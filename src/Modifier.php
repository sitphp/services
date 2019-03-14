<?php

namespace SitPHP\Services;


abstract class Modifier
{
    static function initialize($class){
        if(!property_exists(static::class, 'modified')){
            throw new \RuntimeException('Modifier "'.static::class.'" could not be set up : static $modified property is not declared');
        }
        static::$modified = $class;
    }
}