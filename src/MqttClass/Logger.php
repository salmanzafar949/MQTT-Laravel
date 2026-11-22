<?php

namespace Salman\Mqtt\MqttClass;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger handed to php-mqtt/client.
 *
 * Warnings and errors are always forwarded to the Laravel logger (or
 * error_log() outside Laravel); every other level only when `debug` is on.
 * Placeholders are filled in here, binary packet bytes are escaped and
 * configured secrets (the password) are masked.
 *
 * @internal
 */
class Logger extends AbstractLogger
{
    /** @var array<string, int> */
    public const LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    /** @var string */
    protected $connection;

    /** @var bool */
    protected $debug;

    /** @var array<int, string> */
    protected $secrets;

    /**
     * @param  string  $connection
     * @param  bool  $debug
     * @param  array<int, string|null>  $secrets  values to mask in log output
     */
    public function __construct($connection = 'default', $debug = false, array $secrets = [])
    {
        $this->connection = (string) $connection;
        $this->debug = (bool) $debug;
        $this->secrets = array_values(array_filter(array_map('strval', $secrets), 'strlen'));
    }

    /**
     * @param  mixed  $level
     * @param  string|Stringable  $message
     * @param  array<string, mixed>  $context
     */
    public function log($level, $message, array $context = []): void
    {
        $level = is_string($level) && isset(self::LEVELS[$level]) ? $level : 'debug';

        if (! $this->debug && self::LEVELS[$level] > self::LEVELS['warning']) {
            return;
        }

        // php-mqtt/client already prefixes its lines with "MQTT [host:port] [client id]".
        $message = (string) $message;
        $message = strncmp($message, 'MQTT [', 6) === 0 ? substr($message, 5) : $message;
        $text = 'MQTT ['.$this->connection.'] '.$this->interpolate($message, $context);
        $extra = isset($context['exception']) && $context['exception'] instanceof \Throwable
            ? ['exception' => $context['exception']]
            : [];

        if (function_exists('app') && app()->bound('log')) {
            app('log')->log($level, $text, $extra);

            return;
        }

        error_log($text);
    }

    /**
     * @param  string  $message
     * @param  array<string, mixed>  $context
     * @return string
     */
    protected function interpolate($message, array $context)
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if ($key !== 'exception') {
                $replace['{'.$key.'}'] = $this->stringify($value);
            }
        }

        // Values are redacted and escaped individually (below), the template is
        // escaped too, so nothing (e.g. a payload with a newline) can forge a log line.
        return strtr($this->printable($message), $replace);
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    protected function stringify($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value instanceof Stringable || (is_object($value) && method_exists($value, '__toString'))) {
            return $this->printable($this->redact((string) $value));
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            return $this->printable($this->redact($json === false ? '[array]' : $json));
        }

        return '['.get_debug_type($value).']';
    }

    /**
     * Mask configured secrets in a raw value, before escaping. In a packet the
     * password is preceded by its 2-byte length, so that form is matched
     * exactly and a short password can't mangle the rest of the dump; the
     * bare value is only masked when it's long enough to be unambiguous.
     *
     * @param  string  $text
     * @return string
     */
    protected function redact($text)
    {
        foreach ($this->secrets as $secret) {
            $text = str_replace(pack('n', strlen($secret)).$secret, pack('n', 6).'******', $text);

            if (strlen($secret) >= 8) {
                $text = str_replace($secret, '******', $text);
            }
        }

        return $text;
    }

    /**
     * Escape control characters and invalid UTF-8 so packet dumps stay readable.
     *
     * @param  string  $text
     * @return string
     */
    protected function printable($text)
    {
        if (preg_match('//u', $text) !== 1) {
            return (string) preg_replace_callback('/[^\x20-\x7E]/', function ($m) {
                return sprintf('\x%02X', ord($m[0]));
            }, $text);
        }

        return (string) preg_replace_callback('/[\x00-\x1F\x7F]/', function ($m) {
            return sprintf('\x%02X', ord($m[0]));
        }, $text);
    }
}
