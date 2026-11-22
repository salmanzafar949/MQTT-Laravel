# Changelog

All notable changes to `salmanzafar/laravel-mqtt` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - 2026-11-22

The built-in protocol code (a fork of the unmaintained `bluerhinos/phpMQTT`) is
replaced by the maintained [`php-mqtt/client`](https://github.com/php-mqtt/client).
The Laravel-facing API is unchanged. See [docs/UPGRADING-v4.md](docs/UPGRADING-v4.md).
v3.x remains available, unchanged, for PHP 7.2+.

### Added
- **Real QoS 1 and 2**: publishes wait for the broker's acknowledgement. QoS can
  be set per call (`ConnectAndPublish`, `ConnectAndSubscribe`, `queue`) or per
  connection, and with `--qos` on both Artisan commands.
- **MQTT 3.1.1** (new default) alongside 3.1, set with `protocol`.
- **Last Will and Testament** (`last_will` config).
- **Automatic reconnects** (`reconnect` config) and a `clean_session` option.
- **`Mqtt::interrupt()`** to stop a subscribe loop and disconnect cleanly;
  `mqtt:subscribe` now uses it on SIGINT/SIGTERM instead of `exit()`.
- `tls.enabled` to use TLS with a public-CA broker without a CA file.
- Real-broker integration tests for QoS 1/2 delivery and Last Will.

### Changed
- **Requires PHP 8.0+.**
- `debug` now logs every MQTT packet through the Laravel logger; errors are
  still logged when it's off.
- CI now tests Laravel 12 and 13 on PHP 8.2 – 8.5. Laravel 9 – 11 are no longer
  tested: they're end-of-life with unpatched security advisories, and Composer
  refuses to install them.

### Removed
- `Salman\Mqtt\MqttClass\MqttService`, the legacy hand-written protocol client.
- `tls.ciphers` (not supported by `php-mqtt/client`).

## [3.3.0] - 2026-09-25

### Added
- **Queued / async publishing.** `Mqtt::queue($topic, $message, $clientId, $retain, $connection)`
  dispatches a `PublishMqttMessage` job so HTTP requests and other hot paths
  don't block on broker I/O. Pin the jobs to a queue connection/name via the new
  `mqtt.queue` config. `Mqtt::fake()` records queued publishes with matching
  assertions (`assertQueued`, `assertNotQueued`, `assertNothingQueued`,
  `assertQueuedCount`). Verified end-to-end: dispatch → database queue → worker →
  real broker delivery.

## [3.2.0] - 2026-08-01

### Added
- **Named multi-broker connections.** Define extra brokers under
  `mqtt.connections` and select them with `Mqtt::connection('name')`. The
  existing flat config remains the "default" connection (backwards compatible).
- **Artisan commands** `mqtt:publish {topic} {message}` and
  `mqtt:subscribe {topic*}` (with `--connection`, `--client-id`, `--retain`
  options and graceful Ctrl+C handling).
- **`MqttMessageReceived` event**, dispatched for every received message in
  addition to the subscription callback.
- **Notification channel** — add `'mqtt'` to a notification's `via()` and return
  a payload/`MqttMessage` from `toMqtt()` to publish notifications over MQTT.
- **`Mqtt::fake()`** test double with `assertPublished`, `assertNotPublished`,
  `assertNothingPublished` and `assertPublishedCount` for testing app code
  without a broker.
- A [v4 migration plan](docs/UPGRADING-v4.md) for moving the wire protocol onto
  `php-mqtt/client` (MQTT 3.1.1/5.0, real QoS 1/2, LWT, message expiry).

### Changed
- The `Mqtt` facade now resolves an `MqttManager`; existing
  `Mqtt::ConnectAndPublish(...)` / `ConnectAndSubscribe(...)` calls are proxied
  to the default connection and keep working unchanged.

### Fixed
- **Subscribing now works against strict brokers (e.g. Mosquitto 2.x).** The
  SUBSCRIBE packet was sent with an invalid fixed-header byte (`0x80` instead of
  the mandatory `0x82`), which strict brokers reject as malformed, silently
  dropping the subscription. This is the root cause of the long-standing
  "unable to subscribe" reports (#42, #46). Verified with a real-broker
  round-trip integration test.

## [3.1.0] - 2026-07-30

### Added
- Configurable TLS options (`verify_peer`, `verify_peer_name`, `allow_self_signed`,
  `ciphers`, `passphrase`) via the `mqtt.tls` config array.
- Optional [PSR-3](https://www.php-fig.org/psr/psr-3/) logger support — when running
  inside Laravel the framework logger is used for debug/error output instead of
  `echo`/`error_log`.
- Opt-in exceptions: set `mqtt.exceptions` to `true` to have connection failures
  throw `Salman\Mqtt\Exceptions\MqttConnectionException` instead of returning `false`.
- `@method` annotations on the `Mqtt` facade for IDE autocompletion, documenting
  both the original PascalCase and the (case-insensitive) camelCase call styles.
- Developer tooling: Laravel Pint (code style) and PHPStan (static analysis) with a
  dedicated CI workflow.
- `LICENSE`, `CHANGELOG.md` and `CONTRIBUTING.md`.

### Changed
- Socket writes now check their return value and fail cleanly on a broken pipe
  instead of silently continuing.
- Releases are now tagged automatically from the `version` field in
  `composer.json` when a change is merged into `master`.

## Earlier releases

See the [GitHub releases page](https://github.com/salmanzafar949/MQTT-Laravel/releases)
for the history of tagged versions.
