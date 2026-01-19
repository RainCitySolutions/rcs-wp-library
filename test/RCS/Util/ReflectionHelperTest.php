<?php
declare(strict_types=1);
namespace RCS\Util;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReflectionHelper::class)]
final class ReflectionHelperTest extends TestCase
{
    private object $fixture;

    protected function setUp(): void
    {
        $this->fixture = new class {
            private int $privateValue = 10;                     // @phpstan-ignore property.onlyWritten
            protected string $protectedValue = 'secret';
            public static string $staticValue = 'staticValue';

            private function privateMethod(int $x): int // NOSONAR - need private method, @phpstan-ignore method.unused
            {
                return $x * 2;
            }
        };
    }

    public function testGetAndSetObjectProperty(): void
    {
        // initial get
        $initial = ReflectionHelper::getObjectProperty(
            $this->fixture::class,
            'privateValue',
            $this->fixture
            );
        self::assertSame(10, $initial);

        // set new value
        ReflectionHelper::setObjectProperty(
            $this->fixture::class,
            'privateValue',
            99,
            $this->fixture
            );
        $after = ReflectionHelper::getObjectProperty(
            $this->fixture::class,
            'privateValue',
            $this->fixture
            );
        self::assertSame(99, $after);
    }

    public function testGetAndSetClassProperty(): void
    {
        // static get
        $value = ReflectionHelper::getClassProperty(
            $this->fixture::class,
            'staticValue'
            );
        self::assertSame('staticValue', $value);

        // static set
        ReflectionHelper::setClassProperty(
            $this->fixture::class,
            'staticValue',
            'newStatic'
            );
        $newValue = ReflectionHelper::getClassProperty(
            $this->fixture::class,
            'staticValue'
            );
        self::assertSame('newStatic', $newValue);
    }

    public function testInvokePrivateMethod(): void
    {
        $result = ReflectionHelper::invokeObjectMethod(
            $this->fixture::class,
            $this->fixture,
            'privateMethod',
            5
            );
        self::assertSame(10, $result);
    }

    public function testGetPropertyType(): void
    {
        $type = ReflectionHelper::getPropertyType(
            $this->fixture::class,
            'privateValue'
            );
        self::assertSame('int', $type);

        $nonExistent = ReflectionHelper::getPropertyType(
            $this->fixture::class,
            'nonexistent'
            );
        self::assertNull($nonExistent);
    }

    public function testGetNameForUnionType(): void
    {
        $unionPropObj = new class {
            private int|string $unionProp = 5;  // @phpstan-ignore property.unusedType, property.onlyWritten
        };

        $refClass = new \ReflectionClass($unionPropObj);
        $refProp = $refClass->getProperty('unionProp');
        $type = $refProp->getType();

        $name = ReflectionHelper::getNameForType($type);
        // Should return first type based on the connical order defined by PHP (int|string => string)
        self::assertSame('string', $name);
    }

    public function testNonExistentClassReturnsNull(): void
    {
        $value = ReflectionHelper::getClassProperty(
            'NonExistentClass',
            'prop'
            );
        self::assertNull($value);

        $type = ReflectionHelper::getPropertyType(
            'NonExistentClass',
            'prop'
            );
        self::assertNull($type);
    }
}
