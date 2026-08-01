<?php

namespace Salman\Mqtt\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Salman\Mqtt\Exceptions\MqttConnectionException;
use Salman\Mqtt\MqttClass\MqttService;

class MqttServiceTest extends BaseTestCase
{
    private function makeService()
    {
        // No connection is opened by the constructor, so this is safe to build
        // without a running broker.
        return new MqttService('127.0.0.1', 1883);
    }

    private function invoke($object, $method, array $args = [])
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }

    private function readProperty($object, $property)
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }

    /**
     * Issue #50: the constructor must not require the $timeout / $clientId
     * arguments, so that code migrating from older versions keeps working.
     */
    public function test_the_constructor_accepts_an_optional_timeout_and_client_id()
    {
        $service = new MqttService('broker.example.com', 8883);

        $this->assertSame('broker.example.com', $service->address);
        $this->assertSame(8883, $service->port);
        $this->assertSame(0, $service->timeout);
        $this->assertNull($service->clientid);
    }

    /**
     * Issue #46 / #36 / #30 / #27: subscribing must not throw an
     * "Undefined index: qos" / "array offset on int" error and must honour the
     * per-topic QoS value.
     */
    public function test_it_builds_a_subscribe_payload_using_per_topic_qos()
    {
        $service = $this->makeService();
        $i = 0;

        $topics = [
            'sensors/temperature' => ['qos' => 1, 'function' => function () {
            }],
        ];

        $buffer = $this->invoke($service, 'buildSubscribePayload', [$topics, 0, &$i]);

        // First two bytes are the packet identifier.
        $this->assertSame(0, ord($buffer[0]));
        $this->assertSame(1, ord($buffer[1]));

        // Next two bytes are the topic length (big endian).
        $topic = 'sensors/temperature';
        $this->assertSame(strlen($topic) >> 8, ord($buffer[2]));
        $this->assertSame(strlen($topic) % 256, ord($buffer[3]));

        // The topic name itself.
        $this->assertSame($topic, substr($buffer, 4, strlen($topic)));

        // The trailing byte is the requested QoS for this topic.
        $this->assertSame(1, ord(substr($buffer, -1)));

        // The topic must be registered for later message matching.
        $this->assertArrayHasKey($topic, $service->topics);
    }

    /**
     */
    public function test_it_falls_back_to_the_default_qos_when_a_topic_has_none()
    {
        $service = $this->makeService();
        $i = 0;

        $topics = [
            'home/#' => ['function' => function () {
            }],
        ];

        $buffer = $this->invoke($service, 'buildSubscribePayload', [$topics, 2, &$i]);

        $this->assertSame(2, ord(substr($buffer, -1)));
    }

    /**
     * A bare scalar (the legacy "qos seed") or a stray closure in the topic
     * list must be ignored instead of blowing up. Guards issues #38 and #46.
     */
    public function test_it_skips_entries_that_are_not_valid_topic_definitions()
    {
        $service = $this->makeService();
        $i = 0;

        $topics = [
            'qos'          => 0,                 // legacy scalar seed
            'stray'        => function () {
            },    // a bare closure
            'valid/topic'  => ['qos' => 0, 'function' => function () {
            }],
        ];

        $buffer = $this->invoke($service, 'buildSubscribePayload', [$topics, 0, &$i]);

        $this->assertSame(['valid/topic'], array_keys($service->topics));
        $this->assertStringContainsString('valid/topic', $buffer);
        $this->assertStringNotContainsString('stray', $buffer);
    }

    /**
     */
    public function test_set_and_get_message_length_round_trip()
    {
        $service = $this->makeService();

        foreach ([0, 1, 127, 128, 16383, 16384, 2097151] as $length) {
            $encoded = $this->invoke($service, 'setmsglength', [$length]);
            $i = 0;
            $decoded = $this->invoke($service, 'getmsglength', [&$encoded, &$i]);

            $this->assertSame($length, $decoded, "Failed round-trip for length {$length}");
        }
    }

    /**
     */
    public function test_strwritestring_prefixes_the_string_with_its_length()
    {
        $service = $this->makeService();
        $i = 0;

        $result = $this->invoke($service, 'strwritestring', ['hello', &$i]);

        $this->assertSame(0, ord($result[0]));      // length MSB
        $this->assertSame(5, ord($result[1]));      // length LSB
        $this->assertSame('hello', substr($result, 2));
        $this->assertSame(7, $i);                   // 5 + 2 length bytes
    }

    public function test_the_configuration_setters_are_fluent_and_store_values()
    {
        $service = $this->makeService();

        $result = $service
            ->setKeepalive(42)
            ->setTlsOptions(['verify_peer' => false])
            ->throwExceptions(true);

        $this->assertSame($service, $result);
        $this->assertSame(42, $service->keepalive);
        $this->assertSame(['verify_peer' => false], $this->readProperty($service, 'tlsOptions'));
        $this->assertTrue($this->readProperty($service, 'throwExceptions'));
    }

    public function test_a_failed_connection_returns_false_by_default()
    {
        // Port 1 has nothing listening, so the connection is refused quickly.
        $service = new MqttService('127.0.0.1', 1, 1);

        $this->assertFalse(@$service->connect());
    }

    public function test_a_failed_connection_throws_when_exceptions_are_enabled()
    {
        $service = new MqttService('127.0.0.1', 1, 1);
        $service->throwExceptions(true);

        $this->expectException(MqttConnectionException::class);

        @$service->connect();
    }
}
