<?php

namespace Salman\Mqtt\Console;

use Illuminate\Console\Command;
use Salman\Mqtt\MqttManager;

class MqttSubscribeCommand extends Command
{
    /** @var string */
    protected $signature = 'mqtt:subscribe
        {topic* : One or more topics to subscribe to}
        {--connection= : The MQTT connection to use}
        {--client-id= : An explicit client id}
        {--qos= : The quality of service level (0, 1 or 2)}';

    /** @var string */
    protected $description = 'Subscribe to one or more MQTT topics and print received messages';

    /**
     * @return int
     */
    public function handle(MqttManager $manager)
    {
        $topics = $this->argument('topic');
        $connection = $manager->connection($this->option('connection'));
        $qos = $this->option('qos');

        $this->registerSignalHandlers($connection);

        $this->info('Subscribing to: '.implode(', ', $topics).' (press Ctrl+C to stop)');

        $ok = $connection->ConnectAndSubscribe(
            $topics,
            function ($topic, $message) {
                $this->line('<fg=green>['.$topic.']</> '.$message);
            },
            $this->option('client-id'),
            $qos === null ? null : (int) $qos
        );

        if (! $ok) {
            $this->error('Failed to subscribe.');

            return self::FAILURE;
        }

        $this->info('Stopped MQTT subscription.');

        return self::SUCCESS;
    }

    /**
     * On SIGINT / SIGTERM, interrupt the subscribe loop so the client
     * disconnects cleanly (requires the pcntl extension).
     *
     * @param  mixed  $connection
     * @return void
     */
    protected function registerSignalHandlers($connection)
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function () use ($connection) {
            $this->newLine();
            $this->info('Stopping MQTT subscription...');
            $connection->interrupt();
        };

        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }
}
