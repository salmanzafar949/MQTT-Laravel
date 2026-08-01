<?php

namespace Salman\Mqtt\Exceptions;

use RuntimeException;

/**
 * Thrown when a connection to the MQTT broker cannot be established or is lost,
 * but only when the `mqtt.exceptions` config option is enabled. By default the
 * client keeps its historical behaviour of returning `false` instead.
 */
class MqttConnectionException extends RuntimeException
{
}
