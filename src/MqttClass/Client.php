<?php

namespace Salman\Mqtt\MqttClass;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\MqttClient;
use Psr\Log\LoggerInterface;

/**
 * php-mqtt/client with the behaviour a Laravel app needs from a long-running
 * subscriber or a one-shot publish:
 *
 * - Blocking reads have a deadline. Upstream reads the broker's CONNACK with a
 *   blocking read on a non-blocking socket, which spins forever when a server
 *   accepts the TCP connection but never answers.
 * - run() waits for data with stream_select() instead of sleeping 100 ms
 *   between polls, so messages are handled as soon as they arrive.
 * - Reconnects are logged, and interrupt() also stops a reconnect backoff.
 *
 * @internal
 */
class Client extends MqttClient
{
    /** @var float seconds a blocking read may wait for data */
    protected $readTimeout = 10.0;

    /** @var bool */
    protected $stopped = false;

    /** @var ConnectionSettings|null */
    protected $connectionSettings = null;

    /** @var LoggerInterface|null */
    protected $log = null;

    public function __construct(
        string $host,
        int $port = 1883,
        ?string $clientId = null,
        string $protocol = self::MQTT_3_1,
        ?Repository $repository = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($host, $port, $clientId, $protocol, $repository, $logger);

        $this->log = $logger;
    }

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
    public function connect(?ConnectionSettings $settings = null, bool $useCleanSession = false): void
    {
        $this->connectionSettings = $settings ?? new ConnectionSettings();

        parent::connect($settings, $useCleanSession);
    }

    /**
     * {@inheritDoc}
     */
    public function interrupt(): void
    {
        $this->stopped = true;

        parent::interrupt();
    }

    /**
     * Whether interrupt() has been called.
     *
     * @return bool
     */
    public function isStopped()
    {
        return $this->stopped;
    }

    /**
     * Process incoming messages until interrupt() is called.
     *
     * @return void
     *
     * @throws Interrupted when interrupted during a reconnect
     */
    public function run()
    {
        $startedAt = microtime(true);

        while (! $this->stopped) {
            $this->loopOnce($startedAt, false);

            if (! $this->stopped) {
                // Wake up as soon as data arrives; otherwise at least every
                // 100 ms for keep-alive pings and resends.
                $this->waitForData(0.1);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function reconnect(): void
    {
        $settings = $this->connectionSettings ?? new ConnectionSettings();
        $attempts = max(1, $settings->getMaxReconnectAttempts());
        $delay = max(0, $settings->getDelayBetweenReconnectAttempts());

        for ($i = 1; $i <= $attempts; $i++) {
            if ($this->stopped) {
                throw new Interrupted();
            }

            $this->warn('Connection to the broker lost; reconnecting (attempt {attempt} of {attempts}).', [
                'attempt' => $i,
                'attempts' => $attempts,
            ]);

            try {
                $this->connectInternal(false, true);
                $this->warn('Reconnected to the broker.');

                return;
            } catch (ConnectingToBrokerFailedException $e) {
                if ($i === $attempts) {
                    $this->warn('Giving up after {attempts} reconnect attempts: {error}', [
                        'attempts' => $attempts,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }

                $this->pause($delay / 1000);
            }
        }
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

            $this->waitForData($remaining);
        }

        return $result;
    }

    /**
     * Wait until the socket is readable or $seconds pass. A false return from
     * stream_select() (e.g. interrupted by a signal) just ends the wait.
     *
     * @param  float  $seconds
     * @return void
     */
    protected function waitForData($seconds)
    {
        if (! is_resource($this->socket)) {
            return;
        }

        $read = [$this->socket];
        $write = null;
        $except = null;
        $whole = (int) floor($seconds);

        @stream_select($read, $write, $except, $whole, (int) (($seconds - $whole) * 1000000));
    }

    /**
     * Sleep, but return early once interrupt() is called.
     *
     * @param  float  $seconds
     * @return void
     */
    protected function pause($seconds)
    {
        $until = microtime(true) + $seconds;

        while (! $this->stopped && ($left = $until - microtime(true)) > 0) {
            usleep((int) (min($left, 0.05) * 1000000));
        }
    }

    /**
     * @param  string  $message
     * @param  array<string, mixed>  $context
     * @return void
     */
    protected function warn($message, array $context = [])
    {
        if ($this->log !== null) {
            $this->log->warning('['.$this->getHost().':'.$this->getPort().'] ['.$this->getClientId().'] '.$message, $context);
        }
    }
}
