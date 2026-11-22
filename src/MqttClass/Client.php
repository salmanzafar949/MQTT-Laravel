<?php

namespace Salman\Mqtt\MqttClass;

use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\MqttClient;

/**
 * php-mqtt/client with a deadline on blocking reads.
 *
 * The upstream client reads the broker's CONNACK with a blocking read on a
 * non-blocking socket, which spins forever when a server accepts the TCP
 * connection but never answers. This waits for data with stream_select()
 * and gives up once the read timeout has passed.
 *
 * @internal
 */
class Client extends MqttClient
{
    /** @var float seconds a blocking read may wait for data */
    protected $readTimeout = 10.0;

    /**
     * @param  float  $seconds
     * @return $this
     */
    public function setReadTimeout($seconds)
    {
        $this->readTimeout = max(0.001, (float) $seconds);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function readFromSocket(int $limit = self::SOCKET_READ_BUFFER_SIZE, bool $withoutBlocking = false): string
    {
        if ($withoutBlocking) {
            return parent::readFromSocket($limit, true);
        }

        $deadline = microtime(true) + $this->readTimeout;
        $result = '';

        while (strlen($result) < $limit) {
            // Read whatever is already buffered first: stream_select() can't see
            // data that PHP's stream layer (e.g. TLS) has already pulled in.
            $chunk = parent::readFromSocket($limit - strlen($result), true);

            if ($chunk !== '') {
                $result .= $chunk;

                continue;
            }

            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    sprintf('the broker did not respond within %s seconds.', $this->readTimeout)
                );
            }

            $read = [$this->socket];
            $write = null;
            $except = null;
            $seconds = (int) floor($remaining);

            // A false return (e.g. interrupted by a signal) just retries until the deadline.
            @stream_select($read, $write, $except, $seconds, (int) (($remaining - $seconds) * 1000000));
        }

        return $result;
    }
}
