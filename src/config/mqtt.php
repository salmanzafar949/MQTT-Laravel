<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broker connection
    |--------------------------------------------------------------------------
    */

    'host'      => env('MQTT_HOST', '127.0.0.1'),
    'password'  => env('MQTT_PASSWORD', ''),
    'username'  => env('MQTT_USERNAME', ''),
    'port'      => env('MQTT_PORT', '1883'),
    'timeout'   => (int) env('MQTT_TIMEOUT', 10),

    // Seconds between keep-alive pings sent to the broker.
    'keepalive' => (int) env('MQTT_KEEPALIVE', 10),

    // MQTT protocol version: '3.1.1' (default) or '3.1'.
    'protocol'  => env('MQTT_PROTOCOL', '3.1.1'),

    'debug'     => (bool) env('MQTT_DEBUG', false), // Log every MQTT packet through the Laravel logger
    'qos'       => (int) env('MQTT_QOS', 0), // Default quality of service: 0, 1 or 2
    'retain'    => (int) env('MQTT_RETAIN', 0), // Default retain flag: 0 or 1

    // Start each connection with a clean session. Forced off when automatic
    // reconnects are enabled (php-mqtt/client does not allow both together).
    'clean_session' => (bool) env('MQTT_CLEAN_SESSION', true),

    // When true, connection failures throw Salman\Mqtt\Exceptions\MqttConnectionException
    // instead of returning false. Defaults to false for backwards compatibility.
    'exceptions' => (bool) env('MQTT_EXCEPTIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Last Will and Testament
    |--------------------------------------------------------------------------
    | If a topic is set, the broker publishes this message on the client's
    | behalf when the connection drops without a clean disconnect.
    */

    'last_will' => [
        'topic'   => env('MQTT_LAST_WILL_TOPIC', null),
        'message' => env('MQTT_LAST_WILL_MESSAGE', ''),
        'qos'     => (int) env('MQTT_LAST_WILL_QOS', 0),
        'retain'  => (bool) env('MQTT_LAST_WILL_RETAIN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic reconnects
    |--------------------------------------------------------------------------
    | Reconnect when the connection drops mid-loop (useful for long-running
    | subscribers). Delay is in milliseconds.
    */

    'reconnect' => [
        'enabled'      => (bool) env('MQTT_RECONNECT', false),
        'max_attempts' => (int) env('MQTT_RECONNECT_MAX_ATTEMPTS', 3),
        'delay'        => (int) env('MQTT_RECONNECT_DELAY', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued publishing
    |--------------------------------------------------------------------------
    | Mqtt::queue(...) dispatches a job so callers don't block on broker I/O.
    | Optionally pin those jobs to a specific queue connection and/or queue
    | name; null uses your app's defaults.
    */

    'queue' => [
        'connection' => env('MQTT_QUEUE_CONNECTION', null),
        'name'       => env('MQTT_QUEUE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | TLS / SSL
    |--------------------------------------------------------------------------
    | TLS is used when a CA file is set or `tls.enabled` is true. Keep the
    | verify_* options enabled in production.
    */

    'certfile'  => env('MQTT_CERT_FILE', ''),
    'localcert' => env('MQTT_LOCAL_CERT', ''),
    'localpk'   => env('MQTT_LOCAL_PK', ''),

    'tls' => [
        'enabled'           => (bool) env('MQTT_TLS', false),
        'verify_peer'       => (bool) env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name'  => (bool) env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'allow_self_signed' => (bool) env('MQTT_TLS_ALLOW_SELF_SIGNED', false),
        'passphrase'        => env('MQTT_TLS_PASSPHRASE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional named connections
    |--------------------------------------------------------------------------
    | The settings above are the "default" connection. To talk to more than one
    | broker, define extra connections here and select them with
    | Mqtt::connection('name'). Each connection inherits the settings above and
    | overrides only what it needs.
    |
    | 'connections' => [
    |     'sensors' => [
    |         'host' => env('MQTT_SENSORS_HOST', '127.0.0.1'),
    |         'port' => env('MQTT_SENSORS_PORT', '1883'),
    |     ],
    | ],
    */

    'default' => env('MQTT_CONNECTION', 'default'),

    'connections' => [],
];
