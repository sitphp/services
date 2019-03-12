<?php

namespace SitPHP\Services;

abstract class Initializer
{

    static function initialize($service_class){
        if(!property_exists(static::class, 'service')){
            throw new \RuntimeException('Initializer "'.static::class.'" could not be set up : static $service property is not declared');
        }
        static::$service = $service_class;
    }
}