<?php

namespace Salman\Mqtt\Testing;

use PHPUnit\Framework\Assert as PHPUnit;

/**
 * A test double for the MQTT client. Swap it in with `Mqtt::fake()` and assert
 * on what your application published, without touching a real broker.
 */
class MqttFake
{
    /** @var array<int, array{topic: string, message: string, client_id: mixed, retain: mixed, connection: string}> */
    protected $published = [];

    /** @var array<int, array{topics: array<int, string>, connection: string}> */
    protected $subscriptions = [];

    /** @var string */
    protected $connection = 'default';

    /**
     * Record a published message instead of sending it.
     *
     * @param  string  $topic
     * @param  string  $msg
     * @param  string|int|null  $client_id
     * @param  int|null  $retain
     * @return bool
     */
    public function ConnectAndPublish($topic, $msg, $client_id = null, $retain = null)
    {
        $this->published[] = [
            'topic' => $topic,
            'message' => $msg,
            'client_id' => $client_id,
            'retain' => $retain,
            'connection' => $this->connection,
        ];

        return true;
    }

    /**
     * Record a subscription without blocking on a broker.
     *
     * @param  string|array  $topic
     * @param  callable  $proc
     * @param  string|int|null  $client_id
     * @return bool
     */
    public function ConnectAndSubscribe($topic, $proc, $client_id = null)
    {
        $this->subscriptions[] = [
            'topics' => is_array($topic) ? array_values($topic) : [$topic],
            'connection' => $this->connection,
        ];

        return true;
    }

    /**
     * Pretend to select a connection (fakes ignore the target broker).
     *
     * @param  string|null  $name
     * @return $this
     */
    public function connection($name = null)
    {
        $this->connection = $name ?: 'default';

        return $this;
    }

    /**
     * All recorded published messages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function published()
    {
        return $this->published;
    }

    /**
     * Assert a message was published to the given topic (optionally matching
     * the payload, either exactly or via a callback).
     *
     * @param  string  $topic
     * @param  string|callable|null  $message
     * @return void
     */
    public function assertPublished($topic, $message = null)
    {
        $matches = array_filter($this->published, function ($record) use ($topic, $message) {
            if ($record['topic'] !== $topic) {
                return false;
            }

            if (is_callable($message)) {
                return (bool) $message($record['message'], $record);
            }

            return $message === null || $record['message'] === $message;
        });

        PHPUnit::assertNotEmpty(
            $matches,
            "The expected MQTT message on topic [{$topic}] was not published."
        );
    }

    /**
     * Assert nothing was published to the given topic.
     *
     * @param  string  $topic
     * @return void
     */
    public function assertNotPublished($topic)
    {
        $matches = array_filter($this->published, function ($record) use ($topic) {
            return $record['topic'] === $topic;
        });

        PHPUnit::assertEmpty(
            $matches,
            "An unexpected MQTT message was published to topic [{$topic}]."
        );
    }

    /**
     * Assert no messages were published at all.
     *
     * @return void
     */
    public function assertNothingPublished()
    {
        PHPUnit::assertEmpty(
            $this->published,
            'MQTT messages were published unexpectedly.'
        );
    }

    /**
     * Assert the total number of published messages.
     *
     * @param  int  $count
     * @return void
     */
    public function assertPublishedCount($count)
    {
        PHPUnit::assertCount(
            $count,
            $this->published,
            "Expected {$count} MQTT messages to be published."
        );
    }
}
