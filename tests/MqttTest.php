<?php

namespace Salman\Mqtt\Tests;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use ReflectionMethod;
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

    private function settings(array $config): ConnectionSettings
    {
        return $this->invoke(new Mqtt($config), 'settings');
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

    public function test_reconnect_settings_are_mapped()
    {
        $settings = $this->settings(['reconnect' => [
            'enabled' => true,
            'max_attempts' => 5,
            'delay' => 250,
        ]]);

        $this->assertTrue($settings->shouldReconnectAutomatically());
        $this->assertSame(5, $settings->getMaxReconnectAttempts());
        $this->assertSame(250, $settings->getDelayBetweenReconnectAttempts());
    }

    public function test_protocol_defaults_to_3_1_1_and_accepts_3_1()
    {
        $this->assertSame(MqttClient::MQTT_3_1_1, $this->invoke(new Mqtt([]), 'protocol'));
        $this->assertSame(MqttClient::MQTT_3_1, $this->invoke(new Mqtt(['protocol' => '3.1']), 'protocol'));
        $this->assertSame(MqttClient::MQTT_3_1_1, $this->invoke(new Mqtt(['protocol' => 'bogus']), 'protocol'));
    }

    public function test_qos_falls_back_to_config_and_is_clamped()
    {
        $mqtt = new Mqtt(['qos' => 1]);

        $this->assertSame(1, $this->invoke($mqtt, 'resolveQos', [null]));
        $this->assertSame(2, $this->invoke($mqtt, 'resolveQos', [2]));
        $this->assertSame(2, $this->invoke($mqtt, 'resolveQos', [9]));
        $this->assertSame(0, $this->invoke($mqtt, 'resolveQos', [-1]));
    }

    public function test_retain_falls_back_to_config()
    {
        $this->assertTrue($this->invoke(new Mqtt(['retain' => 1]), 'resolveRetain', [null]));
        $this->assertFalse($this->invoke(new Mqtt(['retain' => 0]), 'resolveRetain', [null]));
        $this->assertTrue($this->invoke(new Mqtt(['retain' => 0]), 'resolveRetain', [1]));
    }

    public function test_a_client_id_is_generated_when_missing()
    {
        $id = $this->invoke(new Mqtt([]), 'resolveClientId', [null]);

        $this->assertStringStartsWith('lmq_', $id);
        $this->assertSame('custom', $this->invoke(new Mqtt([]), 'resolveClientId', ['custom']));
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

    public function test_interrupt_without_an_active_subscription_is_a_no_op()
    {
        (new Mqtt([]))->interrupt();

        $this->addToAssertionCount(1);
    }
}
