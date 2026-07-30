<?php

/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/27/19
 * Time: 12:03 PM
 */

namespace Salman\Mqtt\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool ConnectAndPublish(string $topic, string $msg, string|int|null $client_id = null, int|null $retain = null)
 * @method static bool ConnectAndSubscribe(string|array $topic, callable $proc, string|int|null $client_id = null)
 * @method static bool connectAndPublish(string $topic, string $msg, string|int|null $client_id = null, int|null $retain = null)
 * @method static bool connectAndSubscribe(string|array $topic, callable $proc, string|int|null $client_id = null)
 *
 * @see \Salman\Mqtt\MqttClass\Mqtt
 */
class Mqtt extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Mqtt';
    }
}
