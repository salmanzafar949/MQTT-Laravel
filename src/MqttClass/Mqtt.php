<?php
/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 1:16 PM
 */

namespace Salman\Mqtt\MqttClass;

class Mqtt
{
    protected $client;
    protected $host = null;
    protected $username = null;
    protected $cert_file = null;
    protected $password = null;
    protected $port = null;
    protected $debug = null;

    public function __construct()
    {
        $this->host      = config('mqtt.host');
        $this->username  = config('mqtt.username');
        $this->password  = config('mqtt.password');
        $this->cert_file = config('mqtt.certfile');
        $this->port      = config('mqtt.port');
        $this->debug     = config('mqtt.debug');

    }


    public function ConnectAndPublish($topic, $msg)
    {
        $client = new phpMQTT($this->host,$this->port, rand(0,100), $this->cert_file, $this->debug);

        if ($client->connect(true))
        {
            $client->publish($topic,$msg);
            $client->close();

            return true;
        }

        return false;

    }


}
