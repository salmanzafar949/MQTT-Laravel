<?php

namespace Salman\Mqtt\Tests;

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
}
