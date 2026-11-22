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
    private function forkSubscriber(string $topic, string $file, int $expected, int $qos, array $overrides = [], ?string $clientId = null): int
    {
        $pid = pcntl_fork();

        if ($pid === 0) {
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () {
                exit(1);
            });
            pcntl_alarm(15);

            $received = 0;
            (new Mqtt($this->broker($overrides)))->ConnectAndSubscribe($topic, function ($topic, $message) use ($file, $expected, &$received) {
                file_put_contents($file, $message.PHP_EOL, FILE_APPEND);

                if (++$received >= $expected) {
                    exit(0);
                }
            }, $clientId, $qos);

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

    public function test_a_persistent_session_receives_messages_sent_while_offline()
    {
        $topic = 'laravel-mqtt/persist/'.uniqid();
        $clientId = 'lmq-persist-'.substr(uniqid(), -8);
        $session = ['clean_session' => false];
        $file = tempnam(sys_get_temp_dir(), 'mqtt-persist-');

        // 1. Subscribe once with a persistent session, then disconnect cleanly.
        $first = pcntl_fork();
        if ($first === 0) {
            $mqtt = new Mqtt($this->broker($session));
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () use ($mqtt) {
                $mqtt->interrupt();
            });
            pcntl_alarm(1);
            $mqtt->ConnectAndSubscribe($topic, function () {
            }, $clientId, 1);
            exit(0);
        }
        pcntl_waitpid($first, $status);

        // 2. Publish while the subscriber is offline; the broker queues it.
        $this->assertTrue((new Mqtt($this->broker()))->ConnectAndPublish($topic, 'while-offline', null, null, 1));

        // 3. Reconnect with the same client id: the queued message arrives.
        $second = $this->forkSubscriber($topic, $file, 1, 1, $session, $clientId);
        pcntl_waitpid($second, $status);

        $received = trim((string) file_get_contents($file));
        @unlink($file);

        $this->assertSame('while-offline', $received);
    }

    public function test_qos_publishes_complete_without_polling_delays()
    {
        $publisher = new Mqtt($this->broker());
        $started = microtime(true);

        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($publisher->ConnectAndPublish('laravel-mqtt/speed/'.uniqid(), 'm', null, null, $i % 2 + 1));
        }

        $this->assertLessThan(2.0, microtime(true) - $started, '20 QoS 1/2 publishes should take well under 2 seconds on localhost.');
    }

    public function test_publishes_always_use_a_clean_session()
    {
        $topic = 'laravel-mqtt/clean/'.uniqid();
        $clientId = 'lmq-clean-'.substr(uniqid(), -8);
        $file = tempnam(sys_get_temp_dir(), 'mqtt-clean-');
        $session = ['clean_session' => false];

        // A persistent subscriber session for $clientId...
        $first = pcntl_fork();
        if ($first === 0) {
            $mqtt = new Mqtt($this->broker($session));
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () use ($mqtt) {
                $mqtt->interrupt();
            });
            pcntl_alarm(1);
            $mqtt->ConnectAndSubscribe($topic, function () {
            }, $clientId, 1);
            exit(0);
        }
        pcntl_waitpid($first, $status);

        // ...is discarded by a publish with the same id, even though the
        // config asks for persistent sessions.
        $this->assertTrue((new Mqtt($this->broker($session)))->ConnectAndPublish('laravel-mqtt/other', 'x', $clientId));
        $this->assertTrue((new Mqtt($this->broker()))->ConnectAndPublish($topic, 'should-not-be-queued', null, null, 1));

        // So nothing was queued for the old session.
        $watcher = pcntl_fork();
        if ($watcher === 0) {
            $mqtt = new Mqtt($this->broker($session));
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () use ($mqtt) {
                $mqtt->interrupt();
            });
            pcntl_alarm(2);
            $mqtt->ConnectAndSubscribe($topic, function ($t, $m) use ($file) {
                file_put_contents($file, $m, FILE_APPEND);
            }, $clientId, 1);
            exit(0);
        }
        pcntl_waitpid($watcher, $status);

        $received = (string) file_get_contents($file);
        @unlink($file);

        $this->assertSame('', $received);
    }

    public function test_the_facade_interrupt_stops_a_loop_started_with_new_mqtt()
    {
        $topic = 'laravel-mqtt/interrupt/'.uniqid();

        $publisher = pcntl_fork();
        if ($publisher === 0) {
            usleep(800000);
            (new Mqtt($this->broker()))->ConnectAndPublish($topic, 'stop');
            exit(0);
        }

        $started = microtime(true);
        $result = (new Mqtt($this->broker()))->ConnectAndSubscribe($topic, function () {
            \Salman\Mqtt\Facades\Mqtt::interrupt();
        });
        pcntl_waitpid($publisher, $status);

        $this->assertTrue($result);
        $this->assertLessThan(5, microtime(true) - $started);
    }

    public function test_subscribers_receive_messages_without_polling_delay()
    {
        $topic = 'laravel-mqtt/latency/'.uniqid();
        $file = tempnam(sys_get_temp_dir(), 'mqtt-latency-');

        $pid = pcntl_fork();
        if ($pid === 0) {
            pcntl_async_signals(true);
            pcntl_alarm(15);
            $count = 0;
            (new Mqtt($this->broker()))->ConnectAndSubscribe($topic, function ($t, $sentAt) use ($file, &$count) {
                file_put_contents($file, (microtime(true) - (float) $sentAt).PHP_EOL, FILE_APPEND);
                if (++$count >= 10) {
                    exit(0);
                }
            });
            exit(1);
        }

        usleep(1000000);
        $publisher = new Mqtt($this->broker());
        for ($i = 0; $i < 10; $i++) {
            $publisher->ConnectAndPublish($topic, (string) microtime(true));
            usleep(150000);
        }
        pcntl_waitpid($pid, $status);

        $latencies = array_map('floatval', array_filter(explode(PHP_EOL, (string) file_get_contents($file))));
        @unlink($file);
        sort($latencies);

        $this->assertCount(10, $latencies);
        $this->assertLessThan(0.03, $latencies[4], 'Median receive latency should be a few milliseconds.');
    }

    public function test_duplicate_filters_still_subscribe()
    {
        $topic = 'laravel-mqtt/dup/'.uniqid();

        $publisher = pcntl_fork();
        if ($publisher === 0) {
            usleep(800000);
            (new Mqtt($this->broker()))->ConnectAndPublish($topic, 'once');
            exit(0);
        }

        $received = [];
        $mqtt = new Mqtt($this->broker());
        $result = $mqtt->ConnectAndSubscribe([$topic, $topic], function ($t, $m) use (&$received, $mqtt) {
            $received[] = $m;
            $mqtt->interrupt();
        });
        pcntl_waitpid($publisher, $status);

        $this->assertTrue($result);
        $this->assertSame(['once'], $received);
    }
}
