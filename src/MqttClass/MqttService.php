<?php

/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 2:34 PM
 */

namespace Salman\Mqtt\MqttClass;

use Psr\Log\LoggerInterface;
use Salman\Mqtt\Exceptions\MqttConnectionException;

/*
    A simple php class to connect/publish/Subscribe to an MQTT broker
*/

/* phpMQTT */

class MqttService
{
    private $socket; 			/* holds the socket	*/
    private $msgid = 1;			/* counter for message id */
    public $keepalive = 10;		/* default keepalive timer */
    public $timesinceping;		/* host unix time, used to detect disconnects */
    public $topics = array(); 	/* used to store currently subscribed topics */
    public $debug = false;		/* should output debug messages */
    public $address;			/* broker address */
    public $port;				/* broker port */
    public $timeout = 0;        /* connection timeout */
    public $clientid;			/* client id sent to broker */
    public $will;				/* stores the will of the client */
    private $username;			/* stores username */
    private $password;			/* stores password */
    public $cafile;
    public $localcert;
    public $localpk;

    /** @var array<string, mixed> extra SSL stream-context options */
    protected $tlsOptions = [];

    /** @var LoggerInterface|null optional PSR-3 logger */
    protected $logger = null;

    /** @var bool throw exceptions on failure instead of returning false */
    protected $throwExceptions = false;

    public function __construct($address, $port, $timeout = 0, $clientId = null, $cafile = null, $localCert = null, $localPk = null, $debug = false)
    {
        $this->debug = $debug;
        $this->broker($address, $port, $timeout, $clientId, $cafile, $localCert, $localPk);
    }

    /* sets the broker details */
    public function broker($address, $port, $timeout = 0, $clientid = null, $cafile = null, $localcert = null, $localpk = null)
    {
        $this->address = $address;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->clientid = $clientid;
        $this->cafile = $cafile;
        $this->localcert = $localcert;
        $this->localpk = $localpk;
    }

    /**
     * Set a PSR-3 logger used for debug and error output.
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Provide extra SSL stream-context options (verify_peer, allow_self_signed, ...).
     *
     * @param  array<string, mixed>  $options
     * @return $this
     */
    public function setTlsOptions(array $options)
    {
        $this->tlsOptions = $options;

        return $this;
    }

    /**
     * Set the keep-alive interval, in seconds.
     *
     * @return $this
     */
    public function setKeepalive($seconds)
    {
        $this->keepalive = (int) $seconds;

        return $this;
    }

    /**
     * Toggle whether failures throw an exception instead of returning false.
     *
     * @return $this
     */
    public function throwExceptions($throw = true)
    {
        $this->throwExceptions = (bool) $throw;

        return $this;
    }

    /**
     * Send a message to the configured logger (or fall back to echo/error_log).
     */
    protected function log($level, $message)
    {
        if ($this->logger) {
            $this->logger->log($level, $message);

            return;
        }

        if ($level === 'debug') {
            if ($this->debug) {
                echo $message."\n";
            }

            return;
        }

        error_log($message."\n");
    }

    /**
     * Report a failure, either by throwing (when enabled) or returning false.
     *
     * @return false
     */
    protected function fail($message)
    {
        $this->log('error', $message);

        if ($this->throwExceptions) {
            throw new MqttConnectionException($message);
        }

        return false;
    }

    /**
     * Write a payload to the socket, guarding against broken pipes.
     *
     * @return bool
     */
    protected function write($data)
    {
        $bytes = @fwrite($this->socket, $data);

        if ($bytes === false || $bytes < strlen($data)) {
            $this->log('error', 'Failed to write the full payload to the MQTT socket.');

            return false;
        }

        return true;
    }

    public function connect_auto($clean = true, $will = null, $username = null, $password = null)
    {
        while ($this->connect($clean, $will, $username, $password) == false) {
            sleep(10);
        }
        return true;
    }

    /* connects to the broker
        inputs: $clean: should the client send a clean session flag */
    public function connect($clean = true, $will = null, $username = null, $password = null)
    {

        if ($will) {
            $this->will = $will;
        }
        if ($username) {
            $this->username = $username;
        }
        if ($password) {
            $this->password = $password;
        }
        if ($this->cafile) {
            $ssl = array_merge([
                "verify_peer"      => true,
                "verify_peer_name" => true,
                "cafile"           => $this->cafile,
            ], array_filter($this->tlsOptions, function ($value) {
                return $value !== null;
            }));
            if ($this->localcert && $this->localpk) {
                $ssl["local_cert"] = $this->localcert;
                $ssl["local_pk"] = $this->localpk;
            }
            $socketContext = stream_context_create(["ssl" => $ssl]);
            $this->socket = stream_socket_client("tls://" . $this->address . ":" . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $this->socket = stream_socket_client("tcp://" . $this->address . ":" . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        }
        if (!$this->socket) {
            return $this->fail("stream_socket_client() failed to connect to {$this->address}:{$this->port} ($errno, $errstr)");
        }
        stream_set_timeout($this->socket, 5);
        stream_set_blocking($this->socket, 0);
        $i = 0;
        $buffer = "";
        $buffer .= chr(0x00);
        $i++;
        $buffer .= chr(0x06);
        $i++;
        $buffer .= chr(0x4d);
        $i++;
        $buffer .= chr(0x51);
        $i++;
        $buffer .= chr(0x49);
        $i++;
        $buffer .= chr(0x73);
        $i++;
        $buffer .= chr(0x64);
        $i++;
        $buffer .= chr(0x70);
        $i++;
        $buffer .= chr(0x03);
        $i++;
        //No Will
        $var = 0;
        if ($clean) {
            $var += 2;
        }
        //Add will info to header
        if ($this->will != null) {
            $var += 4; // Set will flag
            $var += ($this->will['qos'] << 3); //Set will qos
            if ($this->will['retain']) {
                $var += 32;
            } //Set will retain
        }
        if ($this->username != null) {
            $var += 128;
        }	//Add username to header
        if ($this->password != null) {
            $var += 64;
        }	//Add password to header
        $buffer .= chr($var);
        $i++;
        //Keep alive
        $buffer .= chr($this->keepalive >> 8);
        $i++;
        $buffer .= chr($this->keepalive & 0xff);
        $i++;
        $buffer .= $this->strwritestring($this->clientid, $i);
        //Adding will to payload
        if ($this->will != null) {
            $buffer .= $this->strwritestring($this->will['topic'], $i);
            $buffer .= $this->strwritestring($this->will['content'], $i);
        }
        if ($this->username) {
            $buffer .= $this->strwritestring($this->username, $i);
        }
        if ($this->password) {
            $buffer .= $this->strwritestring($this->password, $i);
        }
        $head = "  ";
        $head[0] = chr(0x10);
        $head[1] = chr($i);
        if (! $this->write($head) || ! $this->write($buffer)) {
            return $this->fail('Connection failed! Unable to send the CONNECT packet to the broker.');
        }
        $string = $this->read(4);
        // The broker must reply with a 4-byte CONNACK. Some brokers (e.g. newer
        // EMQX builds) may return an empty or truncated response on failure, so
        // guard against reading offsets that do not exist. See issues #45 and #49.
        if (strlen($string) < 4) {
            return $this->fail('Connection failed! The broker returned an empty or incomplete response.');
        }
        if (ord($string[0]) >> 4 == 2 && $string[3] == chr(0)) {
            $this->log('debug', 'Connected to Broker');
        } else {
            return $this->fail(sprintf(
                'Connection failed! (Error: 0x%02x 0x%02x)',
                ord($string[0]),
                ord($string[3])
            ));
        }
        $this->timesinceping = time();
        return true;
    }

    /* read: reads in so many bytes */
    public function read($int = 8192, $nb = false)
    {
        //	print_r(socket_get_status($this->socket));

        $string = "";
        $togo = $int;

        if ($nb) {
            return fread($this->socket, $togo);
        }

        while (!feof($this->socket) && $togo > 0) {
            $fread = fread($this->socket, $togo);
            $string .= $fread;
            $togo = $int - strlen($string);
        }




        return $string;
    }

    /* subscribe: subscribes to topics */
    public function subscribe($topics, $qos = 0)
    {
        $i = 0;
        $buffer = $this->buildSubscribePayload($topics, $qos, $i);

        $cmd = 0x80;
        //$qos
        $cmd +=	($qos << 1);
        $head = chr($cmd);
        $head .= chr($i);

        $this->write($head);
        $this->write($buffer);
        $string = $this->read(2);

        $bytes = ord(substr($string, 1, 1));
        $string = $this->read($bytes);
    }

    /**
     * Builds the SUBSCRIBE packet payload for the given topics and registers
     * them for later message matching.
     *
     * Each topic entry is expected to be an array in the shape
     * ["qos" => int, "function" => callable]. Any other value (for example a
     * bare scalar accidentally mixed into the list) is skipped so that we never
     * try to read the "qos" offset of a non-array. This resolves the
     * "Undefined index: qos" / "Trying to access array offset" errors reported
     * in issues #46, #36, #30 and #27.
     *
     * @param array $topics
     * @param int   $qos    default QoS used when a topic has none of its own
     * @param int   $i      running byte counter (passed by reference)
     * @return string
     */
    protected function buildSubscribePayload($topics, $qos, &$i)
    {
        $buffer = "";
        $id = $this->msgid;
        $buffer .= chr($id >> 8);
        $i++;
        $buffer .= chr($id % 256);
        $i++;
        foreach ($topics as $key => $topic) {
            // Only genuine topic definitions carry a subscription callback.
            if (!is_array($topic) || !isset($topic['function'])) {
                continue;
            }
            $buffer .= $this->strwritestring($key, $i);
            $topicQos = isset($topic['qos']) ? (int) $topic['qos'] : (int) $qos;
            $buffer .= chr($topicQos);
            $i++;
            $this->topics[$key] = $topic;
        }
        return $buffer;
    }

    /* ping: sends a keep alive ping */
    public function ping()
    {
        $head = " ";
        $head = chr(0xc0);
        $head .= chr(0x00);
        $this->write($head);
        $this->log('debug', 'ping sent');
    }

    /* disconnect: sends a proper disconnect cmd */
    public function disconnect()
    {
        $head = " ";
        $head[0] = chr(0xe0);
        $head[1] = chr(0x00);
        $this->write($head);
    }

    /* close: sends a proper disconect, then closes the socket */
    public function close()
    {
        $this->disconnect();
        stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
    }

    /* publish: publishes $content on a $topic */
    public function publish($topic, $content, $qos = 0, $retain = 0)
    {
        $i = 0;
        $buffer = "";
        $buffer .= $this->strwritestring($topic, $i);
        //$buffer .= $this->strwritestring($content,$i);
        if ($qos) {
            $id = $this->msgid++;
            $buffer .= chr($id >> 8);
            $i++;
            $buffer .= chr($id % 256);
            $i++;
        }
        $buffer .= $content;
        $i += strlen($content);
        $head = " ";
        $cmd = 0x30;
        if ($qos) {
            $cmd += $qos << 1;
        }
        if ($retain) {
            $cmd += 1;
        }
        $head[0] = chr($cmd);
        $head .= $this->setmsglength($i);

        return $this->write($head) && $this->write($buffer);
    }

    /* message: processes a received topic */
    public function message($msg)
    {
        $tlen = (ord($msg[0]) << 8) + ord($msg[1]);
        $topic = substr($msg, 2, $tlen);
        $msg = substr($msg, ($tlen + 2));
        $found = 0;
        foreach ($this->topics as $key => $top) {
            if (preg_match("/^".str_replace(
                "#",
                ".*",
                str_replace(
                    "+",
                    "[^\/]*",
                    str_replace(
                        "/",
                        "\/",
                        str_replace(
                            "$",
                            '\$',
                            $key
                        )
                    )
                )
            )."$/", $topic)) {
                if (is_callable($top['function'])) {
                    call_user_func($top['function'], $topic, $msg);
                    $found = 1;
                }
            }
        }
        if (!$found) {
            $this->log('debug', 'msg received but no match in subscriptions');
        }
    }

    /* proc: the processing loop for an "always on" client
        set true when you are doing other stuff in the loop good for watching something else at the same time */
    public function proc($loop = true)
    {
        if (1) {
            $sockets = array($this->socket);
            $w = $e = null;
            $cmd = 0;

            //$byte = fgetc($this->socket);
            if (feof($this->socket)) {
                $this->log('debug', 'eof receive going to reconnect for good measure');
                fclose($this->socket);
                $this->connect_auto(false);
                if (count($this->topics)) {
                    $this->subscribe($this->topics);
                }
            }

            $byte = $this->read(1, true);

            if (!strlen($byte)) {
                if ($loop) {
                    usleep(100000);
                }

            } else {

                $cmd = (int)(ord($byte) / 16);
                $this->log('debug', "Receive: $cmd");
                $multiplier = 1;
                $value = 0;
                do {
                    $digit = ord($this->read(1));
                    $value += ($digit & 127) * $multiplier;
                    $multiplier *= 128;
                } while (($digit & 128) != 0);
                $this->log('debug', "Fetching: $value");

                if ($value) {
                    $string = $this->read($value);
                }

                if ($cmd) {
                    switch ($cmd) {
                        case 3:
                            $this->message($string);
                            break;
                    }
                    $this->timesinceping = time();
                }
            }
            if ($this->timesinceping < (time() - $this->keepalive)) {
                $this->log('debug', 'not found something so ping');
                $this->ping();
            }

            if ($this->timesinceping < (time() - ($this->keepalive * 2))) {
                $this->log('debug', 'not seen a package in a while, disconnecting');
                fclose($this->socket);
                $this->connect_auto(false);
                if (count($this->topics)) {
                    $this->subscribe($this->topics);
                }
            }
        }
        return 1;
    }

    /* getmsglength: */
    public function getmsglength(&$msg, &$i)
    {
        $multiplier = 1;
        $value = 0 ;
        do {
            $digit = ord($msg[$i]);
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $i++;
        } while (($digit & 128) != 0);
        return $value;
    }

    /* setmsglength: */
    public function setmsglength($len)
    {
        $string = "";
        do {
            $digit = $len % 128;
            $len = $len >> 7;
            // if there are more digits to encode, set the top bit of this digit
            if ($len > 0) {
                $digit = ($digit | 0x80);
            }
            $string .= chr($digit);
        } while ($len > 0);
        return $string;
    }

    /* strwritestring: writes a string to a buffer */
    public function strwritestring($str, &$i)
    {
        $ret = " ";
        $len = strlen($str);
        $msb = $len >> 8;
        $lsb = $len % 256;
        $ret = chr($msb);
        $ret .= chr($lsb);
        $ret .= $str;
        $i += ($len + 2);
        return $ret;
    }

    public function printstr($string)
    {
        $strlen = strlen($string);
        for ($j = 0;$j < $strlen;$j++) {
            $num = ord($string[$j]);
            if ($num > 31) {
                $chr = $string[$j];
            } else {
                $chr = " ";
            }
            printf("%4d: %08b : 0x%02x : %s \n", $j, $num, $num, $chr);
        }
    }
}
