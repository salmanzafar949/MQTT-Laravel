<?php

namespace Salman\Mqtt\Tests;

use Salman\Mqtt\Exceptions\MqttConnectionException;
use Illuminate\Support\Facades\Queue;
use Salman\Mqtt\Facades\Mqtt;
use Salman\Mqtt\Jobs\PublishMqttMessage;
use Salman\Mqtt\MqttManager;

class QueuedPublishTest extends TestCase
{
    public function test_queue_dispatches_the_publish_job()
    {
        Queue::fake();

        Mqtt::queue('home/light', 'on', 'client-1', 1);

        Queue::assertPushed(PublishMqttMessage::class, function ($job) {
            return $job->topic === 'home/light'
                && $job->message === 'on'
                && $job->clientId === 'client-1'
                && $job->retain === 1;
        });
    }

    public function test_the_job_publishes_via_the_manager_when_handled()
    {
        $fake = Mqtt::fake();

        $job = new PublishMqttMessage('sensors/temp', '21.5');
        $job->handle($this->app->make(MqttManager::class));

        $fake->assertPublished('sensors/temp', '21.5');
    }

    public function test_the_job_targets_the_requested_mqtt_connection()
    {
        config(['mqtt.connections.sensors' => ['host' => 'sensors.local']]);
        $fake = Mqtt::fake();

        $job = new PublishMqttMessage('sensors/temp', '21.5', null, null, 'sensors');
        $job->handle($this->app->make(MqttManager::class));

        $fake->assertPublished('sensors/temp', function ($payload, $record) {
            return $record['connection'] === 'sensors';
        });
    }

    public function test_fake_records_queued_publishes()
    {
        Mqtt::fake();

        Mqtt::queue('home/door', 'open');

        Mqtt::assertQueued('home/door');
        Mqtt::assertQueued('home/door', 'open');
        Mqtt::assertQueued('home/door', fn ($payload) => $payload === 'open');
        Mqtt::assertQueuedCount(1);
        Mqtt::assertNotQueued('home/window');
    }

    public function test_assert_nothing_queued()
    {
        Mqtt::fake();

        Mqtt::assertNothingQueued();
    }

    public function test_queue_config_routes_the_job_to_a_specific_queue()
    {
        config(['mqtt.queue.name' => 'mqtt', 'mqtt.queue.connection' => 'redis']);
        Queue::fake();

        Mqtt::queue('t', 'm');

        Queue::assertPushed(PublishMqttMessage::class, function ($job) {
            return $job->queue === 'mqtt' && $job->connection === 'redis';
        });
    }

    public function test_a_failed_publish_makes_the_job_throw_so_it_is_retried()
    {
        config(['mqtt.host' => '127.0.0.1', 'mqtt.port' => 1, 'mqtt.timeout' => 1]);

        $this->expectException(MqttConnectionException::class);

        (new PublishMqttMessage('t', 'm'))->handle($this->app->make(MqttManager::class));
    }

    public function test_binary_payloads_survive_queue_serialisation()
    {
        $binary = "\xff\xfe\x00\x01 raw";
        $job = new PublishMqttMessage('t', $binary);

        // The queue JSON-encodes the serialised job; invalid UTF-8 would break it.
        $this->assertNotFalse(json_encode(['data' => serialize($job)]));
        $this->assertSame($binary, unserialize(serialize($job))->payload());

        $fake = \Salman\Mqtt\Facades\Mqtt::fake();
        $job->handle($this->app->make(MqttManager::class));
        $this->assertSame($binary, $fake->published()[0]['message']);
    }

    public function test_invalid_arguments_fail_at_dispatch_time()
    {
        $this->expectException(\InvalidArgumentException::class);

        \Salman\Mqtt\Facades\Mqtt::queue('sensors/#', 'm');
    }
}
