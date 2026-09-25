<?php

namespace Salman\Mqtt\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Salman\Mqtt\Facades\Mqtt;
use Salman\Mqtt\MqttClass\Mqtt as MqttClient;

/**
 * True async end-to-end: Mqtt::queue() dispatches a job onto the database
 * queue, a worker processes it, and the job publishes to a real broker that a
 * forked subscriber receives from. Skipped unless MQTT_E2E=1 with a broker on
 * 127.0.0.1:1883; requires the pcntl extension.
 */
class BrokerQueuedPublishTest extends TestCase
{
    /** @var string */
    private $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('MQTT_E2E') !== '1') {
            $this->markTestSkipped('Set MQTT_E2E=1 with a broker on 127.0.0.1:1883 to run integration tests.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for the queued round-trip test.');
        }

        // Use a file-backed sqlite database so the queued job survives across
        // the dispatch and the worker run.
        $this->dbFile = tempnam(sys_get_temp_dir(), 'mqttq-').'.sqlite';
        touch($this->dbFile);

        config(['database.connections.mqtt_e2e' => [
            'driver' => 'sqlite',
            'database' => $this->dbFile,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        config(['database.default' => 'mqtt_e2e']);
        config(['queue.default' => 'database']);

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    protected function tearDown(): void
    {
        if ($this->dbFile && file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }

        parent::tearDown();
    }

    public function test_a_queued_publish_is_delivered_to_a_subscriber_by_a_worker()
    {
        $topic = 'laravel-mqtt/queued/'.uniqid();
        $payload = 'queued-'.uniqid();
        $file = tempnam(sys_get_temp_dir(), 'mqtt-q-e2e-');

        $pid = pcntl_fork();

        if ($pid === 0) {
            // Child: subscribe and record the first message, with a safety timeout.
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () {
                exit(1);
            });
            pcntl_alarm(15);

            (new MqttClient())->ConnectAndSubscribe($topic, function ($topic, $message) use ($file) {
                file_put_contents($file, $message);
                exit(0);
            });

            exit(0);
        }

        // Parent: let the subscriber connect, then queue the publish.
        usleep(2000000);

        Mqtt::queue($topic, $payload);

        // The job must be sitting on the queue, not have run inline.
        $this->assertSame(1, DB::table('jobs')->count(), 'The publish job was not queued.');

        // Run a worker to process exactly the queued job.
        Artisan::call('queue:work', [
            '--once' => true,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $this->assertSame(0, DB::table('jobs')->count(), 'The queued job was not processed.');

        pcntl_waitpid($pid, $status);

        $received = file_get_contents($file);
        @unlink($file);

        $this->assertSame($payload, $received, 'The subscriber did not receive the queued message.');
    }
}
