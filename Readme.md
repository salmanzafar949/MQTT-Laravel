# Laravel MQTT Package

A simple Laravel Library to connect/publish/subscribe to MQTT broker

Based on [bluerhinos/phpMQTT](https://github.com/bluerhinos/phpMQTT)

For Example see this [repo](https://github.com/salmanzafar949/Laravel-Mqtt-Example)

## Compatibility

| PHP           | Laravel                    |
|---------------|----------------------------|
| 7.2 – 8.4     | 5.5 – 12.x                 |

The package uses Laravel's package auto-discovery, so it works out of the box
across all of the above versions.

## Installation
```
composer require salmanzafar/laravel-mqtt
```
## Features

* Name and password authentication
* Client certificate authentication
* Certificate Protection for end to end encryption
* Enable Debug mode to make it easier for debugging 
* Now you can also set Client_id of your choice and if you don't want just simply don't use or set it to null
* Set QOS flag directly from config file
* Set Retain flag directly from config file
* Addition of Helper functions to make development more easy

## Enable the package (Optional)

This package implements Laravel auto-discovery feature. After you install it the package provider and facade are added automatically for laravel >= 5.5.

__This step is only required if you are using laravel version <5.5__

To declare the provider and/or alias explicitly, then add the service provider to your config/app.php:

```
'providers' => [

        Salman\Mqtt\MqttServiceProvider::class,
];
```
And then add the alias to your config/app.php:
```
'aliases' => [

       'Mqtt' => \Salman\Mqtt\Facades\Mqtt::class,
];
```
## Configuration
Publish the configuration file
```
php artisan vendor:publish --provider="Salman\Mqtt\MqttServiceProvider"
```
## Config/mqtt.php
```
    'host'       => env('MQTT_HOST', '127.0.0.1'),
    'password'   => env('MQTT_PASSWORD', ''),
    'username'   => env('MQTT_USERNAME', ''),
    'port'       => env('MQTT_PORT', '1883'),
    'timeout'    => (int) env('MQTT_TIMEOUT', 10),
    'keepalive'  => (int) env('MQTT_KEEPALIVE', 10),   // seconds between keep-alive pings
    'debug'      => (bool) env('MQTT_DEBUG', false),   // enable debug logging
    'qos'        => env('MQTT_QOS', 0),                // quality of service
    'retain'     => env('MQTT_RETAIN', 0),             // 0 or 1 - retain flag

    // Throw Salman\Mqtt\Exceptions\MqttConnectionException on failure
    // instead of returning false. Defaults to false (backwards compatible).
    'exceptions' => (bool) env('MQTT_EXCEPTIONS', false),

    // TLS / SSL (provide a CA file to connect over tls://)
    'certfile'   => env('MQTT_CERT_FILE', ''),
    'localcert'  => env('MQTT_LOCAL_CERT', ''),
    'localpk'    => env('MQTT_LOCAL_PK', ''),
    'tls' => [
        'verify_peer'       => (bool) env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name'  => (bool) env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'allow_self_signed' => (bool) env('MQTT_TLS_ALLOW_SELF_SIGNED', false),
        'ciphers'           => env('MQTT_TLS_CIPHERS', null),
        'passphrase'        => env('MQTT_TLS_PASSPHRASE', null),
    ],
```

When running inside Laravel, debug and error messages are written through the
framework logger. Set `mqtt.exceptions` to `true` if you would rather catch a
`Salman\Mqtt\Exceptions\MqttConnectionException` than check for a `false`
return value.
#### Publishing topic

```
use Salman\Mqtt\MqttClass\Mqtt;

public function SendMsgViaMqtt($topic, $message)
{
        $mqtt = new Mqtt();
        $client_id = Auth::user()->id;
        $output = $mqtt->ConnectAndPublish($topic, $message, $client_id);

        if ($output === true)
        {
            return "published";
        }
        
        return "Failed";
}
```
#### Publishing topic using Facade

```
use Mqtt;

public function SendMsgViaMqtt($topic, $message)
{
        $client_id = Auth::user()->id;
        
        $output = Mqtt::ConnectAndPublish($topic, $message, $client_id);

        if ($output === true)
        {
            return "published";
        }

        return "Failed";
}
```

#### Subscribing topic

```
use Salman\Mqtt\MqttClass\Mqtt;

public function SubscribetoTopic($topic)
    {
        $mqtt = new Mqtt();
        $client_id = Auth::user()->id;
        $mqtt->ConnectAndSubscribe($topic, function($topic, $msg){
            echo "Msg Received: \n";
            echo "Topic: {$topic}\n\n";
            echo "\t$msg\n\n";
        }, $client_id);


    }
```
#### Subscribing topic using Facade

```
use Mqtt;

public function SubscribetoTopic($topic)
    {
       //You can also subscribe to multiple topics using the same function $topic can be array of topics e.g ['topic1', 'topic2']

       Mqtt::ConnectAndSubscribe($topic, function($topic, $msg){
            echo "Msg Received: \n";
            echo "Topic: {$topic}\n\n";
            echo "\t$msg\n\n";
        },$client_id);


    }
```

#### Publishing topic using Helper method

```

public function SendMsgViaMqtt($topic, $message)
{
        $client_id = Auth::user()->id;
        
        $output = connectToPublish($topic, $message, $client_id);

        if ($output === true)
        {
            return "published";
        }

        return "Failed";
}
```

#### Subscribing topic using Helper method

```
//You can also subscribe to multiple topics using the same function $topic can be array of topics e.g ['topic1', 'topic2']
public function SubscribetoTopic($topic)
{
  return connectToSubscribe($topic,$client_id);
}
```

## Testing & quality

```
composer install
composer test        # PHPUnit
```

Code style (Laravel Pint) and static analysis (PHPStan) are also available:

```
composer lint        # check code style
composer lint:fix    # apply code style fixes
composer analyse     # run PHPStan
```

The Pint and PHPStan binaries are installed on demand by the `quality` CI
workflow; to run them locally add them once with
`composer require --dev laravel/pint larastan/larastan`.

## Releasing

Releases are published to [Packagist](https://packagist.org/packages/salmanzafar/laravel-mqtt)
automatically from git tags (Packagist is connected to this repository via the
Packagist GitHub App). To cut a new release, push a [semver](https://semver.org) tag:

```
git tag v3.0.0
git push origin v3.0.0
```

The `release` workflow runs the test suite for the tag and creates a GitHub
Release; Packagist then picks the new version up on its own.

## Happy Coding...!