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

    public function test_the_publish_command_can_clear_the_retain_flag()
    {
        config(['mqtt.retain' => 1]);
        $fake = Mqtt::fake();

        $this->artisan('mqtt:publish', [
            'topic' => 'home/light',
            'message' => 'on',
            '--no-retain' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, $fake->published()[0]['retain']);
    }

    public function test_the_publish_command_rejects_bad_input_with_exit_code_1()
    {
        Mqtt::fake();

        $this->artisan('mqtt:publish', ['topic' => '', 'message' => 'on'])->assertExitCode(1);
        $this->artisan('mqtt:publish', ['topic' => 'a/#', 'message' => 'on'])->assertExitCode(1);
        $this->artisan('mqtt:publish', ['topic' => 'a', 'message' => 'on', '--qos' => '5'])->assertExitCode(1);

        Mqtt::assertNothingPublished();
    }

    public function test_the_publish_command_rejects_an_unknown_connection()
    {
        $this->artisan('mqtt:publish', ['topic' => 'a', 'message' => 'on', '--connection' => 'nope'])
            ->expectsOutputToContain('[nope] is not configured')
            ->assertExitCode(1);
    }

    public function test_the_publish_command_reports_a_broker_failure()
    {
        config(['mqtt.host' => '127.0.0.1', 'mqtt.port' => 1, 'mqtt.timeout' => 1]);

        $this->artisan('mqtt:publish', ['topic' => 'a', 'message' => 'on'])
            ->expectsOutputToContain('Failed to publish')
            ->assertExitCode(1);
    }

    public function test_the_subscribe_command_rejects_an_unknown_connection()
    {
        $this->artisan('mqtt:subscribe', ['topic' => ['a'], '--connection' => 'nope'])->assertExitCode(1);
    }
}
