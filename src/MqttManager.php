<?php

namespace Salman\Mqtt;

use InvalidArgumentException;
use Salman\Mqtt\MqttClass\Mqtt;
use Salman\Mqtt\Testing\MqttFake;

/**
 * Resolves MQTT connections by name and proxies calls to the default
 * connection, so that `Mqtt::ConnectAndPublish(...)` keeps working while
 * `Mqtt::connection('sensors')->...` targets an additional broker.
 *
 * @method bool ConnectAndPublish(string $topic, string $msg, string|int|null $client_id = null, int|null $retain = null)
 * @method bool ConnectAndSubscribe(string|array $topic, callable $proc, string|int|null $client_id = null)
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
            return $this->fake;
        }

        $name = $name ?: $this->getDefaultConnection();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = new Mqtt($this->configFor($name), $name);
        }

        return $this->connections[$name];
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
     * @return string
     */
    public function getDefaultConnection()
    {
        return function_exists('config') ? (config('mqtt.default') ?: 'default') : 'default';
    }

    /**
     * Resolve the configuration array for a named connection.
     *
     * The default connection maps to the flat top-level `mqtt.*` config (kept
     * for backwards compatibility). Additional connections live under
     * `mqtt.connections.<name>` and inherit any top-level defaults.
     *
     * @param  string  $name
     * @return array<string, mixed>
     */
    public function configFor($name)
    {
        $base = function_exists('config') ? (array) config('mqtt') : [];
        $connections = $base['connections'] ?? [];
        unset($base['connections'], $base['default']);

        if ($name === $this->getDefaultConnection()) {
            return $base;
        }

        if (! isset($connections[$name]) || ! is_array($connections[$name])) {
            throw new InvalidArgumentException("MQTT connection [{$name}] is not configured.");
        }

        return array_merge($base, $connections[$name]);
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
        return $this->connection()->{$method}(...$parameters);
    }
}
