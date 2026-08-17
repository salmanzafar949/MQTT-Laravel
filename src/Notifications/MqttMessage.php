<?php

namespace Salman\Mqtt\Notifications;

/**
 * Fluent message returned from a notification's toMqtt() method.
 */
class MqttMessage
{
    /** @var string|null */
    public $topic = null;

    /** @var string */
    public $payload = '';

    /** @var int|null */
    public $retain = null;

    /** @var string|int|null */
    public $clientId = null;

    /** @var string|null */
    public $connection = null;

    /**
     * @param  string  $payload
     */
    public function __construct($payload = '')
    {
        $this->payload = $payload;
    }

    /**
     * @param  string  $payload
     * @return static
     */
    public static function create($payload = '')
    {
        return new static($payload);
    }

    /**
     * @param  string  $topic
     * @return $this
     */
    public function topic($topic)
    {
        $this->topic = $topic;

        return $this;
    }

    /**
     * @param  string  $payload
     * @return $this
     */
    public function payload($payload)
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @param  int  $retain
     * @return $this
     */
    public function retain($retain = 1)
    {
        $this->retain = $retain;

        return $this;
    }

    /**
     * @param  string|int  $clientId
     * @return $this
     */
    public function clientId($clientId)
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * @param  string  $connection
     * @return $this
     */
    public function connection($connection)
    {
        $this->connection = $connection;

        return $this;
    }
}
