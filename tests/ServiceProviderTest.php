<?php

namespace SitPHP\Services\Tests;

use SitPHP\Services\Decorator;
use SitPHP\Services\Initializer;
use SitPHP\Services\ServiceProvider;
use Doublit\TestCase;

class ServiceProviderTest extends TestCase
{

    public function tearDown()
    {
        ServiceProvider::removeService('my_service');
        ServiceProvider::removeService('my_service_1');
        ServiceProvider::removeService('my_service_2');
    }

    /*
     * Test get/set/unset
     */
    function testSetServiceShouldCreateService()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        $this->assertEquals(\stdClass::class, ServiceProvider::getService('my_service'));
    }

    function testGetUndefinedServiceShouldReturnNull()
    {
        $this->assertNull(ServiceProvider::getService('undefined_service'));
    }

    function testGetServiceByTagShouldReturnTaggedServices()
    {
        ServiceProvider::setService('my_service_1', \stdClass::class, 'tag1');
        ServiceProvider::setService('my_service_2', \stdClass::class, ['tag1', 'tag2']);

        $tag1_services = ServiceProvider::getServicesByTag('tag1');
        $tag2_services = ServiceProvider::getServicesByTag('tag2');
        $this->assertCount(2, $tag1_services);
        $this->assertCount(1, $tag2_services);
        $this->assertEquals(\stdClass::class, $tag2_services[0]);
    }

    function testRemoveServiceShouldWork()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::removeService('my_service');
        $this->assertNull(ServiceProvider::getService('my_service'));
    }


    /*
     * Test decorator
     */
    function testDecoratedServiceShouldReturnDecorator()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addDecorator('my_service', MyDecorator::class);
        $this->assertEquals(MyDecorator::class, ServiceProvider::getService('my_service'));
    }

    function testArrayDecoratedServiceShouldReturnCorrectDecorator()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addDecorator('my_service', [MyDecorator::class, MyOtherDecorator::class]);
        $this->assertEquals(MyOtherDecorator::class, ServiceProvider::getService('my_service'));
    }

    function testArrayPrioritizedDecoratedServiceShouldReturnCorrectDecorator()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addDecorator('my_service', [[MyDecorator::class, 2], [MyOtherDecorator::class, 1]]);
        $this->assertEquals(MyOtherDecorator::class, ServiceProvider::getService('my_service'));
    }

    function testArrayLabelPrioritizedDecoratedServiceShouldReturnCorrectDecorator()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addDecorator('my_service', [['class' => MyDecorator::class, 'priority' => 2], ['class' => MyOtherDecorator::class, 'priority' => 1]]);
        $this->assertEquals(MyOtherDecorator::class, ServiceProvider::getService('my_service'));
    }

    function testDecoratorsShouldBeAppliedByPriority()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addDecorator('my_service', MyOtherDecorator::class, 1);
        ServiceProvider::addDecorator('my_service', MyDecorator::class, 2);
        ServiceProvider::addDecorator('my_service', MyOtherOtherDecorator::class, 1);
        $this->assertEquals(MyOtherOtherDecorator::class, ServiceProvider::getService('my_service'));
    }

    function testDecoratingUndefinedServiceShouldWork()
    {
        ServiceProvider::addDecorator('my_service', MyDecorator::class);
        $this->assertNull(ServiceProvider::getService('my_service'));
        ServiceProvider::setService('my_service', \stdClass::class);
        $this->assertEquals(MyDecorator::class, ServiceProvider::getService('my_service'));
    }

    function testGetServiceWithNonDecoratorClassShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addDecorator('my_service', \stdClass::class);
        ServiceProvider::getService('my_service');
    }

    function testGetServiceWithInvalidDecoratorShouldFail()
    {
        $this->expectException(\RuntimeException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addDecorator('my_service', MyInvalidDecorator::class);
        ServiceProvider::getService('my_service');
    }

    function testAddingInvalidDecoratorTypeShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceProvider::addDecorator('my_service', new \stdClass());
    }

    function testAddingDecoratorToAlreadyRetrievedServiceShouldFail()
    {
        $this->expectException(\LogicException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::getService('my_service');
        ServiceProvider::addDecorator('my_service', MyOtherDecorator::class);
    }

    function testAddingSameDecoratorMultipleServicesShouldFail()
    {
        $this->expectException(\LogicException::class);

        ServiceProvider::setService('my_service_1', \stdClass::class);
        ServiceProvider::setService('my_service_2', \stdClass::class);
        ServiceProvider::addDecorator('my_service_1', MyDecorator::class);
        ServiceProvider::addDecorator('my_service_2', MyDecorator::class);
    }

    /*
     * Test Initializers
     */
    function testInitializerMethodsShouldBeExecuted()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addInitializer('my_service', MyInitializer::class);
        ServiceProvider::getService('my_service');

        $this->assertTrue(MyInitializer::$setup);
        $this->assertTrue(MyInitializer::$register);
        $this->assertTrue(MyInitializer::$tweak);
    }

    function testInitializerMethodsShouldBeExecutedByPriority(){
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addInitializer('my_service', MyInitializer::class, 1);
        ServiceProvider::addInitializer('my_service', MyOtherInitializer::class, 2);
        ServiceProvider::getService('my_service');

        $this->assertTrue(MyInitializer::$setup);
        $this->assertTrue(MyInitializer::$register);
        $this->assertTrue(MyInitializer::$tweak);
    }

    function testArrayPrioritizedInitializersShouldBeExecutedByPriority()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addInitializer('my_service', [[MyInitializer::class, 1], ['class'=> MyOtherInitializer::class, 'priority' => 2]]);

        ServiceProvider::getService('my_service');

        $this->assertTrue(MyInitializer::$setup);
        $this->assertTrue(MyInitializer::$register);
        $this->assertTrue(MyInitializer::$tweak);
    }

    function testAddingInitializerToUndefinedServiceShouldWork()
    {
        ServiceProvider::addInitializer('my_service', MyInitializer::class);
        $this->assertNull(ServiceProvider::getService('my_service'));
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::getService('my_service');

        $this->assertTrue(MyInitializer::$setup);
        $this->assertTrue(MyInitializer::$register);
        $this->assertTrue(MyInitializer::$tweak);
    }


    function testAddingInvalidInitializerTypeShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceProvider::addInitializer('my_service', new \stdClass());
    }

    function testGetServiceWithNonInitializerClassShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addInitializer('my_service', \stdClass::class);
        ServiceProvider::getService('my_service');
    }

    function testGetServiceWithInvalidInitializerShouldFail()
    {
        $this->expectException(\RuntimeException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addInitializer('my_service', MyInvalidInitializer::class);
        ServiceProvider::getService('my_service');
    }

    function testAddingInitializerToAlreadyRetrievedServiceShouldFail()
    {
        $this->expectException(\LogicException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addInitializer('my_service', MyInitializer::class);
        ServiceProvider::getService('my_service');
        ServiceProvider::addInitializer('my_service', MyOtherInitializer::class);
    }
    function testAddingSameInitializerToMultipleServicesShouldFail(){
        $this->expectException(\LogicException::class);

        ServiceProvider::setService('my_service_1', \stdClass::class);
        ServiceProvider::setService('my_service_2', \stdClass::class);
        ServiceProvider::addInitializer('my_service_1', MyInitializer::class);
        ServiceProvider::addInitializer('my_service_2', MyInitializer::class);
    }

}

class MyDecorator extends Decorator
{
    protected static $decorated;
}

class MyOtherDecorator extends Decorator
{
    protected static $decorated;
}

class MyOtherOtherDecorator extends Decorator
{
    protected static $decorated;
}

class MyInvalidDecorator extends Decorator
{
}

class MyInitializer extends Initializer
{
    protected static $service;

    static $setup = false;
    static $register = false;
    static $tweak = false;

    static function setup(){
        self::$setup = true;
    }

    static function register(){
        self::$register = true;
    }

    static function tweak(){
        self::$tweak = true;
    }
}

class MyOtherInitializer extends Initializer{
    protected static $service;

    static function setup(){
        MyInitializer::$setup = false;
    }

    static function register(){
        MyInitializer::$register = false;
    }

    static function tweak(){
        MyInitializer::$tweak = false;
    }
}

class MyInvalidInitializer extends Initializer
{
}