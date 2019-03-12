<?php

namespace SitPHP\Services;

defined('SERVICE_PROVIDER') or define('SERVICE_PROVIDER', ServiceProvider::class);
defined('DOUBLE_PROVIDER') or define('DOUBLE_PROVIDER', \Doublit\Doublit::class);

use \Doublit\Lib\DoubleStub;

trait Service
{
    private static $service_initialized = false;
    private static $internal_services = [
        'service_provider' => SERVICE_PROVIDER,
        'double_provider' => DOUBLE_PROVIDER,
    ];
    private static $overwritten_services = [];

    /**
     *  Register class services
     */
    private static function initializeServices(){
        if(self::$service_initialized){
            return;
        }
        self::$service_initialized = true;
        if(!isset(self::$services)){
            return;
        }
        foreach (self::$services as $name => $class){
            self::$internal_services[$name] = $class;
        }
    }

    /**
     * Return service class
     *
     * @param string $name
     * @return mixed|null
     */
    private static function getServiceClass(string $name){
        self::initializeServices();
        /** @var ServiceProvider $service_provider */
        $service_provider = self::$overwritten_services['service_provider'] ?? self::$internal_services['service_provider'] ?? null;
        if($service_provider === null){
            throw new \RuntimeException('Undefined required "service_provider" service.');
        }
        $service = self::$overwritten_services[$name] ?? self::$internal_services[$name] ?? $service_provider::getService($name, self::class) ?? null;
        if($service === null) {
            throw new \InvalidArgumentException('Service "'.$name.'" not found');
        }

        return $service;
    }

    /**
     * Return a new service instance
     *
     * @param string $name
     * @param array|null $params
     * @return mixed
     */
    private static function getServiceInstance(string $name, array $params = null){
        self::initializeServices();
        $service = self::getServiceClass($name);
        if(isset($params)){
            return new $service(...$params);
        }
        return new $service();
    }

    /**
     * Overwrite service class
     *
     * @param string $name
     * @param string $class
     */
    static function setServiceClass(string $name, string $class){
        self::initializeServices();
        self::$overwritten_services[$name] = $class;
    }

    /**
     * Remove service definition
     *
     * @param string $name
     */
    static function removeService(string $name){
        self::$internal_services[$name] = null;
        self::$overwritten_services[$name] = null;
    }


    /**
     * Return a mock double class
     *
     * @param string $name
     * @return mixed
     */
    static function mockService(string $name){
        self::initializeServices();
        $double_provider = self::getServiceClass('double_provider');
        if($double_provider === null){
            throw new \RuntimeException('Undefined "double_provider" service. Should be defined.');
        }

        $service = self::getServiceClass($name);
        $mock = $double_provider::mock($service)->getClass();
        self::setServiceClass($name, $mock);
        return $mock;
    }

    /**
     * Return a dummy double class
     *
     * @param $name
     * @return DoubleStub
     */
    static function dummyService(string $name){
        self::initializeServices();
        $double_provider = self::getServiceClass('double_provider');
        if($double_provider === null){
            throw new \RuntimeException('Undefined "double_provider" service. Should be defined.');
        }

        $service = self::getServiceClass($name);
        $dummy = $double_provider::dummy($service)->getClass();
        self::setServiceClass($name, $dummy);
        return $dummy;
    }

    /**
     * Reset class service to original definition
     *
     * @param string $name
     */
    static function resetService(string $name){
        self::$overwritten_services[$name] = null;
    }
}