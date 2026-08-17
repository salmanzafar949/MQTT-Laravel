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
}
