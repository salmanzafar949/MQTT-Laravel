<?php

namespace Salman\Mqtt\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Argument validation shared by the real client, the queued job and the fake,
 * so a mistake fails the same way in tests as in production.
 *
 * @internal
 */
class Arguments
{
    /**
     * A topic to publish to: non-empty, no wildcards, no NUL bytes.
     *
     * @param  mixed  $topic
     * @return string
     */
    public static function topic($topic)
    {
        if (! is_string($topic) || $topic === '') {
            throw new InvalidArgumentException('An MQTT topic must be a non-empty string.');
        }

        if (strpbrk($topic, "+#\0") !== false) {
            throw new InvalidArgumentException("The MQTT topic [{$topic}] can't contain the wildcards + or # when publishing.");
        }

        return $topic;
    }

    /**
     * One or more topic filters to subscribe to.
     *
     * @param  mixed  $topics
     * @return array<int, string>
     */
    public static function filters($topics)
    {
        $filters = array_values((array) $topics);

        if ($filters === []) {
            throw new InvalidArgumentException('Subscribe to at least one MQTT topic.');
        }

        foreach ($filters as $filter) {
            if (! is_string($filter) || $filter === '' || strpos($filter, "\0") !== false) {
                throw new InvalidArgumentException('MQTT topic filters must be non-empty strings.');
            }
        }

        return $filters;
    }

    /**
     * A message payload as a string. Arrays and objects without __toString()
     * are rejected; encode them yourself (e.g. json_encode()).
     *
     * @param  mixed  $payload
     * @return string
     */
    public static function payload($payload)
    {
        if ($payload === null || is_scalar($payload) || $payload instanceof Stringable
            || (is_object($payload) && method_exists($payload, '__toString'))) {
            return is_bool($payload) ? ($payload ? '1' : '0') : (string) $payload;
        }

        throw new InvalidArgumentException(
            'An MQTT payload must be a string, got '.get_debug_type($payload).'. Encode it first, e.g. with json_encode().'
        );
    }

    /**
     * A quality of service level: 0, 1 or 2.
     *
     * @param  mixed  $qos
     * @return int
     */
    public static function qos($qos)
    {
        if (is_bool($qos) || ! is_numeric($qos) || ! in_array((string) $qos, ['0', '1', '2'], true)) {
            throw new InvalidArgumentException('MQTT QoS must be 0, 1 or 2, got ['.(is_scalar($qos) ? var_export($qos, true) : get_debug_type($qos)).'].');
        }

        return (int) $qos;
    }
}
