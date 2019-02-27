<?php
/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/27/19
 * Time: 12:03 PM
 */

namespace Salman\Mqtt\Facades;

use Illuminate\Support\Facades\Facade;

class Mqtt extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Mqtt';
    }

}
