# Upgrading to v4

v4 replaces the package's built-in MQTT protocol code (a fork of the old,
unmaintained `bluerhinos/phpMQTT`, speaking MQTT 3.1) with the maintained
[`php-mqtt/client`](https://github.com/php-mqtt/client) library. The
Laravel-facing API is unchanged, so most apps only need to update their
Composer constraint.

**v3.x is unchanged and still available.** If you can't move to PHP 8 yet, stay
on `^3.0` and nothing changes for you.

## Requirements

| | v3.x | v4.x |
|---|---|---|
| PHP | 7.2 – 8.4 | **8.0+** (CI-tested on 8.2 – 8.5) |
| Laravel | 5.5 – 12 (CI-tested on 9 – 12) | **12 – 13**, CI-tested (9 – 11 are end-of-life and no longer tested) |
| Protocol engine | built-in fork of bluerhinos/phpMQTT | `php-mqtt/client` ^2.0 |
| MQTT versions | 3.1 | **3.1.1** (default) and 3.1 |

## Upgrade steps

```bash
composer require salmanzafar/laravel-mqtt:^4.0
```

If you published `config/mqtt.php`, it keeps working: new keys, nested ones
included, are filled in from the package defaults. To see and document the new
options, compare it with the new default (or re-publish with
`php artisan vendor:publish --provider="Salman\Mqtt\MqttServiceProvider" --force`).

## What stays the same

- `Mqtt::ConnectAndPublish()`, `Mqtt::ConnectAndSubscribe()`, `Mqtt::queue()`,
  `Mqtt::connection()`, `Mqtt::fake()` and the helper functions
- The `mqtt:publish` / `mqtt:subscribe` Artisan commands
- The `MqttMessageReceived` event and the `mqtt` notification channel
- Named connections, TLS settings, the `exceptions` flag
- Subscription callbacks still receive `($topic, $message)`

## What's new

- **Real QoS 1 and 2.** Publishing at QoS 1/2 now waits for the broker's
  acknowledgement before disconnecting. Pass a QoS per call
  (`Mqtt::ConnectAndPublish($topic, $msg, null, null, 2)`) or set the
  connection's `qos`.
- **MQTT 3.1.1** by default, set with `protocol` (`'3.1.1'` or `'3.1'`).
- **Last Will and Testament** with `last_will.topic|message|qos|retain`.
- **Automatic reconnects** for subscribers with `reconnect.enabled|max_attempts|delay`.
  The subscriber subscribes again after reconnecting.
- **Persistent sessions**: with `clean_session` off, a fixed client id and QoS
  1/2, messages sent while a subscriber was offline arrive when it reconnects.
  `clean_session` applies to subscribers with a fixed client id only. Publishes
  and generated ids always use a clean session, so no stale sessions pile up
  on the broker.
  `php-mqtt/client` can't combine reconnects with a clean session, so
  `clean_session` is forced off while reconnects are enabled.
- **`clean_session`** option (default `true`).
- **Clean shutdown of subscribers.** `mqtt:subscribe` now stops its loop with
  `interrupt()` on SIGINT/SIGTERM and disconnects properly. You can do the same
  in your own code with `Mqtt::interrupt()`.
- `--qos` option on both Artisan commands.

## Breaking changes

- **PHP 8.0 or newer** is required.
- **`Salman\Mqtt\MqttClass\MqttService` has been removed.** If you instantiated
  it directly, use `Salman\Mqtt\MqttClass\Mqtt` (or the `Mqtt` facade) instead.
- **`tls.ciphers` has been removed.** `php-mqtt/client` has no cipher setting.
- **TLS turns on when `certfile` is set** (as before) **or when `tls.enabled` is
  true**, so you can now use a public-CA broker without a CA file.
- **`debug` now logs every MQTT packet** through the Laravel logger, because
  that's what `php-mqtt/client` logs at debug level. Warnings and errors are
  logged when `debug` is off, and the password is masked.
- **Invalid arguments throw `InvalidArgumentException`**: an empty topic or one
  with `+`/`#` when publishing, an array (or other non-string) payload, an
  empty topic list for subscribing, a QoS other than 0, 1 or 2, an unknown
  `protocol`. v3 sent these to the broker or silently adjusted them.
- **`$retain = 0` means "don't retain".** In v3 any falsy value fell back to
  the configured `retain`; now only `null` does.
- **Queued publishes that fail throw**, so the job is retried and ends up in
  `failed_jobs` instead of being marked as done.
- **A subscribe callback that throws no longer stops the loop.** The exception
  is logged and the next message is delivered.
- **`timeout` also bounds the wait for the broker's CONNACK and for QoS 1/2
  acknowledgements**, so a stuck broker makes the call return `false` (or
  throw) instead of hanging.
- **Generated client ids** look like `lmq_` + 16 hex characters. Pass your own
  if anything depends on the format.
- **`new Mqtt()` and the helpers follow `MQTT_CONNECTION`** (`mqtt.default`),
  like the facade. In v3 they always used the top-level settings.
- **The Last Will is only attached to subscribe connections.** A short publish
  connection that's killed mid-request no longer announces the device as
  offline.
- **The `mqtt` notification channel throws `MqttConnectionException` when the
  publish fails**, so queued notifications are retried instead of being lost.
- **Topic filters are validated**: `+` must fill a whole level and `#` must be
  the last one; topics must be valid UTF-8 and at most 65,535 bytes.
- **`timeout` is a float** (e.g. `0.5`) and also bounds the wait for SUBACK.
  If you published the config before, change its cast from `(int)` to
  `(float)` to use fractions: `'timeout' => (float) env('MQTT_TIMEOUT', 10)`.
  With `(int)`, `0.5` becomes `0`, which falls back to 10 seconds.
- **`MQTT_CONNECTION` must be `default` or a defined connection name.**
  Anything else now throws instead of silently using the top-level settings.
- **Duplicate topic filters** in one subscribe call are subscribed once.
- **`Mqtt::fake()` rejects unconfigured connection names**, like the real
  client, so a typo fails in tests too.

## Not supported

- **MQTT 5** (and with it message expiry, user properties and shared
  subscriptions). `php-mqtt/client` v2 implements MQTT 3.1 and 3.1.1 only.
