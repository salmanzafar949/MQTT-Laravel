<?php

namespace Salman\Mqtt;

use BadMethodCallException;
use InvalidArgumentException;
use Salman\Mqtt\Jobs\PublishMqttMessage;
use Salman\Mqtt\MqttClass\Mqtt;
use Salman\Mqtt\Testing\MqttFake;

/**
 * Resolves MQTT connections by name and proxies calls to the default
 * connection, so that `Mqtt::ConnectAndPublish(...)` keeps working while
 * `Mqtt::connection('sensors')->...` targets an additional broker.
 *
 * @method bool ConnectAndPublish(string $topic, string $msg, string|int|null $client_id = null, int|null $retain = null, int|null $qos = null)
 * @method bool ConnectAndSubscribe(string|array $topic, callable $proc, string|int|null $client_id = null, int|null $qos = null)
 */
class MqttManager
{
    /** @var array<string, Mqtt> */
    protected $connections = [];

    /** @var MqttFake|null */
    protected $fake = null;

    /**
     * Get an MQTT connection instance by name (or the default connection).
     *
     * @param  string|null  $name
     * @return Mqtt|MqttFake
     */
    public function connection($name = null)
    {
        if ($this->fake !== null) {
            if ($name) {
                // Fail on a typo in tests the same way production would.
                $this->configFor($name);
            }

            return $this->fake->connection($name);
        }

        $name = $name ?: $this->getDefaultConnection();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = new Mqtt($this->configFor($name), $name);
        }

        return $this->connections[$name];
    }

    /**
     * Whether Mqtt::fake() has been called.
     *
     * @return bool
     */
    public function isFaking()
    {
        return $this->fake !== null;
    }

    /**
     * Stop every running subscribe loop in this process, on any connection
     * (including ones created with `new Mqtt()` or the helpers).
     *
     * @return void
     */
    public function interrupt()
    {
        Mqtt::interruptAll();
    }

    /**
     * Swap the real client for a fake that records published messages.
     *
     * @return MqttFake
     */
    public function fake()
    {
        return $this->fake = new MqttFake();
    }

    /**
     * Publish a message asynchronously by dispatching a queued job, so the
     * caller doesn't block on broker I/O.
     *
     * @param  string  $topic
     * @param  string  $message
     * @param  string|int|null  $clientId
     * @param  int|null  $retain
     * @param  string|null  $connection  the MQTT connection name
     * @param  int|null  $qos
     * @return mixed  the queued job's PendingDispatch (or the fake's record)
     */
    public function queue($topic, $message, $clientId = null, $retain = null, $connection = null, $qos = null)
    {
        if ($this->fake !== null) {
            return $this->fake->queue($topic, $message, $clientId, $retain, $connection, $qos);
        }

        $job = new PublishMqttMessage($topic, $message, $clientId, $retain, $connection, $qos);

        if (function_exists('config')) {
            if ($queueName = config('mqtt.queue.name')) {
                $job->onQueue($queueName);
            }
            if ($queueConnection = config('mqtt.queue.connection')) {
                $job->onConnection($queueConnection);
            }
        }

        return dispatch($job);
    }

    /**
     * @return string
     */
    public function getDefaultConnection()
    {
        return function_exists('config') ? (config('mqtt.default') ?: 'default') : 'default';
    }

    /**
     * Resolve the configuration array for a named connection.
     *
     * Connections live under `mqtt.connections.<name>` and inherit the flat
     * top-level `mqtt.*` settings, nested arrays (tls, last_will, reconnect)
     * included. "default" (or whatever `mqtt.default` names, if it isn't
     * defined under `connections`) is the flat config itself.
     *
     * @param  string  $name
     * @return array<string, mixed>
     */
    public function configFor($name)
    {
        $base = function_exists('config') ? (array) config('mqtt') : [];
        $connections = $base['connections'] ?? [];
        unset($base['connections'], $base['default']);

        if (isset($connections[$name]) && is_array($connections[$name])) {
            return array_replace_recursive($base, $connections[$name]);
        }

        if ($name === 'default' || $name === $this->getDefaultConnection()) {
            return $base;
        }

        throw new InvalidArgumentException("MQTT connection [{$name}] is not configured.");
    }

    /**
     * Forward calls (including fake assertions) to the resolved connection.
     *
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($this->fake === null && strncmp($method, 'assert', 6) === 0) {
            throw new BadMethodCallException("Call Mqtt::fake() before Mqtt::{$method}().");
        }

        return $this->connection()->{$method}(...$parameters);
    }
}
