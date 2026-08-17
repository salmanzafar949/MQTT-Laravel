# Changelog

All notable changes to `salmanzafar/laravel-mqtt` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
