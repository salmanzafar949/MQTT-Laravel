<?php

namespace Salman\Mqtt\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use ReflectionMethod;
use RuntimeException;
use Salman\Mqtt\Events\MqttMessageReceived;
use Salman\Mqtt\Exceptions\MqttConnectionException;
use Salman\Mqtt\MqttClass\Mqtt;

class MqttTest extends TestCase
{
    private function invoke(Mqtt $mqtt, string $method, array $args = [])
    {
        $ref = new ReflectionMethod($mqtt, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($mqtt, $args);
    }

    private function settings(array $config, bool $reconnect = false): ConnectionSettings
    {
        return $this->invoke(new Mqtt($config), 'settings', [$reconnect]);
    }

    public function test_empty_credentials_are_sent_as_null()
    {
        $settings = $this->settings(['username' => '', 'password' => '']);

        $this->assertNull($settings->getUsername());
        $this->assertNull($settings->getPassword());
    }

    public function test_credentials_timeout_and_keepalive_are_mapped()
    {
        $settings = $this->settings([
            'username' => 'alice',
            'password' => 'secret',
            'timeout' => 7,
            'keepalive' => 30,
        ]);

        $this->assertSame('alice', $settings->getUsername());
        $this->assertSame('secret', $settings->getPassword());
        $this->assertSame(7, $settings->getConnectTimeout());
        $this->assertSame(30, $settings->getKeepAliveInterval());
    }

    public function test_tls_is_off_without_a_ca_file_or_flag()
    {
        $this->assertFalse($this->settings([])->shouldUseTls());
    }

    public function test_a_ca_file_enables_tls_with_the_configured_options()
    {
        $settings = $this->settings([
            'certfile' => '/etc/ssl/ca.crt',
            'localcert' => '/etc/ssl/client.crt',
            'localpk' => '/etc/ssl/client.key',
            'tls' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'passphrase' => 'pw',
            ],
        ]);

        $this->assertTrue($settings->shouldUseTls());
        $this->assertFalse($settings->shouldTlsVerifyPeer());
        $this->assertFalse($settings->shouldTlsVerifyPeerName());
        $this->assertTrue($settings->isTlsSelfSignedAllowed());
        $this->assertSame('/etc/ssl/ca.crt', $settings->getTlsCertificateAuthorityFile());
        $this->assertSame('/etc/ssl/client.crt', $settings->getTlsClientCertificateFile());
        $this->assertSame('/etc/ssl/client.key', $settings->getTlsClientCertificateKeyFile());
        $this->assertSame('pw', $settings->getTlsClientCertificateKeyPassphrase());
    }

    public function test_tls_can_be_enabled_without_a_ca_file()
    {
        $settings = $this->settings(['tls' => ['enabled' => true]]);

        $this->assertTrue($settings->shouldUseTls());
        $this->assertTrue($settings->shouldTlsVerifyPeer());
    }

    public function test_last_will_is_mapped_when_a_topic_is_set()
    {
        $settings = $this->settings(['last_will' => [
            'topic' => 'devices/42/status',
            'message' => 'offline',
            'qos' => 1,
            'retain' => true,
        ]]);

        $this->assertTrue($settings->hasLastWill());
        $this->assertSame('devices/42/status', $settings->getLastWillTopic());
        $this->assertSame('offline', $settings->getLastWillMessage());
        $this->assertSame(1, $settings->getLastWillQualityOfService());
        $this->assertTrue($settings->shouldRetainLastWill());
    }

    public function test_no_last_will_without_a_topic()
    {
        $this->assertFalse($this->settings(['last_will' => ['topic' => null]])->hasLastWill());
    }

    public function test_reconnects_are_only_used_for_subscribe_connections()
    {
        $this->assertFalse($this->settings(['reconnect' => ['enabled' => true]])->shouldReconnectAutomatically());
    }

    public function test_reconnect_settings_are_mapped()
    {
        $settings = $this->settings(['reconnect' => [
            'enabled' => true,
            'max_attempts' => 5,
            'delay' => 250,
        ]], true);

        $this->assertTrue($settings->shouldReconnectAutomatically());
        $this->assertSame(5, $settings->getMaxReconnectAttempts());
        $this->assertSame(250, $settings->getDelayBetweenReconnectAttempts());
    }

    public function test_protocol_defaults_to_3_1_1_and_accepts_3_1()
    {
        $this->assertSame(MqttClient::MQTT_3_1_1, $this->invoke(new Mqtt([]), 'protocol'));
        $this->assertSame(MqttClient::MQTT_3_1, $this->invoke(new Mqtt(['protocol' => '3.1']), 'protocol'));
    }

    public function test_an_unknown_protocol_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mqtt(['protocol' => '5.0']))->ConnectAndPublish('t', 'm');
    }

    public function test_qos_falls_back_to_config()
    {
        $mqtt = new Mqtt(['qos' => 1]);

        $this->assertSame(1, $this->invoke($mqtt, 'resolveQos', [null]));
        $this->assertSame(2, $this->invoke($mqtt, 'resolveQos', [2]));
        $this->assertSame(0, $this->invoke($mqtt, 'resolveQos', ['0']));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidQos')]
    public function test_an_invalid_qos_is_rejected_rather_than_clamped($qos)
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mqtt([]))->ConnectAndPublish('t', 'm', null, null, $qos);
    }

    public static function invalidQos(): array
    {
        return [[3], [-1], ['high'], [1.5], [true]];
    }

    public function test_an_invalid_configured_qos_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mqtt(['qos' => 9]))->ConnectAndPublish('t', 'm');
    }

    public function test_retain_null_uses_the_config_and_an_explicit_value_wins()
    {
        $this->assertTrue($this->invoke(new Mqtt(['retain' => 1]), 'resolveRetain', [null]));
        $this->assertFalse($this->invoke(new Mqtt(['retain' => 0]), 'resolveRetain', [null]));
        $this->assertTrue($this->invoke(new Mqtt(['retain' => 0]), 'resolveRetain', [1]));
        $this->assertFalse($this->invoke(new Mqtt(['retain' => 1]), 'resolveRetain', [0]));
        $this->assertFalse($this->invoke(new Mqtt(['retain' => 1]), 'resolveRetain', [false]));
    }

    public function test_generated_client_ids_are_random_and_fit_mqtt_3_1()
    {
        $ids = [];
        for ($i = 0; $i < 200; $i++) {
            $ids[] = $this->invoke(new Mqtt([]), 'resolveClientId', [null]);
        }

        $this->assertCount(200, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^lmq_[0-9a-f]{16}$/', $id);
            $this->assertLessThanOrEqual(23, strlen($id));
        }

        $this->assertSame('custom', $this->invoke(new Mqtt([]), 'resolveClientId', ['custom']));
        $this->assertSame('0', $this->invoke(new Mqtt([]), 'resolveClientId', ['0']));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTopics')]
    public function test_an_invalid_publish_topic_is_rejected($topic)
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mqtt([]))->ConnectAndPublish($topic, 'm');
    }

    public static function invalidTopics(): array
    {
        return [[''], ['sensors/+/temp'], ['sensors/#'], [null], [['a']]];
    }

    public function test_an_array_payload_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('json_encode');

        (new Mqtt([]))->ConnectAndPublish('t', ['temp' => 21]);
    }

    public function test_subscribing_to_no_topics_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mqtt([]))->ConnectAndSubscribe([], function () {
        });
    }

    public function test_a_throwing_listener_does_not_stop_the_callback()
    {
        Event::listen(MqttMessageReceived::class, function () {
            throw new RuntimeException('listener broke');
        });

        $received = [];
        $this->invoke(new Mqtt([]), 'deliver', [function ($topic, $message) use (&$received) {
            $received[] = [$topic, $message];
        }, 'a/b', 'hello']);

        $this->assertSame([['a/b', 'hello']], $received);
    }

    public function test_a_throwing_callback_is_logged_not_thrown()
    {
        Log::shouldReceive('log')->once()->withArgs(function ($level, $message) {
            return $level === 'error' && str_contains($message, 'callback broke') && str_contains($message, 'a/b');
        });

        $this->invoke(new Mqtt([]), 'deliver', [function () {
            throw new RuntimeException('callback broke');
        }, 'a/b', 'hello']);
    }

    public function test_a_failed_connection_returns_false_by_default()
    {
        // Port 1 has nothing listening, so the connection is refused quickly.
        $mqtt = new Mqtt(['host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]);

        $this->assertFalse($mqtt->ConnectAndPublish('t', 'm'));
    }

    public function test_a_failed_connection_throws_when_exceptions_are_enabled()
    {
        $mqtt = new Mqtt(['host' => '127.0.0.1', 'port' => 1, 'timeout' => 1, 'exceptions' => true]);

        $this->expectException(MqttConnectionException::class);

        $mqtt->ConnectAndPublish('t', 'm');
    }

    public function test_failures_are_logged_even_with_debug_off()
    {
        Log::shouldReceive('log')->atLeast()->once()->withArgs(function ($level, $message) {
            return in_array($level, ['error', 'warning'], true) && str_starts_with($message, 'MQTT [default]');
        });

        (new Mqtt(['host' => '127.0.0.1', 'port' => 1, 'timeout' => 1, 'debug' => false]))->ConnectAndPublish('t', 'm');
    }

    public function test_the_connect_timeout_applies_when_the_broker_never_answers()
    {
        // A listening socket that is never accept()ed: TCP connects, but no
        // CONNACK ever arrives.
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $started = microtime(true);
        $result = (new Mqtt(['host' => '127.0.0.1', 'port' => $port, 'timeout' => 1]))->ConnectAndPublish('t', 'm');
        $elapsed = microtime(true) - $started;

        fclose($server);

        $this->assertFalse($result);
        $this->assertLessThan(3, $elapsed, 'The publish should give up after the 1s timeout.');
    }

    public function test_a_qos_1_publish_gives_up_when_no_puback_arrives()
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires pcntl.');
        }

        // A server that sends CONNACK and then ignores everything.
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $pid = pcntl_fork();
        if ($pid === 0) {
            $conn = stream_socket_accept($server, 5);
            fread($conn, 1024);
            fwrite($conn, "\x20\x02\x00\x00");
            sleep(5);
            exit(0);
        }

        $started = microtime(true);
        $result = (new Mqtt(['host' => '127.0.0.1', 'port' => $port, 'timeout' => 1]))->ConnectAndPublish('t', 'm', null, null, 1);
        $elapsed = microtime(true) - $started;

        posix_kill($pid, SIGKILL);
        pcntl_waitpid($pid, $status);
        fclose($server);

        $this->assertFalse($result);
        $this->assertLessThan(3, $elapsed, 'The publish should give up after the 1s timeout.');
    }

    public function test_interrupt_without_an_active_subscription_is_a_no_op()
    {
        (new Mqtt([]))->interrupt();

        $this->addToAssertionCount(1);
    }

    public function test_new_mqtt_follows_the_configured_default_connection()
    {
        config([
            'mqtt.host' => 'flat.example.com',
            'mqtt.default' => 'second',
            'mqtt.connections.second' => ['host' => 'second.example.com'],
        ]);

        $mqtt = new Mqtt();
        $config = (new \ReflectionProperty($mqtt, 'config'))->getValue($mqtt);

        $this->assertSame('second.example.com', $config['host']);
        $this->assertSame('second', $mqtt->getConnectionName());

        $named = new Mqtt(null, 'second');
        $this->assertSame('second', $named->getConnectionName());
    }

    public function test_publish_connections_never_carry_the_last_will()
    {
        $config = ['last_will' => ['topic' => 'devices/1/status', 'message' => 'offline']];

        $this->assertFalse($this->invoke(new Mqtt($config), 'settings', [false, false])->hasLastWill());
        $this->assertTrue($this->invoke(new Mqtt($config), 'settings', [false, true])->hasLastWill());
    }

    public function test_fractional_timeouts_are_kept()
    {
        $this->assertSame(0.5, $this->invoke(new Mqtt(['timeout' => 0.5]), 'timeout'));
        $this->assertSame(10.0, $this->invoke(new Mqtt(['timeout' => 0]), 'timeout'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFilters')]
    public function test_invalid_topic_filters_are_rejected($filter)
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mqtt([]))->ConnectAndSubscribe($filter, function () {
        });
    }

    public static function invalidFilters(): array
    {
        return [['a/#/b'], ['a/b#'], ['a/+b'], ['#/a'], [''], ["\xff\xfe"]];
    }

    public function test_valid_wildcard_filters_are_accepted()
    {
        $this->assertSame(['#', '+', 'a/+/b', 'a/#', '+/+'], \Salman\Mqtt\Support\Arguments::filters(['#', '+', 'a/+/b', 'a/#', '+/+']));
    }

    public function test_topics_over_65535_bytes_or_invalid_utf8_are_rejected()
    {
        foreach ([str_repeat('a', 65536), "bad\xff"] as $topic) {
            try {
                (new Mqtt([]))->ConnectAndPublish($topic, 'm');
                $this->fail('Expected an InvalidArgumentException.');
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(str_repeat('a', 65535), \Salman\Mqtt\Support\Arguments::topic(str_repeat('a', 65535)));
    }

    public function test_a_non_callable_subscribe_callback_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mqtt([]))->ConnectAndSubscribe('a', 'not a function');
    }

    public function test_a_subscribe_gives_up_when_no_suback_arrives()
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires pcntl.');
        }

        // A server that sends CONNACK and then ignores everything (no SUBACK).
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $pid = pcntl_fork();
        if ($pid === 0) {
            $conn = stream_socket_accept($server, 5);
            fread($conn, 1024);
            fwrite($conn, "\x20\x02\x00\x00");
            sleep(5);
            exit(0);
        }

        $started = microtime(true);
        $result = (new Mqtt(['host' => '127.0.0.1', 'port' => $port, 'timeout' => 1]))->ConnectAndSubscribe('a', function () {
        });
        $elapsed = microtime(true) - $started;

        posix_kill($pid, SIGKILL);
        pcntl_waitpid($pid, $status);
        fclose($server);

        $this->assertFalse($result);
        $this->assertLessThan(3, $elapsed);
    }
}
