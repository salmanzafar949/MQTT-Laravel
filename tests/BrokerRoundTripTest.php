<?php

namespace Salman\Mqtt\Tests;

use Salman\Mqtt\MqttClass\Mqtt;

/**
 * Real broker round-trip. Skipped unless MQTT_E2E=1 and an MQTT broker is
 * listening on 127.0.0.1:1883 (see the "integration" CI job). Requires the
 * pcntl extension so the subscriber can run in a forked process.
 */
class BrokerRoundTripTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('MQTT_E2E') !== '1') {
            $this->markTestSkipped('Set MQTT_E2E=1 with a broker on 127.0.0.1:1883 to run integration tests.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for the broker round-trip test.');
        }
    }

    public function test_a_published_message_is_delivered_to_a_subscriber()
    {
        $topic = 'laravel-mqtt/test/'.uniqid();
        $payload = 'e2e-'.uniqid();
        $file = tempnam(sys_get_temp_dir(), 'mqtt-e2e-');

        $pid = pcntl_fork();

        if ($pid === 0) {
            // Child: subscribe and record the first message, with a safety timeout.
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () {
                exit(1);
            });
            pcntl_alarm(10);

            (new Mqtt())->ConnectAndSubscribe($topic, function ($topic, $message) use ($file) {
                file_put_contents($file, $message);
                exit(0);
            });

            exit(0);
        }

        // Parent: give the subscriber a moment to connect and subscribe, then publish.
        usleep(2000000);
        $published = (new Mqtt())->ConnectAndPublish($topic, $payload);
        pcntl_waitpid($pid, $status);

        $received = file_get_contents($file);
        @unlink($file);

        $this->assertTrue($published, 'Publishing to the broker failed.');
        $this->assertSame($payload, $received, 'The subscriber did not receive the published message.');
    }
}
