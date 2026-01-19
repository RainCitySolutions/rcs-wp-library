<?php
declare(strict_types=1);
namespace RCS\Json;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RCS\Util\ReflectionHelper;

#[CoversClass(\RCS\Json\FieldPropertyEntry::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class FieldPropertyEntryTest extends TestCase
{
    private const TEST_FIELD = 'testField';
    private const TEST_PROPERTY = 'testProperty';

    public function testCtor(): void
    {
        $testObj = new FieldPropertyEntry(self::TEST_FIELD, self::TEST_PROPERTY);

        self::assertEquals(
            self::TEST_FIELD,
            ReflectionHelper::getObjectProperty(FieldPropertyEntry::class, 'field', $testObj)
            );

        self::assertEquals(
            self::TEST_PROPERTY,
            ReflectionHelper::getObjectProperty(FieldPropertyEntry::class, 'property', $testObj)
            );
    }

    public function testGetField(): void
    {
        $testObj = new FieldPropertyEntry(self::TEST_FIELD, '');

        self::assertEquals(
            self::TEST_FIELD,
            $testObj->getField()
            );
    }

    public function testGetProperty(): void
    {
        $testObj = new FieldPropertyEntry('', self::TEST_PROPERTY);

        self::assertEquals(
            self::TEST_PROPERTY,
            $testObj->getProperty()
            );
    }
}
