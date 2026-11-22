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
| PHP | 7.2 – 8.4 | **8.0+** |
| Protocol engine | built-in fork of bluerhinos/phpMQTT | `php-mqtt/client` ^2.0 |
| MQTT versions | 3.1 | **3.1.1** (default) and 3.1 |

## Upgrade steps

```bash
composer require salmanzafar/laravel-mqtt:^4.0
```

If you published `config/mqtt.php`, compare it with the new default (or
re-publish with `php artisan vendor:publish --provider="Salman\Mqtt\MqttServiceProvider" --force`)
to pick up the new options below. Every new option has a safe default, so an
old config file keeps working.

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
- **Automatic reconnects** with `reconnect.enabled|max_attempts|delay`.
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
  that's what `php-mqtt/client` logs at debug level. Errors are still logged
  when `debug` is off.

## Not supported

- **MQTT 5** (and with it message expiry, user properties and shared
  subscriptions). `php-mqtt/client` v2 implements MQTT 3.1 and 3.1.1 only.
