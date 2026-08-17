<?php

namespace Salman\Mqtt;

use Illuminate\Support\ServiceProvider;
use Salman\Mqtt\Console\MqttPublishCommand;
use Salman\Mqtt\Console\MqttSubscribeCommand;
use Salman\Mqtt\Notifications\MqttChannel;

class MqttServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/config/mqtt.php', 'mqtt');
        $this->publishes([
            __DIR__.'/config/mqtt.php' => config_path('mqtt.php'),
        ], 'mqtt-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MqttPublishCommand::class,
                MqttSubscribeCommand::class,
            ]);
        }

        $this->registerNotificationChannel();
    }

    public function register()
    {
        $this->app->singleton(MqttManager::class, function () {
            return new MqttManager();
        });

        // The 'Mqtt' facade resolves the manager; unknown methods are proxied
        // to the default connection so existing calls keep working.
        $this->app->alias(MqttManager::class, 'Mqtt');
    }

    /**
     * Register the "mqtt" notification channel when notifications are available.
     *
     * @return void
     */
    protected function registerNotificationChannel()
    {
        if (! class_exists(\Illuminate\Notifications\ChannelManager::class)) {
            return;
        }

        $this->callAfterResolving(
            \Illuminate\Notifications\ChannelManager::class,
            function ($service, $app) {
                $service->extend('mqtt', function ($app) {
                    return new MqttChannel($app->make(MqttManager::class));
                });
            }
        );
    }

    /**
     * @return array
     */
    public function provides()
    {
        return [MqttManager::class, 'Mqtt'];
    }
}
