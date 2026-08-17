<?php

namespace Salman\Mqtt\Tests;

use Salman\Mqtt\Facades\Mqtt;

class ConsoleCommandTest extends TestCase
{
    public function test_the_publish_command_publishes_via_the_client()
    {
        Mqtt::fake();

        $this->artisan('mqtt:publish', [
            'topic' => 'home/light',
            'message' => 'on',
        ])->assertExitCode(0);

        Mqtt::assertPublished('home/light', 'on');
    }

    public function test_the_publish_command_sets_the_retain_flag()
    {
        $fake = Mqtt::fake();

        $this->artisan('mqtt:publish', [
            'topic' => 'home/light',
            'message' => 'on',
            '--retain' => true,
        ])->assertExitCode(0);

        $published = $fake->published();
        $this->assertSame(1, $published[0]['retain']);
    }

    public function test_the_subscribe_command_runs()
    {
        Mqtt::fake();

        $this->artisan('mqtt:subscribe', [
            'topic' => ['home/light', 'home/door'],
        ])->assertExitCode(0);
    }
}
