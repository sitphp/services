<?php

namespace SitPHP\Services\Tests;

use Doublit\Lib\DoubleInterface;
use Doublit\TestCase;

class ServiceTraitTest extends TestCase
{

    /*
     * Test standard class
     */
    function testGetServiceShouldReturnService(){
        $this->assertEquals( \stdClass::class, Service::testGetMyService());
    }
    function testSetServiceShouldChangeExistingService(){
        Service::setServiceClass('my_service', self::class);
        $this->assertEquals( self::class, Service::testGetMyService());
    }
    function testGetUndefinedServiceShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        $this->assertEquals( self::class, Service::testGetUndefinedService());
    }
    function testSetUndefinedServiceShouldCreateService(){
        Service::setServiceClass('undefined_service', self::class);
        $this->assertEquals(self::class, Service::testGetUndefinedService());
    }
    function testGetServiceInstanceShouldReturnServiceInstance(){
        Service::setServiceClass('my_service', self::class);
        $this->assertInstanceOf(self::class, Service::testGetServiceInstance());
    }
    function testGetServiceInstanceWithParamsRunConstructorWithParams(){
        Service::setServiceClass('my_service', self::class);
        $instance = Service::testGetServiceInstanceWithParams(['param']);
        $this->assertEquals('param', $instance->param);
    }

    /*
     * Test extended class
     */
    function testExtendedServiceShouldNotOverrideParentService(){
        Service::resetService('my_service');
        $this->assertEquals(\stdClass::class, ExtendedService::testGetMyExtendedService());
        $this->assertEquals(\stdClass::class, Service::testGetMyService());
    }
    function testExtendedGetServiceShouldReturnService(){
        $this->assertEquals( \stdClass::class, ExtendedService::testGetMyExtendedService());
    }
    function testExtendedGetParentServiceShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        $this->assertEquals( self::class, ExtendedService::testGetParentService());
    }
    function testExtendedSetServiceWithSameParentNameShouldReturnProperService(){
        Service::setServiceClass('my_service', \stdClass::class);
        ExtendedService::setServiceClass('my_service', self::class);
        $this->assertEquals(self::class, ExtendedService::testGetParentService());
        $this->assertEquals(\stdClass::class, Service::testGetMyService());
    }


    /*
     * Test extended class without $services
     */
    function testGetParentServiceShouldReturnParentService(){
        Service::setServiceClass('my_service', \stdClass::class);
        $this->assertEquals(\stdClass::class, NoService::testGetMyParentService());
    }
    function testGetUndefinedServiceDefinedInParentShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        NoService::testGetMyService();
    }
    function testSetServiceShouldCreateService(){
        NoService::setServiceClass('my_service', \stdClass::class);
        $this->assertEquals(\stdClass::class, NoService::testGetMyService());
    }

    /*
     * Test doubles
     */
    function testMockServiceShouldReturnInstanceOfDouble(){
        Service::resetService('my_service');
        $mock = Service::mockService('my_service');
        $this->assertTrue(is_subclass_of($mock,DoubleInterface::class));
    }
    function testDumServiceShouldReturnInstanceOfDouble(){
        Service::resetService('my_service');
        $dummy = Service::dummyService('my_service');
        $this->assertTrue(is_subclass_of($dummy,DoubleInterface::class));
    }
    function testGetServiceAfterMockShouldReturnMock(){
        Service::resetService('my_service');
        $mock = Service::mockService('my_service');
        $this->assertEquals($mock, Service::testGetMyService());
    }
    function testGetServiceAfterDumShouldReturnDum(){
        Service::resetService('my_service');
        $dummy = Service::mockService('my_service');
        $this->assertEquals($dummy, Service::testGetMyService());
    }

    /*
     * Test service provider
     */
    function testServiceProviderIsCalledWhenCallingUndefinedService(){
        Service::resetService('undefined_service');
        $dummy = Service::dummyService('service_provider');
        $dummy::_method('getService')
            ->stub(\stdClass::class)
            ->count(1);
        $this->assertEquals(\stdClass::class, Service::testGetUndefinedService());
    }
}
class SimpleClass{
    public $param;

    function __construct($param)
    {
        $this->param = $param;
    }
}
class Service{
    use \SitPHP\Services\Service;

    private static $services = [
        'my_service' => \stdClass::class,
        'mu_other_service' => SimpleClass::class
    ];

    static function testGetMyService(){
        return self::getServiceClass('my_service');
    }
    static function testGetUndefinedService(){
        return self::getServiceClass('undefined_service');
    }
    static function testGetServiceInstance(){
        return self::getServiceInstance('my_service');
    }
    static function testGetServiceInstanceWithParams(array $params){
        return self::getServiceInstance('mu_other_service', $params);
    }
}

class ExtendedService extends Service {
    use \SitPHP\Services\Service;

    private static $services = [
        'my_extended_service' => \stdClass::class
    ];

    static function testGetMyExtendedService(){
        return self::getServiceClass('my_extended_service');
    }
    static function testGetParentService(){
        return self::getServiceClass('my_service');
    }
}

class NoService extends Service
{
    use \SitPHP\Services\Service;

    public static function testGetMyParentService()
    {
        return parent::testGetMyService();
    }
    public static function testGetMyService(){
        return self::getServiceClass('my_service');
    }
}