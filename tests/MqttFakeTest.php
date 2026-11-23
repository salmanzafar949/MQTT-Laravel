<?php

namespace Salman\Mqtt\Tests;

use PHPUnit\Framework\AssertionFailedError;
use Salman\Mqtt\Facades\Mqtt;
use Salman\Mqtt\Testing\MqttFake;

class MqttFakeTest extends TestCase
{
    public function test_fake_returns_a_fake_and_records_published_messages()
    {
        $fake = Mqtt::fake();

        $this->assertInstanceOf(MqttFake::class, $fake);

        Mqtt::ConnectAndPublish('home/light', 'on');

        Mqtt::assertPublished('home/light');
        Mqtt::assertPublished('home/light', 'on');
        Mqtt::assertPublishedCount(1);
    }

    public function test_assert_published_supports_a_matching_callback()
    {
        Mqtt::fake();

        Mqtt::ConnectAndPublish('sensors/temp', '21.5');

        Mqtt::assertPublished('sensors/temp', function ($payload) {
            return (float) $payload > 20;
        });
    }

    public function test_assert_not_published_and_nothing_published()
    {
        Mqtt::fake();

        Mqtt::assertNothingPublished();
        Mqtt::assertNotPublished('home/light');

        Mqtt::ConnectAndPublish('home/light', 'on');

        Mqtt::assertNotPublished('home/door');
    }

    public function test_assert_published_fails_when_nothing_matches()
    {
        Mqtt::fake();

        $this->expectException(AssertionFailedError::class);

        Mqtt::assertPublished('never/sent');
    }

    public function test_the_fake_also_catches_helpers_and_directly_built_clients()
    {
        $fake = Mqtt::fake();

        connectToPublish('helper/topic', 'from helper');
        (new \Salman\Mqtt\MqttClass\Mqtt())->ConnectAndPublish('direct/topic', 'direct');

        Mqtt::assertPublished('helper/topic', 'from helper');
        Mqtt::assertPublished('direct/topic', 'direct');
        $this->assertCount(2, $fake->published());
    }

    public function test_a_payload_that_names_a_function_is_compared_as_a_string()
    {
        Mqtt::fake();
        Mqtt::ConnectAndPublish('t', 'on');

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        Mqtt::assertPublished('t', 'date');
    }

    public function test_a_payload_mismatch_lists_what_was_published()
    {
        Mqtt::fake();
        Mqtt::ConnectAndPublish('t', 'on');

        try {
            Mqtt::assertPublished('t', 'off');
            $this->fail('The assertion should have failed.');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->assertStringContainsString("'on'", $e->getMessage());
        }
    }

    public function test_the_fake_casts_payloads_and_validates_like_the_real_client()
    {
        $fake = Mqtt::fake();
        Mqtt::ConnectAndPublish('t', 42);

        $this->assertSame('42', $fake->published()[0]['message']);
        Mqtt::assertPublished('t', '42');

        $this->expectException(\InvalidArgumentException::class);
        Mqtt::ConnectAndPublish('', 'm');
    }

    public function test_assert_subscribed()
    {
        Mqtt::fake();
        Mqtt::ConnectAndSubscribe(['a/+', 'b/#'], function () {
        });

        Mqtt::assertSubscribed('b/#');
    }

    public function test_the_fake_rejects_unknown_connection_names()
    {
        Mqtt::fake();

        $this->expectException(\InvalidArgumentException::class);

        Mqtt::connection('typo')->ConnectAndPublish('t', 'm');
    }

    public function test_assertions_without_fake_explain_what_to_do()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Mqtt::fake()');

        Mqtt::assertPublished('t');
    }

    public function test_the_fake_records_the_default_connection_name_for_new_mqtt()
    {
        config(['mqtt.default' => 'second', 'mqtt.connections.second' => ['host' => 'second.local']]);
        $fake = Mqtt::fake();

        connectToPublish('t', 'm');

        $this->assertSame('second', $fake->published()[0]['connection']);
    }
}
