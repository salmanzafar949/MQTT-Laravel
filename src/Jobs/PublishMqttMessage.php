<?php

namespace Salman\Mqtt\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Salman\Mqtt\Exceptions\MqttConnectionException;
use Salman\Mqtt\MqttManager;
use Salman\Mqtt\Support\Arguments;

/**
 * Publishes a message to the broker from a queue worker, so HTTP requests and
 * other hot paths don't block on broker I/O. Dispatch it via Mqtt::queue().
 *
 * A failed publish throws, so the queue retries the job (per your worker's
 * --tries / the job's $tries) and records it in failed_jobs when it gives up.
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

    /** @var int|null */
    public $qos;

    /** @var bool whether $message is base64-encoded (binary payloads can't be JSON-serialised) */
    public $encoded = false;

    /**
     * @param  string  $topic
     * @param  string  $message
     * @param  string|int|null  $clientId
     * @param  int|null  $retain
     * @param  string|null  $connection  the MQTT connection name
     * @param  int|null  $qos
     */
    public function __construct($topic, $message, $clientId = null, $retain = null, $connection = null, $qos = null)
    {
        $message = Arguments::payload($message);

        $this->topic = Arguments::topic($topic);
        $this->encoded = preg_match('//u', $message) !== 1;
        $this->message = $this->encoded ? base64_encode($message) : $message;
        $this->clientId = $clientId;
        $this->retain = $retain;
        $this->mqttConnection = $connection;
        $this->qos = $qos === null ? null : Arguments::qos($qos);
    }

    /**
     * The payload as it will be published.
     *
     * @return string
     */
    public function payload()
    {
        return $this->encoded ? (string) base64_decode($this->message, true) : $this->message;
    }

    /**
     * @return bool
     *
     * @throws MqttConnectionException when the publish fails, so the job is retried
     */
    public function handle(MqttManager $mqtt)
    {
        $published = $mqtt->connection($this->mqttConnection)
            ->ConnectAndPublish($this->topic, $this->payload(), $this->clientId, $this->retain, $this->qos);

        if ($published === false) {
            throw new MqttConnectionException(
                "Publishing the queued MQTT message to [{$this->topic}] failed; see the log for the broker error."
            );
        }

        return true;
    }
}
