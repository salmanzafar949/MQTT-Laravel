<?php

/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 1:16 PM
 */

namespace Salman\Mqtt\MqttClass;

use Salman\Mqtt\Events\MqttMessageReceived;

/*
    Licence
    Copyright (c) 2019 Salman Zafar
    salmanzafar949@gmail.com
    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.

*/


class Mqtt
{
    protected $host = null;
    protected $username = null;
    protected $cert_file = null;
    protected $local_cert = null;
    protected $local_pk = null;
    protected $password = null;
    protected $port = null;
    protected $timeout = 0;
    protected $debug = null;
    protected $qos = 0;
    protected $retain = 0;
    protected $keepalive = 10;
    protected $tls = [];
    protected $exceptions = false;

    /** @var string name of the connection this instance represents */
    protected $connection = 'default';

    /**
     * @param  array<string, mixed>|null  $config  connection config; when null the
     *                                              flat `mqtt.*` config is used (legacy default connection)
     * @param  string  $connection  the connection name (used for dispatched events)
     */
    public function __construct(?array $config = null, $connection = 'default')
    {
        if ($config === null) {
            $config = function_exists('config') ? (array) config('mqtt') : [];
        }

        $this->connection  = $connection;
        $this->host        = $config['host'] ?? null;
        $this->username    = $config['username'] ?? null;
        $this->password    = $config['password'] ?? null;
        $this->cert_file   = $config['certfile'] ?? null;
        $this->local_cert  = $config['localcert'] ?? null;
        $this->local_pk    = $config['localpk'] ?? null;
        $this->port        = $config['port'] ?? null;
        $this->timeout     = $config['timeout'] ?? 0;
        $this->debug       = $config['debug'] ?? false;
        $this->qos         = $config['qos'] ?? 0;
        $this->retain      = $config['retain'] ?? 0;
        $this->keepalive   = $config['keepalive'] ?? 10;
        $this->tls         = $config['tls'] ?? [];
        $this->exceptions  = $config['exceptions'] ?? false;
    }

    /**
     * Build a configured MqttService instance for the given client id.
     *
     * @param  string|int|null  $clientId
     * @return MqttService
     */
    protected function makeClient($clientId)
    {
        $client = new MqttService(
            $this->host,
            $this->port,
            $this->timeout,
            $this->resolveClientId($clientId),
            $this->cert_file,
            $this->local_cert,
            $this->local_pk,
            $this->debug
        );

        $client->setKeepalive($this->keepalive)
            ->setTlsOptions(is_array($this->tls) ? $this->tls : [])
            ->throwExceptions($this->exceptions);

        if (function_exists('logger')) {
            $client->setLogger(logger());
        }

        return $client;
    }

    /**
     * Generate a reasonably unique client id when none is supplied.
     *
     * @param  string|int|null  $clientId
     * @return string|int
     */
    protected function resolveClientId($clientId)
    {
        return empty($clientId) ? uniqid('lmq_') : $clientId;
    }

    /**
     * Dispatch the MqttMessageReceived event when running inside Laravel.
     *
     * @param  string  $topic
     * @param  string  $message
     * @return void
     */
    protected function dispatchReceived($topic, $message)
    {
        if (function_exists('app') && app()->bound('events')) {
            app('events')->dispatch(new MqttMessageReceived($topic, $message, $this->connection));
        }
    }

    /**
     * The name of the connection this instance represents.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * @param  string  $topic
     * @param  string  $msg
     * @param  string|int|null  $client_id
     * @param  int|null  $retain
     * @return bool
     */
    public function ConnectAndPublish($topic, $msg, $client_id = null, $retain = null)
    {
        $client = $this->makeClient($client_id);

        $retain = empty($retain) ? $this->retain : $retain;

        if ($client->connect(true, null, $this->username, $this->password)) {
            $client->publish($topic, $msg, $this->qos, $retain);
            $client->close();

            return true;
        }

        return false;
    }

    /**
     * @param  string|array  $topic
     * @param  callable  $proc
     * @param  string|int|null  $client_id
     * @return bool
     */
    public function ConnectAndSubscribe($topic, $proc, $client_id = null)
    {
        $client = $this->makeClient($client_id);

        if ($client->connect(true, null, $this->username, $this->password)) {
            $topics = is_array($topic) ? $topic : [$topic];

            $handler = function ($topic, $message) use ($proc) {
                $this->dispatchReceived($topic, $message);

                return call_user_func($proc, $topic, $message);
            };

            $topicData = [];
            foreach ($topics as $topicName) {
                $topicData[$topicName] = ["qos" => (int) $this->qos, "function" => $handler];
            }

            $client->subscribe($topicData, $this->qos);

            while ($client->proc()) {

            }

            $client->close();

            return true;
        }

        return false;
    }
}
