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

//         $this->client = new phpMQTT($this->host, $this->port, 25,$this-$this->cert_file);
//        $this->client = new MQTTClient($this->host,$this->port);
    }


    public function ConnectAndSendMessage($topic, $msg)
    {
        $client = new phpMQTT($this->host,$this->port, rand(0,100), $this->cert_file, $this->debug);

        if ($client->connect(true))
        {
            $client->publish($topic,$msg);
            $client->close();

            return true;
        }

        return false;



//        $this->client->setAuthentication($this->username,$this->password);
//        $this->client->setEncryption($this->cert_file);
//        $success = $this->client->sendConnect(rand(0,63));  // set your client ID
//        if ($success) {
//            $this->client->sendPublish($topic, $msg);
//            $messages = $this->client->getPublishMessages();  // now read and acknowledge all messages waiting
//            foreach ($messages as $message) {
//                echo $message['topic'] .': '. $message['message'] . PHP_EOL;
//            }
//
//            $this->client->sendDisconnect();
//            echo 'success';
//        }
//
//        $this->client->close();
//        echo 'error';
    }

}
