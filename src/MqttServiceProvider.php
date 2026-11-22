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
        $this->mergeDefaultConfig();

        $this->app->singleton(MqttManager::class, function () {
            return new MqttManager();
        });

        // The 'Mqtt' facade resolves the manager; unknown methods are proxied
        // to the default connection so existing calls keep working.
        $this->app->alias(MqttManager::class, 'Mqtt');
    }

    /**
     * Merge the package defaults into the app's config, nested arrays
     * included, so a config file published by an older version still gets new
     * keys such as `tls.enabled`. Skipped when the config is cached.
     *
     * @return void
     */
    protected function mergeDefaultConfig()
    {
        if ($this->app instanceof \Illuminate\Contracts\Foundation\CachesConfiguration
            && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $defaults = require __DIR__.'/config/mqtt.php';

        $config->set('mqtt', array_replace_recursive($defaults, (array) $config->get('mqtt', [])));
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
