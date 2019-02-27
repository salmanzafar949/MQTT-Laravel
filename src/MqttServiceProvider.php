<?php
/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 1:34 PM
 */

namespace Salman\Mqtt;

use Illuminate\Support\ServiceProvider;
use Salman\Mqtt\MqttClass\Mqtt;

class MqttServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/config/mqtt.php','mqtt');
        $this->publishes([
            __DIR__.'/config/mqtt.php' => config_path('mqtt.php'),
        ]);
    }

    public function register()
    {
        $this->app->singleton('Mqtt',function (){

            return new Mqtt();
        });
    }

    /**
     * @return array
     */
    public function provides()
    {
        return array('Mqtt');
    }
}
