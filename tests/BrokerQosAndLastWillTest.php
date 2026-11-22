<?php

namespace Salman\Mqtt\Tests;

use Salman\Mqtt\MqttClass\Mqtt;

/**
 * Real-broker checks for QoS 1/2 delivery and Last Will and Testament.
 * Skipped unless MQTT_E2E=1 with a broker on 127.0.0.1:1883; requires pcntl.
 */
class BrokerQosAndLastWillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('MQTT_E2E') !== '1') {
            $this->markTestSkipped('Set MQTT_E2E=1 with a broker on 127.0.0.1:1883 to run integration tests.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for broker integration tests.');
        }
    }

    private function broker(array $overrides = []): array
    {
        return array_merge(['host' => '127.0.0.1', 'port' => 1883, 'timeout' => 5], $overrides);
    }

    /**
     * Fork a subscriber that appends each message to $file and exits after
     * $expected messages (or after a safety timeout).
     */
    private function forkSubscriber(string $topic, string $file, int $expected, int $qos): int
    {
        $pid = pcntl_fork();

        if ($pid === 0) {
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () {
                exit(1);
            });
            pcntl_alarm(15);

            $received = 0;
            (new Mqtt($this->broker()))->ConnectAndSubscribe($topic, function ($topic, $message) use ($file, $expected, &$received) {
                file_put_contents($file, $message.PHP_EOL, FILE_APPEND);

                if (++$received >= $expected) {
                    exit(0);
                }
            }, null, $qos);

            exit(0);
        }

        return $pid;
    }

    public function test_qos_1_and_qos_2_messages_are_delivered()
    {
        $topic = 'laravel-mqtt/qos/'.uniqid();
        $file = tempnam(sys_get_temp_dir(), 'mqtt-qos-');

        $pid = $this->forkSubscriber($topic, $file, 2, 2);
        usleep(1500000);

        $publisher = new Mqtt($this->broker());
        $this->assertTrue($publisher->ConnectAndPublish($topic, 'qos-one', null, null, 1), 'QoS 1 publish failed.');
        $this->assertTrue($publisher->ConnectAndPublish($topic, 'qos-two', null, null, 2), 'QoS 2 publish failed.');

        pcntl_waitpid($pid, $status);
        $lines = array_filter(explode(PHP_EOL, (string) file_get_contents($file)));
        @unlink($file);

        $this->assertSame(['qos-one', 'qos-two'], array_values($lines));
    }

    public function test_the_last_will_is_published_when_a_client_drops()
    {
        $willTopic = 'laravel-mqtt/will/'.uniqid();
        $file = tempnam(sys_get_temp_dir(), 'mqtt-will-');

        // Watcher: waits for the will message.
        $watcher = $this->forkSubscriber($willTopic, $file, 1, 1);
        usleep(1500000);

        // Device: connects with a last will, then is killed without disconnecting.
        $device = pcntl_fork();

        if ($device === 0) {
            (new Mqtt($this->broker([
                'keepalive' => 60,
                'last_will' => ['topic' => $willTopic, 'message' => 'device-offline', 'qos' => 1],
            ])))->ConnectAndSubscribe('laravel-mqtt/idle/'.uniqid(), function () {
                //
            });

            exit(0);
        }

        usleep(1500000);
        posix_kill($device, SIGKILL);
        pcntl_waitpid($device, $deviceStatus);

        pcntl_waitpid($watcher, $status);
        $received = trim((string) file_get_contents($file));
        @unlink($file);

        $this->assertSame('device-offline', $received);
    }
}
