<?php
declare(strict_types=1);
namespace RCS\Traits;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RCS\Traits\Test\TestExtendedNonSingleton;
use RCS\Traits\Test\TestSingletonTrait;
use RCS\Util\ReflectionHelper;

#[CoversClass(\RCS\Traits\SingletonTrait::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class SingletonTraitTest extends TestCase
{
    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        ReflectionHelper::setClassProperty(TestSingletonTrait::class, 'instance', null);
    }

//     public function testClone (): void
//     {
//         $this->markTestSkipped('Test depended on PHPUnit throwning an exception on trigger_error()');

//         $this->expectExceptionMessage('__clone should not be called on singleton class');
//         $obj = TestSingletonTrait::init();
//         $obj->__clone();
//     }

//     public function testWakeup (): void {
//         $this->markTestSkipped('Test depended on PHPUnit throwning an exception on trigger_error()');

//         $this->expectExceptionMessage('__wakeup should not be called on singleton class');
//         $obj = TestSingletonTrait::init();
//         $obj->__wakeup();
//     }

    public function testInstance(): void {
        $obj1 = TestSingletonTrait::init();
        $obj2 = TestSingletonTrait::init();

        self::assertEquals($obj1, $obj2);
    }

    public function testInstance_initializeInstance(): void {
        $obj = TestSingletonTrait::init();

        self::assertTrue($obj->initInstCalled);
    }

    public function testInstance_extendedClass(): void {
        $obj1 = TestExtendedNonSingleton::init();
        $obj2 = TestExtendedNonSingleton::init();

        self::assertEquals($obj1, $obj2);
    }
}
