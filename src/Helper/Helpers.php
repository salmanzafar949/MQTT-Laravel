<?php

use Salman\Mqtt\MqttClass\Mqtt;


if (!function_exists('connectToPublish'))
{
    function connectToPublish($topic, $message, $client_id=null, $retain=null)
    {
        $mqtt = new Mqtt();

        return $mqtt->ConnectAndPublish($topic, $message, $client_id, $retain);
    }
}


if (!function_exists('connectToSubscribe'))
{
    function connectToSubscribe($topic, $client_id=null)
    {
        $mqtt = new Mqtt();

        return $mqtt->ConnectAndSubscribe($topic, function($topic, $msg){
            echo "Msg Received: \n";
            echo "Topic: {$topic}\n\n";
            echo "\t$msg\n\n";
        }, $client_id);
    }
}