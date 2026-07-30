# Contributing

Thanks for your interest in improving `salmanzafar/laravel-mqtt`!

## Getting started

```bash
git clone https://github.com/salmanzafar949/MQTT-Laravel.git
cd MQTT-Laravel
composer install
```

## Quality gates

Please make sure the following pass before opening a pull request:

```bash
composer test        # PHPUnit test suite
composer lint        # Laravel Pint (code style, --test = check only)
composer analyse     # PHPStan static analysis
```

`composer lint:fix` will apply the code-style fixes automatically.

## Guidelines

- Keep changes focused; one logical change per pull request.
- Add or update tests for any behavioural change.
- Follow the existing coding style (enforced by Pint).
- Update `CHANGELOG.md` under the `Unreleased` section.

## Reporting issues

When filing a bug, please include your PHP version, Laravel version, the MQTT
broker you are using, and a minimal snippet that reproduces the problem.
