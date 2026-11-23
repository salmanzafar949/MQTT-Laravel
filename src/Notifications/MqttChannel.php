<?php

namespace Salman\Mqtt\Notifications;

use Illuminate\Notifications\Notification;
use RuntimeException;
use Salman\Mqtt\Exceptions\MqttConnectionException;
use Salman\Mqtt\MqttManager;
use Salman\Mqtt\Support\Arguments;

/**
 * Notification channel that publishes notifications over MQTT.
 *
 * Add `'mqtt'` (or MqttChannel::class) to your notification's via() method and
 * implement `toMqtt($notifiable)` returning a string payload or an MqttMessage.
 * The topic comes from the MqttMessage or from the notifiable's
 * routeNotificationFor('mqtt') method.
 */
class MqttChannel
{
    /** @var MqttManager */
    protected $manager;

    public function __construct(MqttManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @return bool
     *
     * @throws MqttConnectionException when the publish fails, so a queued
     *                                 notification is retried
     */
    public function send($notifiable, Notification $notification)
    {
        /** @var MqttMessage|string $message */
        $message = $notification->toMqtt($notifiable);

        if (! $message instanceof MqttMessage) {
            $message = new MqttMessage(Arguments::payload($message));
        }

        $topic = $message->topic ?: $this->routeFor($notifiable, $notification);

        if (empty($topic)) {
            throw new RuntimeException(
                'No MQTT topic set. Return a topic from the notification\'s toMqtt() '
                .'method or a routeNotificationForMqtt() method on the notifiable.'
            );
        }

        $published = $this->manager
            ->connection($message->connection)
            ->ConnectAndPublish($topic, $message->payload, $message->clientId, $message->retain, $message->qos);

        if ($published === false) {
            throw new MqttConnectionException(
                "Sending the MQTT notification to [{$topic}] failed; see the log for the broker error."
            );
        }

        return true;
    }

    /**
     * Resolve the topic from the notifiable's routing, if any.
     *
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @return string|null
     */
    protected function routeFor($notifiable, Notification $notification)
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            return $notifiable->routeNotificationFor('mqtt', $notification);
        }

        return null;
    }
}
