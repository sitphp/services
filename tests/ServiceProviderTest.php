<?php

namespace SitPHP\Services\Tests;

use Doublit\Doublit;
use Doublit\Lib\DoubleStub;
use SitPHP\Helpers\Collection;
use SitPHP\Services\Modifier;
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
     * Test modifier
     */
    function testModifiedServiceShouldReturnModifier()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addModifier('my_service', MyModifier::class);
        $this->assertEquals(MyModifier::class, ServiceProvider::getService('my_service'));
    }

    function testArrayModifiedServiceShouldReturnCorrectModifier()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addModifier('my_service', [MyModifier::class, MyOtherModifier::class]);
        $this->assertEquals(MyOtherModifier::class, ServiceProvider::getService('my_service'));
    }

    function testArrayPrioritizedModifiedServiceShouldReturnCorrectModifier()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addModifier('my_service', [[MyModifier::class, 2], [MyOtherModifier::class, 1]]);
        $this->assertEquals(MyOtherModifier::class, ServiceProvider::getService('my_service'));
    }

    function testArrayLabelPrioritizedModifiedServiceShouldReturnCorrectModifier()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addModifier('my_service', [['class' => MyModifier::class, 'priority' => 2], ['class' => MyOtherModifier::class, 'priority' => 1]]);
        $this->assertEquals(MyOtherModifier::class, ServiceProvider::getService('my_service'));
    }

    function testModifiersShouldBeAppliedByPriority()
    {
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addModifier('my_service', MyOtherModifier::class, 1);
        ServiceProvider::addModifier('my_service', MyModifier::class, 2);
        ServiceProvider::addModifier('my_service', MyOtherOtherModifier::class, 1);
        $this->assertEquals(MyOtherOtherModifier::class, ServiceProvider::getService('my_service'));
    }

    function testModifyingUndefinedServiceShouldWork()
    {
        ServiceProvider::addModifier('my_service', MyModifier::class);
        $this->assertNull(ServiceProvider::getService('my_service'));
        ServiceProvider::setService('my_service', \stdClass::class);
        $this->assertEquals(MyModifier::class, ServiceProvider::getService('my_service'));
    }

    function testGetServiceWithNonModifierClassShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addModifier('my_service', \stdClass::class);
        ServiceProvider::getService('my_service');
    }

    function testGetServiceWithInvalidModifierShouldFail()
    {
        $this->expectException(\RuntimeException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::addModifier('my_service', MyInvalidModifier::class);
        ServiceProvider::getService('my_service');
    }

    function testAddingInvalidModifierTypeShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceProvider::addModifier('my_service', new \stdClass());
    }

    function testAddingModifierToAlreadyRetrievedServiceShouldFail()
    {
        $this->expectException(\LogicException::class);
        ServiceProvider::setService('my_service', \stdClass::class);
        ServiceProvider::getService('my_service');
        ServiceProvider::addModifier('my_service', MyOtherModifier::class);
    }

    function testAddingSameModifierMultipleServicesShouldFail()
    {
        $this->expectException(\LogicException::class);

        ServiceProvider::setService('my_service_1', \stdClass::class);
        ServiceProvider::setService('my_service_2', \stdClass::class);
        ServiceProvider::addModifier('my_service_1', MyModifier::class);
        ServiceProvider::addModifier('my_service_2', MyModifier::class);
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
        ServiceProvider::addInitializer('my_service', [MyInitializer::class]);
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


    /*
     * Test unused private methods
     */
    function testGetAllModifiers(){
        /** @var ServiceProvider & DoubleStub $double */
        $double = Doublit::mock(ServiceProvider::class)->getClass();
        $double::addModifier('my_service',MyModifier::class);
        $double::addInitializer('my_service',MyInitializer::class);

        $class = new \ReflectionClass($double);
        $get_all_modifiers = $class->getMethod('getAllModifiers');
        $get_all_modifiers->setAccessible(true);
        $get_all_initializers = $class->getMethod('getAllInitializers');
        $get_all_initializers->setAccessible(true);

        $this->assertEquals(['my_service'=> new Collection([['class'=>MyModifier::class,'priority'=>null]])], $get_all_modifiers->invoke($class));
        $this->assertEquals(['my_service'=> new Collection([['class'=>MyInitializer::class,'priority'=>null]])], $get_all_initializers->invoke($class));
    }

}

class MyModifier extends Modifier
{
    protected static $modified;
}

class MyOtherModifier extends Modifier
{
    protected static $modified;
}

class MyOtherOtherModifier extends Modifier
{
    protected static $modified;
}

class MyInvalidModifier extends Modifier
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