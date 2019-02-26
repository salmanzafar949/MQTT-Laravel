# Laravel MQTT Package

A simple Package that can be used to connect to Mqtt using laravel

Based on [bluerhinos/phpMQTT](https://github.com/bluerhinos/phpMQTT)

## Installation
```
composer require salmanzafar/mqtt_laravel
```
## Features

* Name and Password Authentication
* Certificate Protection for end to end encryption
* Enable Debug mode to make it easier for debugging 

## Configuration
```
php artisan vendor:publish --provider="Salman\Mqtt\MqttServiceProvider"
```
## Config/mqtt.php
```
    'host'     => env('mqtt_host','127.0.0.1'),
    'password' => env('mqtt_password',''),
    'username' => env('mqtt_username',''),
    'certfile' => env('mqtt_cert_file',''),
    'port'     => env('mqtt_port','1883'),
    'debug'    => env('mqtt_debug',false) //Optional Parameter to enable debugging set it to True
```
#### Publishing topic

```
use Salman\Mqtt\MqttClass\Mqtt;

public function SendMsgViaMqtt($topic, $message)
{
        $mqtt = new Mqtt();
        $output = $mqtt->ConnectAndPublish($topic, $message);

        if ($output === true)
        {
            return true;
        }

        return false;
}
```

### Tested on php 7.3 and laravel 5.7 

# Full documentation Coming Soon
