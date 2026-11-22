<?php

namespace Salman\Mqtt\Tests;

use Illuminate\Support\Facades\Event;
use ReflectionMethod;
use Salman\Mqtt\Events\MqttMessageReceived;
use Salman\Mqtt\MqttManager;

class EventTest extends TestCase
{
    public function test_the_event_exposes_topic_message_and_connection()
    {
        $event = new MqttMessageReceived('a/b', 'hello', 'sensors');

        $this->assertSame('a/b', $event->topic);
        $this->assertSame('hello', $event->message);
        $this->assertSame('sensors', $event->connection);
    }

    public function test_receiving_a_message_dispatches_the_event()
    {
        Event::fake([MqttMessageReceived::class]);

        $connection = $this->app->make(MqttManager::class)->connection();

        // Exercise the same dispatch path the subscribe handler uses.
        $dispatch = new ReflectionMethod($connection, 'dispatchReceived');
        $dispatch->setAccessible(true);
        $dispatch->invoke($connection, 'home/light', 'on');

        Event::assertDispatched(MqttMessageReceived::class, function ($event) {
            return $event->topic === 'home/light'
                && $event->message === 'on'
                && $event->connection === 'default';
        });
    }

    public function test_binary_messages_survive_serialisation_for_queued_listeners()
    {
        $event = new \Salman\Mqtt\Events\MqttMessageReceived('t', "\xff\x00\xfe", 'second');

        $this->assertNotFalse(json_encode(['data' => serialize($event)]));

        $copy = unserialize(serialize($event));
        $this->assertSame("\xff\x00\xfe", $copy->message);
        $this->assertSame('t', $copy->topic);
        $this->assertSame('second', $copy->connection);
    }
}
