<?php
declare(strict_types = 1);
namespace RCS\Csv;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RCS\Util\ReflectionHelper;

#[CoversClass(\RCS\Csv\CsvBindByName::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class CsvBindByNameTest extends TestCase
{
    private const TEST_COLUMN1 = 'Test Column 1';
    private const TEST_COLUMN2 = 'Test Column 2';

    public function testCtor_emptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CsvBindByName('');  // NOSONAR
    }

    public function testCtor_emptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CsvBindByName([]);  // NOSONAR
    }

    public function testCtor_blankArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CsvBindByName('   ');  // NOSONAR
    }

//     public function testCtor_nonStringArray()
//     {
//         $this->expectException(\InvalidArgumentException::class);

//         new CsvBindByName(['a', 2,]);  // NOSONAR
//     }

    public function testCtor_string(): void
    {
        $testObj = new CsvBindByName(self::TEST_COLUMN1);

        $columns = ReflectionHelper::getObjectProperty(CsvBindByName::class, 'columns', $testObj);

        self::assertIsArray($columns);
        self::assertCount(1, $columns);
        self::assertEquals(self::TEST_COLUMN1, array_shift($columns));
    }

    public function testCtor_paddedString(): void
    {
        $testObj = new CsvBindByName('  '.self::TEST_COLUMN2.'    ');

        $columns = ReflectionHelper::getObjectProperty(CsvBindByName::class, 'columns', $testObj);

        self::assertIsArray($columns);
        self::assertCount(1, $columns);
        self::assertEquals(self::TEST_COLUMN2, array_shift($columns));
    }

    public function testCtor_array(): void
    {
        $testObj = new CsvBindByName([self::TEST_COLUMN1, self::TEST_COLUMN2]);

        $columns = ReflectionHelper::getObjectProperty(CsvBindByName::class, 'columns', $testObj);

        self::assertIsArray($columns);
        self::assertCount(2, $columns);
        self::assertEquals(self::TEST_COLUMN1, array_shift($columns));
        self::assertEquals(self::TEST_COLUMN2, array_shift($columns));
    }

    public function testGetColumns(): void
    {
        $testObj = new CsvBindByName(self::TEST_COLUMN1);

        ReflectionHelper::setObjectProperty(CsvBindByName::class, 'columns', array(self::TEST_COLUMN2), $testObj);

        $columns = $testObj->getColumns();

        self::assertCount(1, $columns);

        self::assertEquals(self::TEST_COLUMN2, array_shift($columns));
    }
}
