<?php
declare(strict_types=1);
namespace RCS\Json;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesMethod;
use RCS\Util\ReflectionHelper;

#[CoversClass(\RCS\Json\JsonEntity::class)]
#[UsesMethod(\RCS\Json\FieldPropertyEntry::class, '__construct')]
#[UsesMethod(\RCS\Json\FieldPropertyEntry::class, 'getField')]
#[UsesMethod(\RCS\Json\FieldPropertyEntry::class, 'getProperty')]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class JsonEntityTest extends TestCase
{
    private const FIELD_ID     = 'idField';
    private const FIELD_NAME   = 'nameField';
    private const FIELD_NUMBER = 'numberField';

    private const PROPERTY_ID     = 'idProp';
    private const PROPERTY_NAME   = 'nameProp';
    private const PROPERTY_NUMBER = 'numberProp';

    /** @var FieldPropertyEntry[] */
    public static array $testPropertyMap;

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUpBeforeClass()
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testPropertyMap = [
            new FieldPropertyEntry(self::FIELD_ID, self::PROPERTY_ID),
            new FieldPropertyEntry(self::FIELD_NUMBER, self::PROPERTY_NUMBER),
            new FieldPropertyEntry(self::FIELD_NAME, self::PROPERTY_NAME)
        ];
    }

    public function testGetJsonFields_noMap(): void
    {
        $obj = new EmptyMapEntityTestClass();

        self::assertEmpty($obj->getJsonFields());
    }

    public function testGetJsonFields_justProps(): void
    {
        $obj = new JustClassPropsTestClass();

        $fields = $obj->getJsonFields();

        self::assertNotEmpty($fields);
        self::assertContains('intVal', $fields);
        self::assertContains('strVal', $fields);
    }

    public function testGetJsonFields_withMap(): void
    {
        $obj = new ByNameEntityTestClass();

        $fields = $obj->getJsonFields();

        self::assertNotEmpty($fields);
        self::assertCount(count(self::$testPropertyMap), $fields);
        self::assertContains(self::FIELD_ID, $fields);
        self::assertContains(self::FIELD_NUMBER, $fields);
    }

    public function testGetJsonFields_propsAndMap(): void
    {
        $testObj = new class() extends JsonEntity
        {
            public int $intValue;
            public string $strValue;

            protected static function getFieldPropertyMap(): array
            {
                return [
                    new FieldPropertyEntry('jsonField', 'intValue')
                ];
            }
        };

        $fields = $testObj->getJsonFields();

        self::assertNotEmpty($fields);
        self::assertCount(2, $fields);
        self::assertContains('jsonField', $fields);
        self::assertContains('strValue', $fields);
    }

    public function testGetRenameMapping_noMap(): void
    {
        $obj = new EmptyMapEntityTestClass();

        $rename = $obj->getRenameMapping();

        $mappings = ReflectionHelper::getObjectProperty(get_class($rename), 'mapping', $rename);

        self::assertEmpty($mappings);
    }

    public function testGetRenameMapping_fieldMap(): void
    {
        $obj = new ByNameEntityTestClass();

        $rename = $obj->getRenameMapping();

        $mappings = ReflectionHelper::getObjectProperty(get_class($rename), 'mapping', $rename);

        self::assertNotEmpty($mappings);
        self::assertCount(count(self::$testPropertyMap), $mappings);

        for ($ndx = 0; $ndx < count($mappings); $ndx++) {
            $mapping = $mappings[$ndx];
            $fieldPropEntry = self::$testPropertyMap[$ndx];

            $clazz = ReflectionHelper::getObjectProperty(get_class($mapping), 'class', $mapping);
            $from  = ReflectionHelper::getObjectProperty(get_class($mapping), 'from', $mapping);
            $to    = ReflectionHelper::getObjectProperty(get_class($mapping), 'to', $mapping);

            self::assertEquals(get_class($obj), $clazz);
            self::assertEquals($fieldPropEntry->getField(), $from);
            self::assertEquals($fieldPropEntry->getProperty(), $to);
        }
    }

    public function testGetRenameMapping_fieldMapByIndex(): void
    {
        $obj = new ByIndexEntityTestClass();

        $rename = $obj->getRenameMapping();

        $mappings = ReflectionHelper::getObjectProperty(get_class($rename), 'mapping', $rename);

        self::assertNotEmpty($mappings);
        self::assertCount(count(self::$testPropertyMap), $mappings);

        for ($ndx = 0; $ndx < count($mappings); $ndx++) {
            $mapping = $mappings[$ndx];
            $fieldPropEntry = self::$testPropertyMap[$ndx];

            $clazz = ReflectionHelper::getObjectProperty(get_class($mapping), 'class', $mapping);
            $from  = ReflectionHelper::getObjectProperty(get_class($mapping), 'from', $mapping);
            $to    = ReflectionHelper::getObjectProperty(get_class($mapping), 'to', $mapping);

            self::assertEquals(get_class($obj), $clazz);
            self::assertEquals(strval($ndx), $from);
            self::assertEquals($fieldPropEntry->getProperty(), $to);
        }
    }
}

class EmptyMapEntityTestClass extends JsonEntity
{
}

class ByNameEntityTestClass extends JsonEntity
{
    /**
     * {@inheritDoc}
     * @see \RCS\Json\JsonEntity::getFieldPropertyMap()
     */
    protected static function getFieldPropertyMap(): array
    {
        return JsonEntityTest::$testPropertyMap;
    }
}

class ByIndexEntityTestClass extends JsonEntity
{
    /**
     * {@inheritDoc}
     * @see \RCS\Json\JsonEntity::getFieldPropertyMap()
     */
    protected static function getFieldPropertyMap(): array
    {
        return JsonEntityTest::$testPropertyMap;
    }

    /**
     * {@inheritDoc}
     * @see \RCS\Json\JsonEntity::isMapByIndex()
     */
    protected static function isMapByIndex(): bool
    {
        return true;
    }
}

class JustClassPropsTestClass extends JsonEntity
{
    public int $intVal;
    public string $strVal;
}
