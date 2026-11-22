<?php

namespace Salman\Mqtt\Tests;

use InvalidArgumentException;
use Salman\Mqtt\MqttClass\Mqtt;
use Salman\Mqtt\MqttManager;

class MqttManagerTest extends TestCase
{
    protected function manager(): MqttManager
    {
        return $this->app->make(MqttManager::class);
    }

    public function test_the_default_connection_uses_the_flat_config()
    {
        $config = $this->manager()->configFor('default');

        $this->assertSame('127.0.0.1', $config['host']);
        $this->assertSame('1883', $config['port']);
        // The default connection config must not leak the meta keys.
        $this->assertArrayNotHasKey('connections', $config);
        $this->assertArrayNotHasKey('default', $config);
    }

    public function test_a_named_connection_inherits_and_overrides_defaults()
    {
        config()->set('mqtt.connections.sensors', [
            'host' => 'broker.example.com',
            'port' => '8883',
        ]);

        $config = $this->manager()->configFor('sensors');

        $this->assertSame('broker.example.com', $config['host']);
        $this->assertSame('8883', $config['port']);
        // Inherited from the top-level defaults.
        $this->assertSame(10, $config['keepalive']);
        $this->assertArrayHasKey('tls', $config);
    }

    public function test_named_connections_return_distinct_cached_instances()
    {
        config()->set('mqtt.connections.sensors', ['host' => 'broker.example.com']);

        $default = $this->manager()->connection();
        $sensors = $this->manager()->connection('sensors');

        $this->assertInstanceOf(Mqtt::class, $sensors);
        $this->assertNotSame($default, $sensors);
        $this->assertSame($sensors, $this->manager()->connection('sensors'));
        $this->assertSame('sensors', $sensors->getConnectionName());
    }

    public function test_an_unknown_connection_throws()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager()->configFor('does-not-exist');
    }

    public function test_nested_settings_are_merged_into_named_connections()
    {
        config([
            'mqtt.tls' => ['enabled' => false, 'verify_peer' => true, 'verify_peer_name' => true],
            'mqtt.connections.secure' => ['tls' => ['enabled' => true]],
        ]);

        $config = $this->app->make(MqttManager::class)->configFor('secure');

        $this->assertTrue($config['tls']['enabled']);
        $this->assertTrue($config['tls']['verify_peer']);
        $this->assertTrue($config['tls']['verify_peer_name']);
    }

    public function test_the_default_can_point_at_a_named_connection()
    {
        config([
            'mqtt.host' => 'flat.example.com',
            'mqtt.default' => 'cloud',
            'mqtt.connections.cloud' => ['host' => 'cloud.example.com'],
        ]);

        $manager = $this->app->make(MqttManager::class);

        $this->assertSame('cloud.example.com', $manager->configFor('cloud')['host']);
        $this->assertSame('flat.example.com', $manager->configFor('default')['host']);
    }

    public function test_interrupt_reaches_every_resolved_connection()
    {
        config(['mqtt.connections.sensors' => ['host' => 'sensors.local']]);
        $manager = $this->app->make(MqttManager::class);
        $manager->connection();
        $manager->connection('sensors');

        $manager->interrupt();

        $this->addToAssertionCount(1);
    }
}
