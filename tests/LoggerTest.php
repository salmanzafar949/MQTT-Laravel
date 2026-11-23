<?php

namespace Salman\Mqtt\Tests;

use Illuminate\Support\Facades\Log;
use Salman\Mqtt\MqttClass\Logger;

class LoggerTest extends TestCase
{
    public function test_debug_and_info_are_dropped_when_debug_is_off()
    {
        Log::shouldReceive('log')->never();

        $logger = new Logger('default', false);
        $logger->debug('packet');
        $logger->info('connected');
    }

    public function test_warnings_and_errors_are_kept_when_debug_is_off()
    {
        Log::shouldReceive('log')->once()->with('warning', 'MQTT [sensors] slow', []);
        Log::shouldReceive('log')->once()->with('error', 'MQTT [sensors] failed', []);

        $logger = new Logger('sensors', false);
        $logger->warning('slow');
        $logger->error('failed');
    }

    public function test_everything_is_kept_when_debug_is_on()
    {
        Log::shouldReceive('log')->once()->with('debug', 'MQTT [default] packet', []);

        (new Logger('default', true))->debug('packet');
    }

    public function test_placeholders_are_filled_and_the_password_is_masked_in_binary_data()
    {
        Log::shouldReceive('log')->once()->withArgs(function ($level, $message) use (&$logged) {
            $logged = $message;

            return true;
        });

        // A CONNECT packet carries the password as raw bytes.
        $packet = "\x10\x1f\x00\x04MQTT\x04\xc2\x00\x0a\x00\x03abc\x00\x05alice\x00\x06s3cret";

        (new Logger('default', true, ['s3cret', null]))->debug('Sent data: {data}', ['data' => $packet]);

        $this->assertStringNotContainsString('s3cret', $logged);
        $this->assertStringContainsString('******', $logged);
        $this->assertStringContainsString('alice', $logged);
        $this->assertStringContainsString('\x10', $logged);
        $this->assertSame(1, preg_match('//u', $logged));
    }

    public function test_exceptions_are_passed_through_as_context()
    {
        $e = new \RuntimeException('boom');

        Log::shouldReceive('log')->once()->with('error', 'MQTT [default] it failed on [a/b]', ['exception' => $e]);

        (new Logger())->error('it failed on [{topic}]', ['topic' => 'a/b', 'exception' => $e]);
    }

    public function test_newlines_in_values_cannot_forge_log_lines()
    {
        Log::shouldReceive('log')->once()->withArgs(function ($level, $message) use (&$logged) {
            $logged = $message;

            return true;
        });

        (new Logger('default', true))->debug('Received: {message}', ['message' => "ok\n[2026-01-01 00:00:00] local.EMERGENCY: forged"]);

        $this->assertStringNotContainsString("\n", $logged);
        $this->assertStringContainsString('\x0A', $logged);
    }

    public function test_a_short_password_is_masked_without_mangling_the_dump()
    {
        Log::shouldReceive('log')->once()->withArgs(function ($level, $message) use (&$logged) {
            $logged = $message;

            return true;
        });

        $packet = "\x10\x1a\x00\x04MQTT\x04\xc2\x00\x0a\x00\x03xyz\x00\x05alice\x00\x01x";
        (new Logger('default', true, ['x']))->debug('Sent data: {data}', ['data' => $packet]);

        $this->assertStringContainsString('\x10', $logged);
        $this->assertStringContainsString('xyz', $logged);
        $this->assertStringContainsString('alice\x00\x06******', $logged);
    }
}
