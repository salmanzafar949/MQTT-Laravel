<?php

namespace Salman\Mqtt\Tests;

use Illuminate\Notifications\Notification;
use Salman\Mqtt\Facades\Mqtt;
use Salman\Mqtt\MqttManager;
use Salman\Mqtt\Notifications\MqttChannel;
use Salman\Mqtt\Notifications\MqttMessage;

class NotificationChannelTest extends TestCase
{
    public function test_it_publishes_a_notification_with_an_explicit_topic()
    {
        $fake = Mqtt::fake();

        $notification = new class () extends Notification {
            public function toMqtt($notifiable)
            {
                return MqttMessage::create('battery-low')->topic('devices/42/alerts');
            }
        };

        (new MqttChannel($this->app->make(MqttManager::class)))
            ->send(new DummyNotifiable(), $notification);

        $fake->assertPublished('devices/42/alerts', 'battery-low');
    }

    public function test_it_falls_back_to_the_notifiable_route_for_the_topic()
    {
        $fake = Mqtt::fake();

        $notification = new class () extends Notification {
            public function toMqtt($notifiable)
            {
                // A bare string payload, no topic.
                return 'ping';
            }
        };

        (new MqttChannel($this->app->make(MqttManager::class)))
            ->send(new DummyNotifiable(), $notification);

        $fake->assertPublished('devices/42', 'ping');
    }

    public function test_the_mqtt_channel_is_registered_and_usable_via_the_pipeline()
    {
        $fake = Mqtt::fake();

        $notification = new class () extends Notification {
            public function via($notifiable)
            {
                return ['mqtt'];
            }

            public function toMqtt($notifiable)
            {
                return MqttMessage::create('hi')->topic('pipeline/topic');
            }
        };

        $this->app->make(\Illuminate\Contracts\Notifications\Dispatcher::class)
            ->send(new DummyNotifiable(), $notification);

        $fake->assertPublished('pipeline/topic', 'hi');
    }
}

class DummyNotifiable
{
    use \Illuminate\Notifications\Notifiable;

    public function routeNotificationFor($channel, $notification = null)
    {
        return $channel === 'mqtt' ? 'devices/42' : null;
    }
}
