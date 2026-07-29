<?php

namespace Salman\Mqtt\Tests;

use Salman\Mqtt\Facades\Mqtt as MqttFacade;
use Salman\Mqtt\MqttClass\Mqtt;

class ServiceProviderTest extends TestCase
{
    public function test_it_binds_the_mqtt_singleton_into_the_container()
    {
        $this->assertTrue($this->app->bound('Mqtt'));
        $this->assertInstanceOf(Mqtt::class, $this->app->make('Mqtt'));
    }
    public function test_the_mqtt_binding_is_a_singleton()
    {
        $this->assertSame($this->app->make('Mqtt'), $this->app->make('Mqtt'));
    }
    public function test_it_resolves_the_mqtt_facade()
    {
        $this->assertInstanceOf(Mqtt::class, MqttFacade::getFacadeRoot());
    }
    public function test_it_merges_the_default_configuration()
    {
        $this->assertSame('127.0.0.1', config('mqtt.host'));
        $this->assertSame('1883', config('mqtt.port'));
        $this->assertArrayHasKey('qos', config('mqtt'));
        $this->assertArrayHasKey('retain', config('mqtt'));
    }
    public function test_the_helper_functions_are_registered()
    {
        $this->assertTrue(function_exists('connectToPublish'));
        $this->assertTrue(function_exists('connectToSubscribe'));
    }
}
