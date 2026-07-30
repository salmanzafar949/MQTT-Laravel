# Changelog

All notable changes to `salmanzafar/laravel-mqtt` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

## Earlier releases

See the [GitHub releases page](https://github.com/salmanzafar949/MQTT-Laravel/releases)
for the history of tagged versions.
