<?php

namespace Salman\Mqtt\Tests;

use Salman\Mqtt\Facades\Mqtt as MqttFacade;
use Salman\Mqtt\MqttClass\Mqtt;
use Salman\Mqtt\MqttManager;

class ServiceProviderTest extends TestCase
{
    public function test_it_binds_the_mqtt_manager_into_the_container()
    {
        $this->assertTrue($this->app->bound('Mqtt'));
        $this->assertInstanceOf(MqttManager::class, $this->app->make('Mqtt'));
        $this->assertInstanceOf(MqttManager::class, $this->app->make(MqttManager::class));
    }
    public function test_the_mqtt_binding_is_a_singleton()
    {
        $this->assertSame($this->app->make('Mqtt'), $this->app->make('Mqtt'));
    }
    public function test_it_resolves_the_mqtt_facade_to_the_manager()
    {
        $this->assertInstanceOf(MqttManager::class, MqttFacade::getFacadeRoot());
    }
    public function test_the_default_connection_resolves_to_an_mqtt_instance()
    {
        $this->assertInstanceOf(Mqtt::class, $this->app->make('Mqtt')->connection());
    }
    public function test_it_merges_the_default_configuration()
    {
        $this->assertSame('127.0.0.1', config('mqtt.host'));
        $this->assertSame('1883', config('mqtt.port'));
        $this->assertArrayHasKey('qos', config('mqtt'));
        $this->assertArrayHasKey('retain', config('mqtt'));
    }

    public function test_it_exposes_the_new_reliability_configuration()
    {
        $this->assertSame(10, config('mqtt.keepalive'));
        $this->assertFalse(config('mqtt.exceptions'));
        $this->assertIsArray(config('mqtt.tls'));
        $this->assertTrue(config('mqtt.tls.verify_peer'));
        $this->assertFalse(config('mqtt.tls.allow_self_signed'));
    }

    public function test_the_mqtt_methods_are_callable_in_both_cases()
    {
        $mqtt = $this->app->make('Mqtt');

        // PHP method names are case-insensitive, so camelCase resolves too.
        $this->assertTrue(is_callable([$mqtt, 'ConnectAndPublish']));
        $this->assertTrue(is_callable([$mqtt, 'connectAndPublish']));
        $this->assertTrue(is_callable([$mqtt, 'connectAndSubscribe']));
    }
    public function test_the_helper_functions_are_registered()
    {
        $this->assertTrue(function_exists('connectToPublish'));
        $this->assertTrue(function_exists('connectToSubscribe'));
    }
}
