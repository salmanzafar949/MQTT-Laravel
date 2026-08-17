# Laravel MQTT

[![Latest Version on Packagist](https://img.shields.io/packagist/v/salmanzafar/laravel-mqtt.svg)](https://packagist.org/packages/salmanzafar/laravel-mqtt)
[![Total Downloads](https://img.shields.io/packagist/dt/salmanzafar/laravel-mqtt.svg)](https://packagist.org/packages/salmanzafar/laravel-mqtt)
[![Tests](https://github.com/salmanzafar949/MQTT-Laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/salmanzafar949/MQTT-Laravel/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/salmanzafar/laravel-mqtt.svg)](LICENSE)

A simple Laravel library to connect, publish and subscribe to an MQTT broker.

Based on [bluerhinos/phpMQTT](https://github.com/bluerhinos/phpMQTT). A working
example application is available in the
[Laravel-Mqtt-Example](https://github.com/salmanzafar949/Laravel-Mqtt-Example) repo.

## Table of contents

- [Compatibility](#compatibility)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Publishing](#publishing)
  - [Subscribing](#subscribing)
  - [Multiple topics](#subscribing-to-multiple-topics)
  - [Helper functions](#helper-functions)
- [Artisan commands](#artisan-commands)
- [Multiple connections](#multiple-connections)
- [Events](#events)
- [Notifications](#notifications)
- [TLS / SSL](#tls--ssl)
- [Error handling](#error-handling)
- [Testing your app](#testing-your-app)
- [Available methods](#available-methods)
- [Testing & quality](#testing--quality)
- [Roadmap](#roadmap)
- [Releasing](#releasing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)

## Compatibility

| PHP       | Laravel     |
|-----------|-------------|
| 7.2 – 8.4 | 5.5 – 12.x  |

The package uses Laravel's package auto-discovery, so it works out of the box
across all of the above versions.

## Installation

```bash
composer require salmanzafar/laravel-mqtt
```

The service provider and `Mqtt` facade are registered automatically on
Laravel 5.5+. **Only** if you are on Laravel < 5.5, register them manually in
`config/app.php`:

```php
'providers' => [
    Salman\Mqtt\MqttServiceProvider::class,
],

'aliases' => [
    'Mqtt' => Salman\Mqtt\Facades\Mqtt::class,
],
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Salman\Mqtt\MqttServiceProvider"
```

This creates `config/mqtt.php`:

```php
return [
    'host'       => env('MQTT_HOST', '127.0.0.1'),
    'password'   => env('MQTT_PASSWORD', ''),
    'username'   => env('MQTT_USERNAME', ''),
    'port'       => env('MQTT_PORT', '1883'),
    'timeout'    => (int) env('MQTT_TIMEOUT', 10),
    'keepalive'  => (int) env('MQTT_KEEPALIVE', 10),
    'debug'      => (bool) env('MQTT_DEBUG', false),
    'qos'        => env('MQTT_QOS', 0),
    'retain'     => env('MQTT_RETAIN', 0),
    'exceptions' => (bool) env('MQTT_EXCEPTIONS', false),

    // TLS / SSL
    'certfile'   => env('MQTT_CERT_FILE', ''),
    'localcert'  => env('MQTT_LOCAL_CERT', ''),
    'localpk'    => env('MQTT_LOCAL_PK', ''),
    'tls' => [
        'verify_peer'       => (bool) env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name'  => (bool) env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'allow_self_signed' => (bool) env('MQTT_TLS_ALLOW_SELF_SIGNED', false),
        'ciphers'           => env('MQTT_TLS_CIPHERS', null),
        'passphrase'        => env('MQTT_TLS_PASSPHRASE', null),
    ],
];
```

| Key          | Env var                | Default     | Description                                              |
|--------------|------------------------|-------------|----------------------------------------------------------|
| `host`       | `MQTT_HOST`            | `127.0.0.1` | Broker host.                                             |
| `port`       | `MQTT_PORT`            | `1883`      | Broker port (`8883` for TLS).                           |
| `username`   | `MQTT_USERNAME`        | `''`        | Username, if the broker requires authentication.        |
| `password`   | `MQTT_PASSWORD`        | `''`        | Password, if the broker requires authentication.        |
| `timeout`    | `MQTT_TIMEOUT`         | `10`        | Connection timeout in seconds.                          |
| `keepalive`  | `MQTT_KEEPALIVE`       | `10`        | Seconds between keep-alive pings.                       |
| `debug`      | `MQTT_DEBUG`           | `false`     | Enable debug logging.                                   |
| `qos`        | `MQTT_QOS`             | `0`         | Quality of Service level.                               |
| `retain`     | `MQTT_RETAIN`          | `0`         | Retain flag (`0` or `1`).                               |
| `exceptions` | `MQTT_EXCEPTIONS`      | `false`     | Throw on failure instead of returning `false`.          |
| `tls`        | `MQTT_TLS_*`           | see above   | SSL stream-context options used when a CA file is set.  |

When running inside Laravel, debug and error messages are written through the
framework logger.

## Usage

### Publishing

Using the class directly:

```php
use Salman\Mqtt\MqttClass\Mqtt;

public function sendMessage(string $topic, string $message)
{
    $mqtt      = new Mqtt();
    $clientId  = optional(auth()->user())->id;

    $published = $mqtt->ConnectAndPublish($topic, $message, $clientId);

    return $published ? 'published' : 'failed';
}
```

Using the facade:

```php
use Mqtt; // or: use Salman\Mqtt\Facades\Mqtt;

$published = Mqtt::ConnectAndPublish($topic, $message, $clientId);
```

> `$client_id` and `$retain` are optional: `ConnectAndPublish($topic, $message)`
> works too. When no client id is given, a unique one is generated for you.

### Subscribing

```php
use Salman\Mqtt\MqttClass\Mqtt;

public function subscribe(string $topic)
{
    $mqtt = new Mqtt();

    $mqtt->ConnectAndSubscribe($topic, function ($topic, $message) {
        echo "Message received on {$topic}: {$message}\n";
    });
}
```

Or via the facade:

```php
Mqtt::ConnectAndSubscribe($topic, function ($topic, $message) {
    logger()->info("MQTT message on {$topic}", ['message' => $message]);
});
```

> Subscribing blocks the process while it listens, so run it from an Artisan
> command / queue worker rather than an HTTP request.

### Subscribing to multiple topics

Pass an array of topics to listen to several at once:

```php
Mqtt::ConnectAndSubscribe(['sensors/temperature', 'sensors/humidity'], function ($topic, $message) {
    echo "{$topic} => {$message}\n";
});
```

### Helper functions

Two convenience helpers are also available:

```php
// Publish
connectToPublish($topic, $message, $clientId = null, $retain = null);

// Subscribe (echoes received messages)
connectToSubscribe($topic, $clientId = null);
```

## Artisan commands

Publish or subscribe straight from the CLI — no need to hand-write a console
command:

```bash
# Publish a message
php artisan mqtt:publish home/light on
php artisan mqtt:publish home/light on --retain --connection=sensors

# Subscribe to one or more topics (Ctrl+C to stop)
php artisan mqtt:subscribe home/light
php artisan mqtt:subscribe "sensors/#" "home/+/status" --connection=sensors
```

## Multiple connections

Talk to more than one broker by defining extra connections in `config/mqtt.php`.
The top-level settings are the `default` connection; each named connection
inherits them and overrides only what it needs:

```php
'connections' => [
    'sensors' => [
        'host' => env('MQTT_SENSORS_HOST', 'broker.example.com'),
        'port' => env('MQTT_SENSORS_PORT', '8883'),
    ],
],
```

```php
Mqtt::connection('sensors')->ConnectAndPublish('sensors/temp', '21.5');
Mqtt::ConnectAndPublish('home/light', 'on'); // default connection
```

## Events

Every received message dispatches a `Salman\Mqtt\Events\MqttMessageReceived`
event (in addition to your subscription callback), so you can handle messages
with a listener:

```php
use Salman\Mqtt\Events\MqttMessageReceived;

Event::listen(function (MqttMessageReceived $event) {
    logger()->info("[{$event->connection}] {$event->topic}: {$event->message}");
});
```

## Notifications

Send Laravel notifications over MQTT. Add `'mqtt'` to `via()` and return a
payload (or an `MqttMessage`) from `toMqtt()`:

```php
use Illuminate\Notifications\Notification;
use Salman\Mqtt\Notifications\MqttMessage;

class DeviceOffline extends Notification
{
    public function via($notifiable)
    {
        return ['mqtt'];
    }

    public function toMqtt($notifiable)
    {
        return MqttMessage::create('offline')
            ->topic("devices/{$notifiable->id}/status")
            ->retain();
    }
}
```

The topic can come from the `MqttMessage` or from a
`routeNotificationForMqtt()` method on the notifiable.

## TLS / SSL

Provide a CA file (and optionally a client certificate) to connect over
`tls://`. Set the broker port to `8883` and configure the certificate paths:

```dotenv
MQTT_PORT=8883
MQTT_CERT_FILE=/path/to/ca.crt
MQTT_LOCAL_CERT=/path/to/client.crt
MQTT_LOCAL_PK=/path/to/client.key
```

Fine-tune verification through the `mqtt.tls` options (for example set
`MQTT_TLS_ALLOW_SELF_SIGNED=true` for a self-signed broker in development). Keep
the `verify_peer` options enabled in production.

## Error handling

By default the connection methods return `false` on failure. If you prefer
exceptions, enable them:

```dotenv
MQTT_EXCEPTIONS=true
```

```php
use Salman\Mqtt\Exceptions\MqttConnectionException;

try {
    Mqtt::ConnectAndPublish($topic, $message);
} catch (MqttConnectionException $e) {
    report($e);
}
```

## Testing your app

Use `Mqtt::fake()` to swap the real client for a recorder and assert on what
your application published — no broker required:

```php
use Salman\Mqtt\Facades\Mqtt;

public function test_it_publishes_a_reading()
{
    Mqtt::fake();

    // ... code under test calls Mqtt::ConnectAndPublish('sensors/temp', '21.5') ...

    Mqtt::assertPublished('sensors/temp');
    Mqtt::assertPublished('sensors/temp', '21.5');
    Mqtt::assertPublished('sensors/temp', fn ($payload) => (float) $payload > 20);
    Mqtt::assertPublishedCount(1);
    Mqtt::assertNotPublished('sensors/humidity');
}
```

## Available methods

| Method | Returns | Description |
|--------|---------|-------------|
| `ConnectAndPublish(string $topic, string $message, string\|int $clientId = null, int $retain = null)` | `bool` | Connect, publish a message and disconnect. |
| `ConnectAndSubscribe(string\|array $topic, callable $callback, string\|int $clientId = null)` | `bool` | Connect and listen for messages on one or more topics. |
| `connection(string $name = null)` | `Mqtt` | Get a specific broker connection. |
| `fake()` | `MqttFake` | Swap in a test double that records published messages. |

> PHP method names are case-insensitive, so `Mqtt::connectAndPublish(...)` and
> `Mqtt::connectAndSubscribe(...)` work as well.

## Testing & quality

```bash
composer install
composer test        # PHPUnit
```

Code style (Laravel Pint) and static analysis (PHPStan) are also available:

```bash
composer lint        # check code style
composer lint:fix    # apply code-style fixes
composer analyse     # run PHPStan
```

The Pint and PHPStan binaries are installed on demand by the `quality` CI
workflow; to run them locally add them once with
`composer require --dev laravel/pint larastan/larastan`.

## Roadmap

The wire protocol is still the legacy phpMQTT 3.1 implementation. The next major
version (v4) will move it onto [`php-mqtt/client`](https://github.com/php-mqtt/client)
for **MQTT 3.1.1 / 5.0**, real **QoS 1/2**, **Last Will & Testament** and
**message expiry**, while keeping the Laravel-facing API. See the
[v4 migration plan](docs/UPGRADING-v4.md).

## Releasing

Releases are published to
[Packagist](https://packagist.org/packages/salmanzafar/laravel-mqtt)
automatically. Packagist is connected to this repository via the Packagist
GitHub App, and a GitHub Actions workflow tags releases from the `version`
field in `composer.json`:

1. Bump `"version"` in `composer.json` (and update `CHANGELOG.md`) in your pull request.
2. Merge the pull request into `master`.
3. The `release` workflow runs the test suite, creates the matching `vX.Y.Z`
   tag and GitHub Release, and Packagist publishes the new version.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Contributing

Contributions are welcome — please read [CONTRIBUTING.md](CONTRIBUTING.md) first.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
