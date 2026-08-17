# v4 plan — modern MQTT protocol (3.1.1 / 5.0)

> Status: **planning / not yet implemented.** This document describes the intended
> direction for the next major version. Nothing here ships in the 3.x line.

## Why

The current client is a fork of the old `bluerhinos/phpMQTT` and speaks **MQTT
3.1** (the legacy `MQIsdp` protocol name, level `0x03`). That is the root cause
of most historical issues:

- Many modern brokers (EMQX, HiveMQ, AWS IoT, Mosquitto with defaults) prefer
  **MQTT 3.1.1 / 5.0**.
- **QoS 1 and 2 are not truly implemented** — `publish()` never processes the
  `PUBACK` / `PUBREC` / `PUBREL` / `PUBCOMP` handshake, which is why messages can
  be lost at QoS 1 (issue #44).
- The blocking `read()` has no internal timeout (issue #29).
- No **Last Will & Testament**, **persistent sessions**, **message expiry**
  (issue #48), **retained-message** handling, or **MQTT 5** properties.

## Approach

Delegate the wire protocol to the maintained, well-tested
[`php-mqtt/client`](https://github.com/php-mqtt/client) library and keep this
package as the **thin Laravel layer** it already is (config, manager, facade,
events, notification channel, Artisan commands, `fake()`).

```
Salman\Mqtt\MqttManager      (unchanged public surface)
        │  resolves per-connection config
        ▼
Salman\Mqtt\MqttClass\Mqtt   ──►  php-mqtt/client  ──►  broker
   (adapter: publish / subscribe / loop)
```

### What we gain for free

- MQTT **3.1.1 and 5.0**, selectable per connection.
- Correct **QoS 0/1/2** delivery.
- **Last Will & Testament**, **clean/persistent sessions**, **keep-alive**,
  **auto-reconnect with backoff**, **message expiry**, **retained** handling.
- A proper, interruptible subscribe loop (clean `mqtt:subscribe` shutdown).

## Public API — keep it compatible where possible

The goal is that most users only change the composer constraint:

- `Mqtt::ConnectAndPublish($topic, $message, $clientId, $retain)` — keep as a
  thin wrapper over the new client.
- `Mqtt::ConnectAndSubscribe($topic, $callback, $clientId)` — keep; still fires
  the `MqttMessageReceived` event.
- `Mqtt::connection()`, `Mqtt::fake()`, the notification channel, and both
  Artisan commands — unchanged.

### New, additive API (opt-in)

- `->publish($topic, $message, qos: 1, retain: true)`
- `->subscribe($topic, $callback, qos: 1)`
- Per-connection config: `protocol` (3.1.1 / 5.0), `clean_session`,
  `last_will` (`topic`, `message`, `qos`, `retain`), `reconnect` (attempts,
  delay), `message_expiry`.

## Breaking changes to expect

- **New dependency** (`php-mqtt/client`) and a higher **PHP floor** (likely
  `^8.1`).
- The internal `Salman\Mqtt\MqttClass\MqttService` (the hand-rolled protocol) is
  **removed**; anyone instantiating it directly must migrate. The `Mqtt`
  facade / manager API is preserved.
- Config keys for TLS map onto the new client's options (documented in an
  `UPGRADING` guide shipped with v4).

## Rollout

1. Add `php-mqtt/client`, implement the adapter behind the existing `Mqtt`
   methods, keep 3.x tests green.
2. Add the opt-in QoS / LWT / reconnect config and tests (with a Mosquitto
   service container in CI for real integration tests).
3. Write the `UPGRADING` guide, tag **v4.0.0**.

## Open questions

- Do we keep a `legacy` protocol connection driver for one more minor, or drop
  the hand-rolled implementation immediately in v4?
- Expose the `php-mqtt/client` object for advanced users, or keep it fully
  wrapped?
