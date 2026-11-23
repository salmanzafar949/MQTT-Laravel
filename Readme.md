# Laravel MQTT

[![Latest Version on Packagist](https://img.shields.io/packagist/v/salmanzafar/laravel-mqtt.svg)](https://packagist.org/packages/salmanzafar/laravel-mqtt)
[![Total Downloads](https://img.shields.io/packagist/dt/salmanzafar/laravel-mqtt.svg)](https://packagist.org/packages/salmanzafar/laravel-mqtt)
[![Tests](https://github.com/salmanzafar949/MQTT-Laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/salmanzafar949/MQTT-Laravel/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/salmanzafar/laravel-mqtt.svg)](LICENSE)

A simple Laravel library to connect, publish and subscribe to an MQTT broker.

Built on [php-mqtt/client](https://github.com/php-mqtt/client) (MQTT 3.1.1 and
3.1, real QoS 0/1/2, Last Will, automatic reconnects). A working example
application is available in the
[Laravel-Mqtt-Example](https://github.com/salmanzafar949/Laravel-Mqtt-Example) repo.

> **Upgrading from v3?** See [docs/UPGRADING-v4.md](docs/UPGRADING-v4.md). v3.x is
> still available unchanged for PHP 7.2+.

## Table of contents

- [Compatibility](#compatibility)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Publishing](#publishing)
  - [Subscribing](#subscribing)
  - [Multiple topics](#subscribing-to-multiple-topics)
  - [Helper functions](#helper-functions)
- [Quality of Service](#quality-of-service)
- [Last Will and reconnects](#last-will-and-reconnects)
- [Queued publishing](#queued-publishing)
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

| Package | PHP       | Laravel    | Protocol engine                       |
|---------|-----------|------------|---------------------------------------|
| **4.x** | 8.0+      | 12 – 13 (tested) | `php-mqtt/client` (MQTT 3.1.1 / 3.1)  |
| 3.x     | 7.2 – 8.4 | 5.5 – 12.x | built-in legacy client (MQTT 3.1)     |

v4 is tested in CI on Laravel 12 and 13 with PHP 8.2 – 8.5. It doesn't depend
on a specific Laravel version, so older releases may work, but Laravel 9 – 11 are
end-of-life with unpatched security advisories and aren't tested.

The package uses Laravel's package auto-discovery, so it works out of the box.
v3.x stays published unchanged; Composer keeps you on it as long as your
constraint is `^3.0`.

## Installation

```bash
composer require salmanzafar/laravel-mqtt
```

The service provider and `Mqtt` facade are registered automatically through
Laravel's package auto-discovery.

On PHP 7.x, stay on the 3.x line instead:

```bash
composer require salmanzafar/laravel-mqtt:^3.0
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
    'timeout'    => (float) env('MQTT_TIMEOUT', 10),
    'keepalive'  => (int) env('MQTT_KEEPALIVE', 10),
    'protocol'   => env('MQTT_PROTOCOL', '3.1.1'),
    'debug'      => (bool) env('MQTT_DEBUG', false),
    'qos'        => (int) env('MQTT_QOS', 0),
    'retain'     => (int) env('MQTT_RETAIN', 0),
    'clean_session' => (bool) env('MQTT_CLEAN_SESSION', true),
    'exceptions' => (bool) env('MQTT_EXCEPTIONS', false),

    'last_will' => [
        'topic'   => env('MQTT_LAST_WILL_TOPIC', null),
        'message' => env('MQTT_LAST_WILL_MESSAGE', ''),
        'qos'     => (int) env('MQTT_LAST_WILL_QOS', 0),
        'retain'  => (bool) env('MQTT_LAST_WILL_RETAIN', false),
    ],

    'reconnect' => [
        'enabled'      => (bool) env('MQTT_RECONNECT', false),
        'max_attempts' => (int) env('MQTT_RECONNECT_MAX_ATTEMPTS', 3),
        'delay'        => (int) env('MQTT_RECONNECT_DELAY', 1000), // ms
    ],

    // TLS / SSL
    'certfile'   => env('MQTT_CERT_FILE', ''),
    'localcert'  => env('MQTT_LOCAL_CERT', ''),
    'localpk'    => env('MQTT_LOCAL_PK', ''),
    'tls' => [
        'enabled'           => (bool) env('MQTT_TLS', false),
        'verify_peer'       => (bool) env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name'  => (bool) env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'allow_self_signed' => (bool) env('MQTT_TLS_ALLOW_SELF_SIGNED', false),
        'passphrase'        => env('MQTT_TLS_PASSPHRASE', null),
    ],

    // Queued publishing (see "Queued publishing")
    'queue' => [
        'connection' => env('MQTT_QUEUE_CONNECTION', null),
        'name'       => env('MQTT_QUEUE', null),
    ],

    // Named connections (see "Multiple connections")
    'default'     => env('MQTT_CONNECTION', 'default'),
    'connections' => [],
];
```

If you published the config from an older version, new keys are filled in
from the package defaults automatically, nested ones (like `tls.enabled`)
included.

| Key          | Env var                | Default     | Description                                              |
|--------------|------------------------|-------------|----------------------------------------------------------|
| `host`       | `MQTT_HOST`            | `127.0.0.1` | Broker host.                                             |
| `port`       | `MQTT_PORT`            | `1883`      | Broker port (`8883` for TLS).                           |
| `username`   | `MQTT_USERNAME`        | `''`        | Username, if the broker requires authentication.        |
| `password`   | `MQTT_PASSWORD`        | `''`        | Password, if the broker requires authentication.        |
| `timeout`    | `MQTT_TIMEOUT`         | `10`        | Seconds to wait for the broker: to connect, and for QoS 1/2 and subscribe acknowledgements. Fractions such as `0.5` work. |
| `keepalive`  | `MQTT_KEEPALIVE`       | `10`        | Seconds between keep-alive pings.                       |
| `protocol`   | `MQTT_PROTOCOL`        | `3.1.1`     | MQTT version: `3.1.1` or `3.1`.                         |
| `debug`      | `MQTT_DEBUG`           | `false`     | Log every MQTT packet through the Laravel logger.       |
| `qos`        | `MQTT_QOS`             | `0`         | Default Quality of Service level (0, 1 or 2).           |
| `retain`     | `MQTT_RETAIN`          | `0`         | Default retain flag (`0` or `1`), used when a call passes `null`. |
| `clean_session` | `MQTT_CLEAN_SESSION` | `true`     | For subscribers with a fixed client id: `false` resumes the broker session. Publishes always use a clean session. |
| `exceptions` | `MQTT_EXCEPTIONS`      | `false`     | Throw on failure instead of returning `false`.          |
| `last_will`  | `MQTT_LAST_WILL_*`     | off         | Message the broker publishes if a subscriber drops.     |
| `reconnect`  | `MQTT_RECONNECT*`      | off         | Reconnect automatically when the connection drops.      |
| `tls`        | `MQTT_TLS*`            | see above   | TLS options, used when a CA file is set or `tls.enabled`. |
| `queue`      | `MQTT_QUEUE_CONNECTION`, `MQTT_QUEUE` | `null` | Queue connection / name for `Mqtt::queue()` jobs. |
| `default`    | `MQTT_CONNECTION`      | `default`   | Connection used when none is named (see below).         |
| `connections`| —                      | `[]`        | Additional named broker connections.                    |

Warnings and errors (failed connections, timeouts, exceptions thrown by your
subscribe callback) are always written through the framework logger; `debug`
adds a log line for every packet. The password is masked in log output.

## Usage

### Publishing

Using the class directly:

```php
use Salman\Mqtt\MqttClass\Mqtt;

public function sendMessage(string $topic, string $message)
{
    $mqtt      = new Mqtt();

    $published = $mqtt->ConnectAndPublish($topic, $message);

    return $published ? 'published' : 'failed';
}
```

Using the facade:

```php
use Mqtt; // or: use Salman\Mqtt\Facades\Mqtt;

$published = Mqtt::ConnectAndPublish($topic, $message);
```

> `$clientId`, `$retain` and `$qos` are optional. Without a client id a random
> one is generated. Pass one only when you need a stable identity (for example
> a persistent session), and make it unique per process: the broker
> disconnects an existing client when another one connects with the same id.
> `$retain = null` uses the configured default; `0` / `false` turns it off.
>
> Topics must be non-empty and can't contain the `+` / `#` wildcards, and the
> payload must be a string (or a number / `Stringable`). Encode arrays yourself,
> e.g. with `json_encode()`. Invalid arguments throw an `InvalidArgumentException`.

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
> command / queue worker rather than an HTTP request. Call `Mqtt::interrupt()`
> (for example from a signal handler or from inside the callback) to stop the
> loop and disconnect cleanly. It stops every subscribe loop in the process,
> however it was started (facade, `new Mqtt()` or `connectToSubscribe()`), and
> also ends a reconnect backoff right away.
> `ConnectAndSubscribe()` returns `true` after an interrupt and `false` if the
> connection fails or is lost. An exception thrown by your callback is logged
> and doesn't stop the loop.

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

## Quality of Service

QoS 0, 1 and 2 are supported. Set a connection default with `qos`, or pass it
per call as the last argument. At QoS 1 and 2 the publish waits (up to
`timeout` seconds) for the broker's acknowledgement before disconnecting, and
returns `false` if it never arrives. Any other QoS value throws an
`InvalidArgumentException`.

```php
// $topic, $message, $clientId, $retain, $qos
Mqtt::ConnectAndPublish('orders/created', $json, null, null, 1);

// $topic, $callback, $clientId, $qos
Mqtt::ConnectAndSubscribe('orders/#', $handler, null, 2);
```

## Last Will and reconnects

**Last Will and Testament** lets the broker announce on a device's behalf that
it went offline when its connection drops without a clean disconnect. It's
attached to subscribe (long-lived) connections only:

```dotenv
MQTT_LAST_WILL_TOPIC=devices/42/status
MQTT_LAST_WILL_MESSAGE=offline
MQTT_LAST_WILL_QOS=1
MQTT_LAST_WILL_RETAIN=true
```

**Automatic reconnects** keep long-running subscribers alive through network
blips:

```dotenv
MQTT_RECONNECT=true
MQTT_RECONNECT_MAX_ATTEMPTS=5
MQTT_RECONNECT_DELAY=1000
```

Reconnects apply to subscribers only (a publish is a single short connection).
After a reconnect the subscriber subscribes again to its topics.
`php-mqtt/client` can't combine automatic reconnects with a clean session, so
`clean_session` is turned off while reconnects are enabled.

**Persistent sessions** (`MQTT_CLEAN_SESSION=false` with a fixed client id and
QoS 1 or 2) let a subscriber receive the messages that were published while it
was offline:

```php
Mqtt::ConnectAndSubscribe('orders/#', $handler, 'orders-worker-1', 1);
```

Publishes and subscribers without a client id always use a clean session, so
they never leave sessions behind on the broker. Reconnects need a persistent
session, so with `MQTT_RECONNECT=true` **pass a fixed client id** (unique per
process): without one, a session is removed when the subscriber stops cleanly,
but one is left behind each time the process is killed or gives up.

## Queued publishing

Publishing opens a socket to the broker, which you usually don't want to do
inline in an HTTP request. `Mqtt::queue(...)` dispatches a job instead, so the
request returns immediately and a queue worker does the publish:

```php
Mqtt::queue('home/light', 'on');
// $topic, $message, $clientId, $retain, $connection, $qos
Mqtt::queue('sensors/temp', '21.5', null, 1, 'sensors', 1);
```

Make sure a queue worker is running (`php artisan queue:work`). If the publish
fails, the job throws, so the queue retries it (`--tries`) and records it in
`failed_jobs` once it gives up. Binary payloads are supported. Optionally pin
the jobs to a specific queue connection and/or name in `config/mqtt.php`:

```php
'queue' => [
    'connection' => env('MQTT_QUEUE_CONNECTION', null), // null = default queue connection
    'name'       => env('MQTT_QUEUE', null),            // null = default queue
],
```

## Artisan commands

Publish or subscribe straight from the CLI — no need to hand-write a console
command:

Both exit with code `1` on failure (bad input, unknown connection or a broker
error), so they're safe to use in scripts.

```bash
# Publish a message
php artisan mqtt:publish home/light on
php artisan mqtt:publish home/light on --retain --qos=1 --connection=sensors
php artisan mqtt:publish home/light on --no-retain   # override MQTT_RETAIN=1

# Subscribe to one or more topics (Ctrl+C / SIGTERM stops it and disconnects cleanly)
php artisan mqtt:subscribe home/light
php artisan mqtt:subscribe "sensors/#" "home/+/status" --qos=1 --connection=sensors
```

## Multiple connections

Talk to more than one broker by defining extra connections in `config/mqtt.php`.
The top-level settings are the `default` connection; each named connection
inherits them (nested arrays like `tls` included) and overrides only what it
needs:

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

To make a named connection the default, set `MQTT_CONNECTION=sensors`. That
applies to the facade, `new Mqtt()` and the helper functions alike. It must be
`default` or the name of a defined connection; anything else throws an
`InvalidArgumentException` instead of silently using the top-level settings.
`Mqtt::interrupt()` stops subscribe loops on every connection.

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
            ->qos(1)
            ->retain();
    }
}
```

The topic can come from the `MqttMessage` or from a
`routeNotificationForMqtt()` method on the notifiable. If the publish fails,
the channel throws `MqttConnectionException`, so a queued notification is
retried.

## TLS / SSL

Provide a CA file (and optionally a client certificate) to connect over
`tls://`. Set the broker port to `8883` and configure the certificate paths:

```dotenv
MQTT_PORT=8883
MQTT_CERT_FILE=/path/to/ca.crt
MQTT_LOCAL_CERT=/path/to/client.crt
MQTT_LOCAL_PK=/path/to/client.key
```

For a broker with a certificate from a public CA, you don't need a CA file at
all. Turn TLS on with `MQTT_TLS=true` and the system trust store is used.

For a broker whose certificate comes from your own (private) CA, point
`MQTT_CERT_FILE` at that CA. That's all you need, and verification stays on.
`MQTT_TLS_ALLOW_SELF_SIGNED=true` is only for a broker that presents a
self-signed certificate directly (development). Keep the `verify_peer` options
enabled in production.

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

Queued publishes are recorded too, with matching assertions:

```php
Mqtt::fake();
// ... Mqtt::queue('sensors/temp', '21.5') ...
Mqtt::assertQueued('sensors/temp', '21.5');
Mqtt::assertQueuedCount(1);
Mqtt::assertNotQueued('sensors/humidity');
Mqtt::assertNothingPublished(); // queued, not published inline
```

The fake also catches publishes from the helper functions and from
`new Mqtt()`, and records subscriptions (`Mqtt::assertSubscribed('sensors/#')`).
It validates topics, payloads and QoS exactly like the real client.

## Available methods

| Method | Returns | Description |
|--------|---------|-------------|
| `ConnectAndPublish(string $topic, string $message, string\|int $clientId = null, int $retain = null, int $qos = null)` | `bool` | Connect, publish a message and disconnect. |
| `ConnectAndSubscribe(string\|array $topic, callable $callback, string\|int $clientId = null, int $qos = null)` | `bool` | Connect and listen for messages on one or more topics until interrupted. |
| `interrupt()` | `void` | Stop running subscribe loops and disconnect cleanly. |
| `queue(string $topic, string $message, string\|int $clientId = null, int $retain = null, string $connection = null, int $qos = null)` | `PendingDispatch` | Publish asynchronously via a queued job. |
| `connection(string $name = null)` | `Mqtt` | Get a specific broker connection. |
| `fake()` | `MqttFake` | Swap in a test double that records published/queued messages. |

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

- **MQTT 5** (message expiry, user properties, shared subscriptions) once
  `php-mqtt/client` supports it. Its current major version (2.x) implements
  MQTT 3.1 and 3.1.1 only.
- A supervised subscriber worker (restart on failure, health checks) on top of
  the interruptible loop and reconnects added in v4.

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
