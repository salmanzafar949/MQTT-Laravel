<?php

namespace Salman\Mqtt\Events;

/**
 * Dispatched for every message received by a subscription, in addition to the
 * subscription callback being invoked. Register a listener for this event to
 * handle incoming MQTT messages the Laravel way.
 */
class MqttMessageReceived
{
    /** @var string */
    public $topic;

    /** @var string */
    public $message;

    /** @var string */
    public $connection;

    /**
     * @param  string  $topic
     * @param  string  $message
     * @param  string  $connection
     */
    public function __construct($topic, $message, $connection = 'default')
    {
        $this->topic = $topic;
        $this->message = $message;
        $this->connection = $connection;
    }
}
