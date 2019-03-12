<?php

namespace SitPHP\Services;


abstract class Decorator
{
    static function initialize($class){
        if(!property_exists(static::class, 'decorated')){
            throw new \RuntimeException('Decorator "'.static::class.'" could not be set up : static $decorated property is not declared');
        }
        static::$decorated = $class;
    }
}