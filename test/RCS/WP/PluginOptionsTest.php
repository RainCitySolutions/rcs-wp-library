<?php
declare(strict_types=1);
namespace RCS\WP;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RCS\Util\ReflectionHelper;

#[CoversClass(PluginOptions::class)]
#[UsesClass(ReflectionHelper::class)]
final class PluginOptionsTest extends TestCase
{
    public const TEST_OPTION_NAME = 'test_option';
    public const KEY1 = 'key1';
    public const KEY2 = 'key2';
    private const DB_VERSION = '1.2.3';

    /** @var array<string, mixed> */
    private array $optionsTable = [];

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        \Brain\Monkey\Functions\when('add_option')->alias(
            function (string $option, $value = '', string $deprecated = '', $autoload = 'yes')
            {
                $result = false;

                if (!isset($this->optionsTable[$option])) {
                    $this->optionsTable[$option] = $value;
                    $result = true;
                }

                return $result;
            }
        );
        \Brain\Monkey\Functions\when('update_option')->alias(
            function (string $option, $value, $autoload = null)
            {
                $this->optionsTable[$option] = $value;
                return true;
            }
        );
        \Brain\Monkey\Functions\when('get_option')->alias(
            function(string $option, $default = false)
            {
                $result = $default;

                if (isset($this->optionsTable[$option])) {
                    $result = $this->optionsTable[$option];
                }

                return $result;
            }
        );

        // Reset WordPress options mock
        $this->optionsTable = [];
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
    }

    private function getPluginOptionsInstance(): PluginOptions
    {
        // Reset any singleton references
        ReflectionHelper::setClassProperty(PluginOptions::class, 'instance', []);

        return TestPluginOptions::init();
    }


    public function testInitializeInstanceSetsDefaults(): void
    {
        $instance = $this->getPluginOptionsInstance();
        $values = $instance->getValues();

        self::assertArrayHasKey('dbVersion', $values);
        self::assertArrayHasKey(self::KEY1, $values);
        self::assertArrayHasKey(self::KEY2, $values);

        // Default values are empty strings
        foreach ($values as $val) {
            self::assertSame('', $val);
        }
    }

    public function testSetValueAndGetValue(): void
    {
        $instance = $this->getPluginOptionsInstance();

        $instance->setValue(self::KEY1, 'value1');
        self::assertSame('value1', $instance->getValue(self::KEY1));

        $instance->setValue('invalid', 'shouldBeIgnored');
        self::assertNull($instance->getValue('invalid'));
    }

    public function testNewValue(): void
    {
        // Simulate key2 as new value
        add_option(PluginOptionsTest::TEST_OPTION_NAME, ['dbVersion' => self::DB_VERSION, self::KEY1 => 'testValue']);

        $instance = $this->getPluginOptionsInstance();
        $values = $instance->getValues();

        self::assertArrayHasKey(self::KEY2, $values);
    }

    public function testOldValue(): void
    {
        // Simulate key2 as new value
        add_option(PluginOptionsTest::TEST_OPTION_NAME, ['dbVersion' => self::DB_VERSION, self::KEY1 => 'testValue', self::KEY2 => 'testValue', 'oldKey' => 'oldTestValue']);

        $instance = $this->getPluginOptionsInstance();
        $values = $instance->getValues();

        self::assertArrayNotHasKey('oldKey', $values);
    }

    public function testDatabaseVersionMethods(): void
    {
        $instance = $this->getPluginOptionsInstance();
        $instance->setDatabaseVersion(self::DB_VERSION);

        self::assertSame(self::DB_VERSION, $instance->getDatabaseVersion());
        self::assertSame(self::DB_VERSION, $instance->getValue('dbVersion'));
    }
}

class TestPluginOptions extends PluginOptions
{
    public function getOptionName(): string
    {
        return PluginOptionsTest::TEST_OPTION_NAME;
    }

    protected function getOptionKeys(): array
    {
        return [PluginOptionsTest::KEY1, PluginOptionsTest::KEY2];
    }
}
