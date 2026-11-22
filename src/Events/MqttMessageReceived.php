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

    /**
     * Queued listeners JSON-encode the serialised event, which fails on binary
     * (non-UTF-8) payloads, so those are base64-encoded while serialised.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $binary = preg_match('//u', (string) $this->message) !== 1;

        return [
            'topic' => $this->topic,
            'message' => $binary ? base64_encode((string) $this->message) : $this->message,
            'binary' => $binary,
            'connection' => $this->connection,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->topic = $data['topic'];
        $this->message = ! empty($data['binary']) ? (string) base64_decode($data['message'], true) : $data['message'];
        $this->connection = $data['connection'];
    }
}
