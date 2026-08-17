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
        {--client-id= : An explicit client id}';

    /** @var string */
    protected $description = 'Subscribe to one or more MQTT topics and print received messages';

    /**
     * @return int
     */
    public function handle(MqttManager $manager)
    {
        $topics = $this->argument('topic');

        $this->registerSignalHandlers();

        $this->info('Subscribing to: '.implode(', ', $topics).' (press Ctrl+C to stop)');

        $manager->connection($this->option('connection'))->ConnectAndSubscribe(
            $topics,
            function ($topic, $message) {
                $this->line('<fg=green>['.$topic.']</> '.$message);
            },
            $this->option('client-id')
        );

        return self::SUCCESS;
    }

    /**
     * Stop cleanly on SIGINT / SIGTERM when the pcntl extension is available.
     *
     * @return void
     */
    protected function registerSignalHandlers()
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function () {
            $this->newLine();
            $this->info('Stopping MQTT subscription.');
            exit(self::SUCCESS);
        };

        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }
}
