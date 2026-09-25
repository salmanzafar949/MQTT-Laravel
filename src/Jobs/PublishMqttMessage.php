<?php

namespace Salman\Mqtt\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Salman\Mqtt\MqttManager;

/**
 * Publishes a message to the broker from a queue worker, so HTTP requests and
 * other hot paths don't block on broker I/O. Dispatch it via Mqtt::queue().
 *
 * Note: the MQTT connection name is stored as $mqttConnection to avoid clashing
 * with the Queueable trait's $connection (the queue connection).
 */
class PublishMqttMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var string */
    public $topic;

    /** @var string */
    public $message;

    /** @var string|int|null */
    public $clientId;

    /** @var int|null */
    public $retain;

    /** @var string|null */
    public $mqttConnection;

    /**
     * @param  string  $topic
     * @param  string  $message
     * @param  string|int|null  $clientId
     * @param  int|null  $retain
     * @param  string|null  $connection  the MQTT connection name
     */
    public function __construct($topic, $message, $clientId = null, $retain = null, $connection = null)
    {
        $this->topic = $topic;
        $this->message = $message;
        $this->clientId = $clientId;
        $this->retain = $retain;
        $this->mqttConnection = $connection;
    }

    /**
     * @return bool
     */
    public function handle(MqttManager $mqtt)
    {
        return $mqtt->connection($this->mqttConnection)
            ->ConnectAndPublish($this->topic, $this->message, $this->clientId, $this->retain);
    }
}
