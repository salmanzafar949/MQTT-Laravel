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

    'debug'     => (bool) env('MQTT_DEBUG', false), // Optional Parameter to enable debugging set it to True
    'qos'       => env('MQTT_QOS', 0), // set quality of service here
    'retain'    => env('MQTT_RETAIN', 0), // it should be 0 or 1 Whether the message should be retained.- Retain Flag

    // When true, connection failures throw Salman\Mqtt\Exceptions\MqttConnectionException
    // instead of returning false. Defaults to false for backwards compatibility.
    'exceptions' => (bool) env('MQTT_EXCEPTIONS', false),

    /*
    |--------------------------------------------------------------------------
    | TLS / SSL
    |--------------------------------------------------------------------------
    | Provide a CA file to connect over tls://. The options below are forwarded
    | to the SSL stream context. Keep the verify_* options enabled in production.
    */

    'certfile'  => env('MQTT_CERT_FILE', ''),
    'localcert' => env('MQTT_LOCAL_CERT', ''),
    'localpk'   => env('MQTT_LOCAL_PK', ''),

    'tls' => [
        'verify_peer'       => (bool) env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name'  => (bool) env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'allow_self_signed' => (bool) env('MQTT_TLS_ALLOW_SELF_SIGNED', false),
        'ciphers'           => env('MQTT_TLS_CIPHERS', null),
        'passphrase'        => env('MQTT_TLS_PASSPHRASE', null),
    ],
];
