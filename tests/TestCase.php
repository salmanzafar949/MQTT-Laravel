<?php

namespace Salman\Mqtt\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Salman\Mqtt\MqttServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Register the package service provider so it is booted for every test.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            MqttServiceProvider::class,
        ];
    }

    /**
     * Register the package facade alias.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Mqtt' => \Salman\Mqtt\Facades\Mqtt::class,
        ];
    }
}
