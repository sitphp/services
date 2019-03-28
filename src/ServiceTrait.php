<?php

namespace SitPHP\Services;

defined('SERVICE_PROVIDER') or define('SERVICE_PROVIDER', ServiceProvider::class);
defined('DOUBLE_PROVIDER') or define('DOUBLE_PROVIDER', \Doublit\Doublit::class);

use \Doublit\Lib\DoubleStub;

trait ServiceTrait
{
    private static $service_initialized = false;
    /** @var \Doublit\Doublit $double_provider */
    private static $double_provider = DOUBLE_PROVIDER;
    /** @var ServiceProvider $service_provider */
    private static $service_provider = SERVICE_PROVIDER;
    private static $overwritten_services = [];
    private static $doubled_services = [];


    /**
     * Return service class
     *
     * @param string $name
     * @return mixed|null
     */
    private static function getServiceClass(string $name)
    {
        $service = self::$doubled_services[$name] ?? self::$overwritten_services[$name] ?? self::$services[$name] ?? self::$service_provider::getService($name) ?? null;
        if ($service === null) {
            throw new \InvalidArgumentException('Undefined service "' . $name . '".');
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
    private static function getServiceInstance(string $name, array $params = null)
    {
        $service = self::getServiceClass($name);
        if (isset($params)) {
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
    static function overwriteService(string $name, string $class)
    {
        self::restoreService($name);
        self::$overwritten_services[$name] = $class;
    }

    /**
     * Return a mock double class
     *
     * @param string $name
     * @return DoubleStub
     * @throws \ReflectionException
     */
    static function mockService(string $name)
    {
        self::restoreService($name);
        $service = self::getServiceClass($name);
        $mock = self::$double_provider::mock($service)->getClass();
        return self::$doubled_services[$name] = $mock;
    }

    /**
     * Return a dummy double class
     *
     * @param $name
     * @return DoubleStub
     * @throws \ReflectionException
     */
    static function dummyService(string $name)
    {
        self::restoreService($name);
        $service = self::getServiceClass($name);
        $dummy = self::$double_provider::dummy($service)->getClass();
        return self::$doubled_services[$name] = $dummy;
    }

    /**
     * Restore service double
     *
     * @param string $name
     */
    static function restoreService(string $name){
        self::$doubled_services[$name] = null;

    }

    /**
     * Remove all services doubles made via "dummyService" and "mockService" methods
     */
    static function restoreAllServices(){
        self::$doubled_services = [];
    }

    /**
     * Remove overwritten service via "overwriteService"
     *
     * @param string $name
     */
    static function resetService(string $name)
    {
        self::$overwritten_services[$name] = null;
    }

    /**
     * Remove all overwritten services via "overwriteService"
     */
    static function resetAllServices(){
        self::restoreAllServices();
        self::$overwritten_services = [];
    }
}