<?php

/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 1:16 PM
 */

namespace Salman\Mqtt\MqttClass;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;
use Psr\Log\LoggerInterface;
use Salman\Mqtt\Events\MqttMessageReceived;
use Salman\Mqtt\Exceptions\MqttConnectionException;

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

/**
 * One MQTT connection, backed by php-mqtt/client.
 */
class Mqtt
{
    /** @var array<string, mixed> */
    protected $config;

    /** @var string name of the connection this instance represents */
    protected $connection = 'default';

    /** @var MqttClient|null the client currently running a subscribe loop */
    protected $activeClient = null;

    /**
     * @param  array<string, mixed>|null  $config  connection config; when null the
     *                                              flat `mqtt.*` config is used (the default connection)
     * @param  string  $connection  the connection name (used for dispatched events)
     */
    public function __construct(?array $config = null, $connection = 'default')
    {
        if ($config === null) {
            $config = function_exists('config') ? (array) config('mqtt') : [];
        }

        $this->config = $config;
        $this->connection = $connection;
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
     * Connect, publish one message and disconnect. For QoS 1 and 2 this waits
     * for the broker to acknowledge the message before disconnecting.
     *
     * @param  string  $topic
     * @param  string  $msg
     * @param  string|int|null  $client_id
     * @param  int|null  $retain
     * @param  int|null  $qos  defaults to the connection's `qos`
     * @return bool
     */
    public function ConnectAndPublish($topic, $msg, $client_id = null, $retain = null, $qos = null)
    {
        $qos = $this->resolveQos($qos);
        $retain = $this->resolveRetain($retain);

        return $this->attempt(function () use ($topic, $msg, $client_id, $retain, $qos) {
            $client = $this->connect($client_id);

            try {
                $client->publish($topic, (string) $msg, $qos, $retain);

                if ($qos > 0) {
                    // Run the loop until the PUBACK / PUBREC-PUBREL-PUBCOMP flow completes.
                    $client->loop(true, true);
                }
            } finally {
                $this->disconnect($client);
            }

            return true;
        });
    }

    /**
     * Connect and listen on one or more topic filters. Blocks until
     * interrupt() is called (e.g. from a signal handler), then disconnects.
     *
     * @param  string|array<int, string>  $topic
     * @param  callable  $proc  receives ($topic, $message)
     * @param  string|int|null  $client_id
     * @param  int|null  $qos  defaults to the connection's `qos`
     * @return bool
     */
    public function ConnectAndSubscribe($topic, $proc, $client_id = null, $qos = null)
    {
        $qos = $this->resolveQos($qos);

        return $this->attempt(function () use ($topic, $proc, $client_id, $qos) {
            $client = $this->connect($client_id);
            $this->activeClient = $client;

            try {
                foreach ((array) $topic as $filter) {
                    $client->subscribe($filter, function ($topic, $message) use ($proc) {
                        $this->dispatchReceived($topic, $message);
                        call_user_func($proc, $topic, $message);
                    }, $qos);
                }

                $client->loop(true);
            } finally {
                $this->activeClient = null;
                $this->disconnect($client);
            }

            return true;
        });
    }

    /**
     * Stop a running ConnectAndSubscribe() loop so it disconnects cleanly.
     *
     * @return void
     */
    public function interrupt()
    {
        if ($this->activeClient !== null) {
            $this->activeClient->interrupt();
        }
    }

    /**
     * Run an operation, turning client failures into `false` (or an
     * MqttConnectionException when `exceptions` is enabled).
     *
     * @return bool
     */
    protected function attempt(callable $operation)
    {
        try {
            return $operation();
        } catch (MqttClientException $e) {
            $this->report($e);

            if ($this->config['exceptions'] ?? false) {
                throw new MqttConnectionException($e->getMessage(), (int) $e->getCode(), $e);
            }

            return false;
        }
    }

    /**
     * @param  string|int|null  $clientId
     * @return MqttClient
     */
    protected function connect($clientId)
    {
        $reconnect = (bool) ($this->config['reconnect']['enabled'] ?? false);

        $client = new MqttClient(
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 1883),
            $this->resolveClientId($clientId),
            $this->protocol(),
            null,
            $this->logger()
        );

        // php-mqtt/client does not allow automatic reconnects with a clean session.
        $cleanSession = $reconnect ? false : (bool) ($this->config['clean_session'] ?? true);

        $client->connect($this->settings(), $cleanSession);

        return $client;
    }

    /**
     * @return ConnectionSettings
     */
    protected function settings()
    {
        $settings = (new ConnectionSettings())
            ->setUsername($this->nullable($this->config['username'] ?? null))
            ->setPassword($this->nullable($this->config['password'] ?? null))
            ->setConnectTimeout((int) ($this->config['timeout'] ?? 10))
            ->setKeepAliveInterval((int) ($this->config['keepalive'] ?? 10));

        $tls = (array) ($this->config['tls'] ?? []);
        $caFile = $this->nullable($this->config['certfile'] ?? null);

        if (($tls['enabled'] ?? false) || $caFile !== null) {
            $settings = $settings->setUseTls(true)
                ->setTlsVerifyPeer((bool) ($tls['verify_peer'] ?? true))
                ->setTlsVerifyPeerName((bool) ($tls['verify_peer_name'] ?? true))
                ->setTlsSelfSignedAllowed((bool) ($tls['allow_self_signed'] ?? false))
                ->setTlsCertificateAuthorityFile($caFile)
                ->setTlsClientCertificateFile($this->nullable($this->config['localcert'] ?? null))
                ->setTlsClientCertificateKeyFile($this->nullable($this->config['localpk'] ?? null))
                ->setTlsClientCertificateKeyPassphrase($this->nullable($tls['passphrase'] ?? null));
        }

        $will = (array) ($this->config['last_will'] ?? []);

        if (! empty($will['topic'])) {
            $settings = $settings->setLastWillTopic($will['topic'])
                ->setLastWillMessage((string) ($will['message'] ?? ''))
                ->setLastWillQualityOfService((int) ($will['qos'] ?? 0))
                ->setRetainLastWill((bool) ($will['retain'] ?? false));
        }

        $reconnect = (array) ($this->config['reconnect'] ?? []);

        if (! empty($reconnect['enabled'])) {
            $settings = $settings->setReconnectAutomatically(true)
                ->setMaxReconnectAttempts((int) ($reconnect['max_attempts'] ?? 3))
                ->setDelayBetweenReconnectAttempts((int) ($reconnect['delay'] ?? 1000));
        }

        return $settings;
    }

    /**
     * @return string
     */
    protected function protocol()
    {
        return ($this->config['protocol'] ?? MqttClient::MQTT_3_1_1) === MqttClient::MQTT_3_1
            ? MqttClient::MQTT_3_1
            : MqttClient::MQTT_3_1_1;
    }

    /**
     * Pass the framework logger to the client only in debug mode; the client
     * logs every packet at debug level.
     *
     * @return LoggerInterface|null
     */
    protected function logger()
    {
        if (! ($this->config['debug'] ?? false)) {
            return null;
        }

        if (function_exists('app') && app()->bound('log')) {
            return app('log');
        }

        return null;
    }

    /**
     * @return void
     */
    protected function report(MqttClientException $e)
    {
        $message = 'MQTT ['.$this->connection.'] '.$e->getMessage();

        if (function_exists('app') && app()->bound('log')) {
            app('log')->error($message);

            return;
        }

        error_log($message);
    }

    /**
     * @param  MqttClient  $client
     * @return void
     */
    protected function disconnect($client)
    {
        try {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        } catch (MqttClientException $e) {
            // The connection is already gone; nothing left to close.
        }
    }

    /**
     * @param  int|null  $qos
     * @return int
     */
    protected function resolveQos($qos)
    {
        $qos = (int) ($qos ?? ($this->config['qos'] ?? 0));

        return max(0, min(2, $qos));
    }

    /**
     * @param  int|bool|null  $retain
     * @return bool
     */
    protected function resolveRetain($retain)
    {
        return (bool) (empty($retain) ? ($this->config['retain'] ?? 0) : $retain);
    }

    /**
     * Generate a reasonably unique client id when none is supplied.
     *
     * @param  string|int|null  $clientId
     * @return string
     */
    protected function resolveClientId($clientId)
    {
        return empty($clientId) ? uniqid('lmq_') : (string) $clientId;
    }

    /**
     * Treat empty strings (the config defaults) as "not set".
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function nullable($value)
    {
        return ($value === null || $value === '') ? null : (string) $value;
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
}
