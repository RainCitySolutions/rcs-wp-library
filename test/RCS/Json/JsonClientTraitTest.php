<?php
declare(strict_types=1);
namespace RCS\Json;

use JsonMapper\JsonMapper;
use JsonMapper\Handler\FactoryRegistry;
use JsonMapper\Handler\PropertyMapper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesMethod;
use RCS\Json\Test\JsonClientTraitTestClass;
use RCS\Util\ReflectionHelper;


#[CoversClass(\RCS\Json\Test\JsonClientTraitTestClass::class)]
#[UsesMethod(\RCS\Json\FieldPropertyEntry::class, '__construct')]
#[UsesMethod(\RCS\Json\FieldPropertyEntry::class, 'getProperty')]
#[UsesMethod(\RCS\Json\JsonEntity::class, 'getRenameMapping')]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class JsonClientTraitTest extends TestCase
{
    private JsonClientTraitTestClass $testObj;
    /**
     *
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testObj = new JsonClientTraitTestClass();
    }

    public function testCtor_defaults(): void
    {
        self::assertNotNull($this->getClassFactoryRegistry($this->testObj));
    }

    public function testCtor_setRegistryFactory(): void
    {
        $testFactory = new FactoryRegistry();

        $localTestObj = new JsonClientTraitTestClass(
            null,
            600,
            $testFactory
            );

        self::assertSame(
            $testFactory,
            $this->getClassFactoryRegistry($localTestObj)
            );
    }

    public function testGetCacheKey(): void
    {
        $key = ReflectionHelper::invokeObjectMethod(
            get_class($this->testObj),
            $this->testObj,
            'getCacheKey',
            __FUNCTION__
            );

        self::assertNotNull($key);
        self::assertStringContainsString('JsonClientTraitTestClass', $key);
        self::assertStringContainsString(__FUNCTION__, $key);
        self::assertStringNotContainsString('\\', $key);
    }

    public function testProcessJsonResponse_notJson(): void
    {
        $result = ReflectionHelper::invokeObjectMethod(
            get_class($this->testObj),
            $this->testObj,
            'processJsonResponse',
            'Hello World!',
            new JsonEntityTestClass()
            );

        self::assertNull($result);
    }

    public function testProcessJsonResponse_singleObject(): void
    {
        list ($testJsonObj, $testJsonStr) = $this->generateJsonEntityObject();

        $result = ReflectionHelper::invokeObjectMethod(
            get_class($this->testObj),
            $this->testObj,
            'processJsonResponse',
            $testJsonStr,
            new JsonEntityTestClass()
            );

        self::assertNotNull($result);
        self::assertEquals($testJsonObj, $result);
    }

    public function testProcessJsonResponse_array(): void
    {
        $jsonObjArray = array();
        $jsonStrArray = array();

        $cnt = rand(1, 5);
        while ($cnt >= 1) {
            list ($testJsonObj, $testJsonStr) = $this->generateJsonEntityObject();

            array_push($jsonObjArray, $testJsonObj);
            array_push($jsonStrArray, $testJsonStr);

            $cnt--;
        }

        $result = ReflectionHelper::invokeObjectMethod(
            get_class($this->testObj),
            $this->testObj,
            'processJsonResponse',
            '['.join(', ', $jsonStrArray).']',
            new JsonEntityTestClass()
            );

        self::assertNotNull($result);
        self::assertIsArray($result);

        foreach ($result as $ndx => $entry) {
            self::assertEquals($jsonObjArray[$ndx], $entry);
        }
    }

    public function testProcessJsonResponse_listArray(): void
    {
        $jsonObjArray = array();
        $jsonStrArray = array();

        $cnt = rand(1, 5);
        while ($cnt >= 1) {
            list ($testJsonObj, $testJsonStr) = $this->generateJsonEntityList();

            array_push($jsonObjArray, $testJsonObj);
            array_push($jsonStrArray, $testJsonStr);

            $cnt--;
        }

        $result = ReflectionHelper::invokeObjectMethod(
            get_class($this->testObj),
            $this->testObj,
            'processJsonResponse',
            '['.join(', ', $jsonStrArray).']',
            new JsonEntityTestClass()
            );

        self::assertNotNull($result);
        self::assertIsArray($result);

        foreach ($result as $ndx => $entry) {
            self::assertEquals($jsonObjArray[$ndx], $entry);
        }
    }

    private function getClassFactoryRegistry(JsonClientTraitTestClass $testObj): ?FactoryRegistry   // @phpstan-ignore return.unusedType
    {
        $classFactoryRegistry = null;

        /** @var JsonMapper */
        $jsonMapper = ReflectionHelper::getObjectProperty(
            get_class($testObj),
            'mapper',
            $testObj
            );
        if (!is_null($jsonMapper)) {            // @phpstan-ignore function.impossibleType
            /** @var PropertyMapper */
            $propertyMapper = ReflectionHelper::getObjectProperty(
                JsonMapper::class,
                'propertyMapper',
                $jsonMapper
                );
            if (!is_null($propertyMapper)) {    // @phpstan-ignore function.impossibleType
                /** @var FactoryRegistry */
                $classFactoryRegistry = ReflectionHelper::getObjectProperty(
                    PropertyMapper::class,
                    'classFactoryRegistry',
                    $propertyMapper
                    );
            }
        }

        return $classFactoryRegistry;
    }

    /**
     *
     * @return array<mixed>
     */
    private function generateJsonEntityObject(): array
    {
        $obj = new JsonEntityTestClass();
        $obj->id = rand(1, 100);
        $obj->name = 'test-'.$obj->id;
        $obj->number = $obj->id * rand (1, 9);

        return array ($obj, json_encode($obj));
    }

    /**
     *
     * @return array<mixed>
     */
    private function generateJsonEntityList(): array
    {
        $obj = new JsonEntityTestClass();
        $obj->id = rand(1, 100);
        $obj->name = 'test-'.$obj->id;
        $obj->number = $obj->id * rand(2, 9);

        $list = array($obj->id, $obj->name, $obj->number);

        return array ($obj, json_encode($list));
    }
}

class JsonEntityTestClass extends JsonEntity
{
    public int $id;
    public string $name;
    public int $number;

    protected static function isMapByIndex(): bool
    {
        return true;
    }

    protected static function getFieldPropertyMap(): array
    {
        return [
            new FieldPropertyEntry('doesnotmatter1', 'id'),
            new FieldPropertyEntry('doesnotmatter2', 'name'),
            new FieldPropertyEntry('doesnotmatter3', 'number')
            ];
    }
}
