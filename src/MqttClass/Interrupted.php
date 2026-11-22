<?php

namespace Salman\Mqtt\MqttClass;

use PhpMqtt\Client\Exceptions\MqttClientException;

/**
 * Thrown inside the client when interrupt() stops a reconnect, so the
 * subscribe loop can end cleanly instead of reporting a lost connection.
 *
 * @internal
 */
class Interrupted extends MqttClientException
{
    public function __construct()
    {
        parent::__construct('The subscription was interrupted.');
    }
}
