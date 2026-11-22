<?php

/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 1:16 PM
 */

namespace Salman\Mqtt\MqttClass;

use InvalidArgumentException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;
use Salman\Mqtt\Events\MqttMessageReceived;
use Salman\Mqtt\Exceptions\MqttConnectionException;
use Salman\Mqtt\MqttManager;
use Salman\Mqtt\Support\Arguments;

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

    /** @var Logger|null */
    protected $logger = null;

    /** @var array<int, self> instances currently running a subscribe loop */
    protected static $running = [];

    /**
     * @param  array<string, mixed>|null  $config  connection config; when null it's
     *                                              read from `config/mqtt.php` for $connection
     * @param  string|null  $connection  the connection name; null (or "default") is the
     *                                   connection `mqtt.default` points at
     */
    public function __construct(?array $config = null, $connection = null)
    {
        if ($config === null) {
            [$config, $connection] = $this->resolveConfig($connection);
        }

        $this->config = $config;
        $this->connection = $connection ?: 'default';
    }

    /**
     * Stop every subscribe loop running in this process, however the
     * connection was created (facade, `new Mqtt()` or the helpers).
     *
     * @return void
     */
    public static function interruptAll()
    {
        foreach (static::$running as $instance) {
            $instance->interrupt();
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
     * Connect, publish one message and disconnect. For QoS 1 and 2 this waits
     * (up to `timeout` seconds) for the broker to acknowledge the message.
     *
     * @param  string  $topic
     * @param  string  $msg
     * @param  string|int|null  $client_id
     * @param  int|bool|null  $retain  null uses the connection's `retain`
     * @param  int|null  $qos  null uses the connection's `qos`
     * @return bool
     *
     * @throws InvalidArgumentException for an invalid topic, payload or QoS
     */
    public function ConnectAndPublish($topic, $msg, $client_id = null, $retain = null, $qos = null)
    {
        if ($fake = $this->fakeManager()) {
            return $fake->connection($this->connection)->ConnectAndPublish($topic, $msg, $client_id, $retain, $qos);
        }

        $topic = Arguments::topic($topic);
        $msg = Arguments::payload($msg);
        $qos = $this->resolveQos($qos);
        $retain = $this->resolveRetain($retain);

        return $this->attempt(function () use ($topic, $msg, $client_id, $retain, $qos) {
            $repository = new MemoryRepository();
            $client = $this->makeClient($this->resolveClientId($client_id), $repository);

            try {
                // A publish is one short connection: always a clean session (a
                // persistent one would be left behind on the broker), and no Last
                // Will or reconnects, which are for long-lived subscribers.
                $client->connect($this->settings(false, false), true);
                $client->publish($topic, $msg, $qos, $retain);

                if ($qos > 0) {
                    $this->waitForAcknowledgement($client, $repository);
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
     * Exceptions thrown by the callback or by event listeners are logged and
     * don't stop the loop.
     *
     * @param  string|array<int, string>  $topic
     * @param  callable  $proc  receives ($topic, $message)
     * @param  string|int|null  $client_id
     * @param  int|null  $qos  null uses the connection's `qos`
     * @return bool  true after interrupt(), false if the connection failed or was lost
     *
     * @throws InvalidArgumentException for invalid topic filters or QoS
     */
    public function ConnectAndSubscribe($topic, $proc, $client_id = null, $qos = null)
    {
        if ($fake = $this->fakeManager()) {
            return $fake->connection($this->connection)->ConnectAndSubscribe($topic, $proc, $client_id, $qos);
        }

        $filters = Arguments::filters($topic);
        $qos = $this->resolveQos($qos);

        if (! is_callable($proc)) {
            throw new InvalidArgumentException('The subscribe callback must be callable.');
        }

        return $this->attempt(function () use ($filters, $proc, $client_id, $qos) {
            $generatedId = $client_id === null || $client_id === '';
            $clientId = $this->resolveClientId($client_id);
            $reconnect = (bool) ($this->config['reconnect']['enabled'] ?? false);
            $repository = new MemoryRepository();
            $client = $this->makeClient($clientId, $repository);

            // Deliver through the client-wide hook rather than per-subscription
            // callbacks: with a persistent session the broker sends queued
            // messages right after CONNACK, before the SUBACK that activates a
            // subscription callback, and those messages would otherwise be lost.
            $client->registerMessageReceivedEventHandler(function ($client, $topic, $message) use ($proc) {
                $this->deliver($proc, $topic, $message);
            });

            // After an automatic reconnect, subscribe again in case the broker
            // lost the session.
            $client->registerConnectedEventHandler(function ($client, $isAutoReconnect) use ($filters, $qos) {
                if ($isAutoReconnect) {
                    $this->subscribeAll($client, $filters, $qos);
                }
            });

            $this->activeClient = $client;
            static::$running[spl_object_id($this)] = $this;
            $connected = false;

            try {
                // php-mqtt/client can't combine reconnects with a clean session. A
                // generated id can never resume its session, so it otherwise
                // always gets a clean one.
                $cleanSession = $reconnect ? false : ($generatedId || (bool) ($this->config['clean_session'] ?? true));
                $client->connect($this->settings($reconnect, true), $cleanSession);
                $connected = true;

                $this->subscribeAll($client, $filters, $qos);
                $this->waitForAcknowledgement($client, $repository);

                if ($repository->countSubscriptions() < count($filters)) {
                    throw new MqttClientException('The broker rejected the subscription to ['.implode(', ', $filters).'].');
                }

                $client->run();
            } catch (Interrupted $e) {
                // interrupt() was called during a reconnect: a clean stop.
            } finally {
                $this->activeClient = null;
                unset(static::$running[spl_object_id($this)]);
                $this->disconnect($client);

                if ($connected && $reconnect && $generatedId) {
                    $this->discardSession($clientId);
                }
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
     * @param  string  $clientId
     * @return Client
     */
    protected function makeClient($clientId, Repository $repository)
    {
        $client = new Client(
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 1883),
            $clientId,
            $this->protocol(),
            $repository,
            $this->logger()
        );

        return $client->setReadTimeout($this->timeout());
    }

    /**
     * Reconnects force a persistent session. With a generated client id nobody
     * can resume it, so connect once more with a clean session to make the
     * broker drop it instead of keeping it forever.
     *
     * @param  string  $clientId
     * @return void
     */
    protected function discardSession($clientId)
    {
        $client = $this->makeClient($clientId, new MemoryRepository());

        try {
            $client->connect($this->settings(false, false), true);
        } catch (MqttClientException $e) {
            return;
        }

        $this->disconnect($client);
    }

    /**
     * Run the loop until every outgoing message is acknowledged (PUBACK for
     * QoS 1, PUBREC/PUBREL/PUBCOMP for QoS 2, SUBACK) or the timeout passes.
     *
     * @return void
     */
    protected function waitForAcknowledgement(MqttClient $client, Repository $repository)
    {
        $startedAt = microtime(true);
        $timeout = $this->timeout();

        while ($repository->countPendingOutgoingMessages() > 0) {
            if (microtime(true) - $startedAt >= $timeout) {
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    sprintf('the broker did not acknowledge within %s seconds.', $timeout)
                );
            }

            $client->loopOnce($startedAt, true, 1000);
        }
    }

    /**
     * @param  array<int, string>  $filters
     * @return void
     */
    protected function subscribeAll(MqttClient $client, array $filters, int $qos)
    {
        foreach ($filters as $filter) {
            $client->subscribe($filter, null, $qos);
        }
    }

    /**
     * Fire the event, then the callback. A failing listener doesn't stop the
     * callback, and neither stops the subscribe loop.
     *
     * @param  string  $topic
     * @param  string  $message
     * @return void
     */
    protected function deliver(callable $proc, $topic, $message)
    {
        try {
            $this->dispatchReceived($topic, $message);
        } catch (\Throwable $e) {
            $this->logger()->error('An MqttMessageReceived listener failed for a message on [{topic}]: {error}', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        try {
            call_user_func($proc, $topic, $message);
        } catch (\Throwable $e) {
            $this->logger()->error('The subscribe callback failed for a message on [{topic}]: {error}', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * @param  bool  $reconnect  enable automatic reconnects
     * @param  bool  $lastWill  attach the configured Last Will
     * @return ConnectionSettings
     */
    protected function settings($reconnect = false, $lastWill = true)
    {
        $settings = (new ConnectionSettings())
            ->setUsername($this->nullable($this->config['username'] ?? null))
            ->setPassword($this->nullable($this->config['password'] ?? null))
            ->setConnectTimeout((int) ceil($this->timeout()))
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

        if ($lastWill && ! empty($will['topic'])) {
            $settings = $settings->setLastWillTopic(Arguments::topic($will['topic']))
                ->setLastWillMessage(Arguments::payload($will['message'] ?? ''))
                ->setLastWillQualityOfService(Arguments::qos($will['qos'] ?? 0))
                ->setRetainLastWill((bool) ($will['retain'] ?? false));
        }

        if ($reconnect) {
            $options = (array) ($this->config['reconnect'] ?? []);

            $settings = $settings->setReconnectAutomatically(true)
                ->setMaxReconnectAttempts((int) ($options['max_attempts'] ?? 3))
                ->setDelayBetweenReconnectAttempts((int) ($options['delay'] ?? 1000));
        }

        return $settings;
    }

    /**
     * @return string
     */
    protected function protocol()
    {
        $protocol = (string) ($this->config['protocol'] ?? MqttClient::MQTT_3_1_1);

        if (! in_array($protocol, [MqttClient::MQTT_3_1_1, MqttClient::MQTT_3_1], true)) {
            throw new InvalidArgumentException("Unsupported MQTT protocol [{$protocol}]; use '3.1.1' or '3.1'.");
        }

        return $protocol;
    }

    /**
     * Connect / read / acknowledgement timeout in seconds.
     *
     * @return float
     */
    protected function timeout()
    {
        $timeout = (float) ($this->config['timeout'] ?? 10);

        return $timeout > 0 ? $timeout : 10.0;
    }

    /**
     * The logger handed to php-mqtt/client: warnings and errors always, every
     * packet when `debug` is on, with the password masked.
     *
     * @return Logger
     */
    protected function logger()
    {
        return $this->logger ??= new Logger(
            $this->connection,
            (bool) ($this->config['debug'] ?? false),
            [$this->nullable($this->config['password'] ?? null), $this->nullable($this->config['tls']['passphrase'] ?? null)]
        );
    }

    /**
     * @return void
     */
    protected function report(MqttClientException $e)
    {
        $this->logger()->error($e->getMessage());
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
     * @param  int|string|null  $qos
     * @return int
     */
    protected function resolveQos($qos)
    {
        return Arguments::qos($qos ?? ($this->config['qos'] ?? 0));
    }

    /**
     * null means "use the connection's `retain` setting"; anything else is
     * taken as the flag itself, so an explicit 0 / false turns retain off.
     *
     * @param  int|bool|string|null  $retain
     * @return bool
     */
    protected function resolveRetain($retain)
    {
        return (bool) ($retain ?? ($this->config['retain'] ?? false));
    }

    /**
     * Generate a random client id when none is supplied (20 characters, within
     * MQTT 3.1's 23-character limit).
     *
     * @param  string|int|null  $clientId
     * @return string
     */
    protected function resolveClientId($clientId)
    {
        if ($clientId === null || $clientId === '') {
            return 'lmq_'.bin2hex(random_bytes(8));
        }

        return (string) $clientId;
    }

    /**
     * The config for a connection name, through the manager when running in
     * Laravel so `mqtt.default` and `mqtt.connections` are honoured.
     *
     * @param  string|null  $connection
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function resolveConfig($connection)
    {
        if (function_exists('app') && app()->bound(MqttManager::class)) {
            $manager = app(MqttManager::class);
            $name = ($connection === null || $connection === '' || $connection === 'default')
                ? $manager->getDefaultConnection()
                : (string) $connection;

            return [$manager->configFor($name), $name];
        }

        return [function_exists('config') ? (array) config('mqtt') : [], $connection ?: 'default'];
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
     * The manager, if the app has swapped in Mqtt::fake(). Lets code that
     * builds this class directly (helpers, `new Mqtt()`) honour the fake.
     *
     * @return MqttManager|null
     */
    protected function fakeManager()
    {
        if (! function_exists('app')) {
            return null;
        }

        $app = app();

        if (! $app->resolved(MqttManager::class)) {
            return null;
        }

        $manager = $app->make(MqttManager::class);

        return $manager->isFaking() ? $manager : null;
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
