<?php

namespace Salman\Mqtt\Console;

use Illuminate\Console\Command;
use Salman\Mqtt\MqttManager;

class MqttPublishCommand extends Command
{
    /** @var string */
    protected $signature = 'mqtt:publish
        {topic : The topic to publish to}
        {message : The message payload}
        {--connection= : The MQTT connection to use}
        {--client-id= : An explicit client id}
        {--retain : Set the retain flag}';

    /** @var string */
    protected $description = 'Publish a message to an MQTT topic';

    /**
     * @return int
     */
    public function handle(MqttManager $manager)
    {
        $topic = $this->argument('topic');

        $ok = $manager->connection($this->option('connection'))->ConnectAndPublish(
            $topic,
            $this->argument('message'),
            $this->option('client-id'),
            $this->option('retain') ? 1 : null
        );

        if ($ok) {
            $this->info("Published to [{$topic}].");

            return self::SUCCESS;
        }

        $this->error("Failed to publish to [{$topic}].");

        return self::FAILURE;
    }
}
