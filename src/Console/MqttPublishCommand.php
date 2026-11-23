<?php

namespace Salman\Mqtt\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Salman\Mqtt\Exceptions\MqttConnectionException;
use Salman\Mqtt\MqttManager;

class MqttPublishCommand extends Command
{
    /** @var string */
    protected $signature = 'mqtt:publish
        {topic : The topic to publish to}
        {message : The message payload}
        {--connection= : The MQTT connection to use}
        {--client-id= : An explicit client id}
        {--retain : Set the retain flag}
        {--no-retain : Clear the retain flag (overrides the configured default)}
        {--qos= : The quality of service level (0, 1 or 2)}';

    /** @var string */
    protected $description = 'Publish a message to an MQTT topic';

    /**
     * @return int
     */
    public function handle(MqttManager $manager)
    {
        $topic = $this->argument('topic');
        $qos = $this->option('qos');

        if ($this->option('retain') && $this->option('no-retain')) {
            $this->error('Use either --retain or --no-retain, not both.');

            return self::FAILURE;
        }

        $retain = $this->option('retain') ? 1 : ($this->option('no-retain') ? 0 : null);

        try {
            $ok = $manager->connection($this->option('connection'))->ConnectAndPublish(
                $topic,
                $this->argument('message'),
                $this->option('client-id'),
                $retain,
                $qos
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (MqttConnectionException $e) {
            $this->error("Failed to publish to [{$topic}]: ".$e->getMessage());

            return self::FAILURE;
        }

        if ($ok) {
            $this->info("Published to [{$topic}].");

            return self::SUCCESS;
        }

        $this->error("Failed to publish to [{$topic}]. Check the log for the broker error.");

        return self::FAILURE;
    }
}
