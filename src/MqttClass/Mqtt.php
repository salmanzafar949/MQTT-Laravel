<?php
/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 1:16 PM
 */

namespace Salman\Mqtt\MqttClass;

/*
	Licence
	Copyright (c) 2019 Salman Zafar
	salmanzafar949@gmail.com
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

*/


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
        $client = new MqttService($this->host,$this->port, rand(0,100), $this->cert_file, $this->debug);

        if ($client->connect(true, null, $this->username, $this->password))
        {
            $client->publish($topic,$msg);
            $client->close();

            return true;
        }

        return false;

    }

    public function ConnectAndSubscribe($topic, $proc)
    {
        $client = new MqttService($this->host,$this->port, rand(0,100), $this->cert_file, $this->debug);

        if ($client->connect(true, null, $this->username, $this->password))
        {
            $topics[$topic] = array("qos" => 0, "function" => $proc);

            $client->subscribe($topics, 0);

            while($client->proc())
            {

            }

            $client->close();
        }

        return false;
    }

}
