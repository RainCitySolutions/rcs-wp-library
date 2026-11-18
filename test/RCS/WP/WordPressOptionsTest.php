<?php

declare(strict_types=1);

namespace RCS\WP\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RCS\WP\WordPressOptions;

/**
 * Examples of the intended usage: each subclass owns its option name and
 * its set of valid keys.
 */
final class SampleSettings extends WordPressOptions
{
    public const OPTION_NAME = 'rcs_sample_settings';

    public function __construct()
    {
        parent::__construct(self::OPTION_NAME, ['api_key', 'mode']);
    }
}

final class OtherSettings extends WordPressOptions
{
    public const OPTION_NAME = 'rcs_other_settings';

    public function __construct()
    {
        parent::__construct(self::OPTION_NAME, ['theme']);
    }
}

/**
 * Configurable harness so the base class can be exercised with arbitrary
 * option names and key sets.
 */
final class TestOptions extends WordPressOptions
{
    /**
     * @param string[] $validKeys
     */
    public function __construct(string $optionName, array $validKeys)
    {
        parent::__construct($optionName, $validKeys);
    }
}

#[CoversClass(WordPressOptions::class)]
final class WordPressOptionsTest extends TestCase
{
    // Verifies Mockery/Brain Monkey expectations and counts them as assertions.
    use MockeryPHPUnitIntegration;

    private const OPTION_NAME = 'rcs_test_options';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function stubStored(mixed $stored): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(self::OPTION_NAME, [])
            ->andReturn($stored);
    }

    private function expectNoWrite(): void
    {
        Functions\expect('update_option')->never();
    }

    /**
     * @param array<string, string> $values
     */
    private function expectWrite(array $values, int $times = 1): void
    {
        Functions\expect('update_option')
            ->times($times)
            ->with(self::OPTION_NAME, $values, false)
            ->andReturn(true);
    }

    /**
     * @param string[] $validKeys
     * @param null|string[] $expectedWrite
     */
    private function makeOptions(mixed $stored, array $validKeys, ?array $expectedWrite = null): TestOptions
    {
        $this->stubStored($stored);

        if ($expectedWrite === null) {
            $this->expectNoWrite();
        } else {
            $this->expectWrite($expectedWrite);
        }

        return new TestOptions(self::OPTION_NAME, $validKeys);
    }

    // ------------------------------------------------------------------
    // Constructor: loading and normalization
    // ------------------------------------------------------------------

    public function testOptionNameIsReturned(): void
    {
        $opts = $this->makeOptions(['a' => '1'], ['a']);

        $this->assertSame(self::OPTION_NAME, $opts->getOptionName());
    }

    public function testMissingOptionInitializesAllKeysToEmptyStringAndWrites(): void
    {
        $expected = ['a' => '', 'b' => ''];

        $opts = $this->makeOptions([], ['a', 'b'], $expected);

        $this->assertSame($expected, $opts->getValues());
    }

    public function testCompleteStoredOptionIsLoadedWithoutWriting(): void
    {
        $stored = ['a' => 'one', 'b' => 'two'];

        $opts = $this->makeOptions($stored, ['a', 'b']);

        $this->assertSame($stored, $opts->getValues());
    }

    public function testMissingKeysAreFilledAndPersisted(): void
    {
        $opts = $this->makeOptions(
            ['a' => 'one'],
            ['a', 'b'],
            ['a' => 'one', 'b' => '']
        );

        $this->assertSame(['a' => 'one', 'b' => ''], $opts->getValues());
    }

    public function testStaleKeysArePrunedAndPersisted(): void
    {
        $opts = $this->makeOptions(
            ['a' => 'one', 'old' => 'gone'],
            ['a'],
            ['a' => 'one']
        );

        $this->assertSame(['a' => 'one'], $opts->getValues());
        $this->assertNull($opts->getValue('old'));
    }

    public function testValuesFollowValidKeysOrder(): void
    {
        // Stored order differs from validKeys order. The normalized order
        // differs from storage, so a write is expected (strict !== on arrays).
        $opts = $this->makeOptions(
            ['b' => '2', 'a' => '1'],
            ['a', 'b'],
            ['a' => '1', 'b' => '2']
        );

        $this->assertSame(['a', 'b'], array_keys($opts->getValues()));
    }

    /**
     * @param mixed $stored
     */
    #[DataProvider('nonArrayStoredProvider')]
    public function testNonArrayStoredValueIsTreatedAsEmpty(mixed $stored): void
    {
        $expected = ['a' => ''];

        $opts = $this->makeOptions($stored, ['a'], $expected);

        $this->assertSame($expected, $opts->getValues());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function nonArrayStoredProvider(): array
    {
        return [
            'false (WP default for missing)' => [false],
            'null'                           => [null],
            'string'                         => ['corrupt'],
            'int'                            => [123],
            'empty string'                   => [''],
        ];
    }

    #[DataProvider('coercionProvider')]
    public function testStoredValuesAreCoercedToStrings(mixed $input, string $expected): void
    {
        $this->stubStored(['k' => $input]);
        // Whether a write happens depends on the case; it is not under test here.
        Functions\when('update_option')->justReturn(true);

        $opts = new TestOptions(self::OPTION_NAME, ['k']);

        $this->assertSame($expected, $opts->getValue('k'));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function coercionProvider(): array
    {
        return [
            'string'        => ['abc', 'abc'],
            'int'           => [42, '42'],
            'float'         => [1.5, '1.5'],
            'bool true'     => [true, '1'],
            'bool false'    => [false, ''],
            'null'          => [null, ''],
            'array'         => [['x'], ''],
            'object'        => [new \stdClass(), ''],
        ];
    }

    public function testNonStringStoredValuesTriggerWrite(): void
    {
        $this->stubStored(['k' => 42]);
        $this->expectWrite(['k' => '42']);

        new TestOptions(self::OPTION_NAME, ['k']);
    }

    public function testEmptyValidKeysResultsInNoValues(): void
    {
        $opts = $this->makeOptions([], []);

        $this->assertSame([], $opts->getValues());
    }

    public function testEmptyValidKeysPrunesEverythingStored(): void
    {
        $opts = $this->makeOptions(['a' => '1'], [], []);

        $this->assertSame([], $opts->getValues());
    }

    // ------------------------------------------------------------------
    // getValues / getValue
    // ------------------------------------------------------------------

    public function testGetValueReturnsStoredValue(): void
    {
        $opts = $this->makeOptions(['a' => 'one', 'b' => 'two'], ['a', 'b']);

        $this->assertSame('one', $opts->getValue('a'));
        $this->assertSame('two', $opts->getValue('b'));
    }

    public function testGetValueReturnsEmptyStringForDefaultedKey(): void
    {
        $opts = $this->makeOptions(['a' => 'x'], ['a', 'b'], ['a' => 'x', 'b' => '']);

        $this->assertSame('', $opts->getValue('b'));
    }

    public function testGetValueReturnsNullForInvalidKey(): void
    {
        $opts = $this->makeOptions(['a' => 'one'], ['a']);

        $this->assertNull($opts->getValue('nope'));
    }

    public function testKeysAreCaseSensitive(): void
    {
        $opts = $this->makeOptions(['a' => 'one'], ['a']);

        $this->assertNull($opts->getValue('A'));
        $this->assertFalse($opts->setValue('A', 'x'));
    }

    // ------------------------------------------------------------------
    // setValue
    // ------------------------------------------------------------------

    public function testSetValueUpdatesValueAndReturnsTrue(): void
    {
        $opts = $this->makeOptions(['a' => 'one'], ['a']);

        $this->assertTrue($opts->setValue('a', 'changed'));
        $this->assertSame('changed', $opts->getValue('a'));
        $this->assertSame(['a' => 'changed'], $opts->getValues());
    }

    public function testSetValueAcceptsEmptyString(): void
    {
        $opts = $this->makeOptions(['a' => 'one'], ['a']);

        $this->assertTrue($opts->setValue('a', ''));
        $this->assertSame('', $opts->getValue('a'));
    }

    public function testSetValueWithInvalidKeyReturnsFalseAndChangesNothing(): void
    {
        $opts = $this->makeOptions(['a' => 'one'], ['a']);

        $this->assertFalse($opts->setValue('nope', 'x'));
        $this->assertNull($opts->getValue('nope'));
        $this->assertSame(['a' => 'one'], $opts->getValues());
    }

    public function testSetValueDoesNotPersistUntilSaveIsCalled(): void
    {
        // makeOptions() asserts update_option is never called during construction;
        // the expectation stays active, so any write from setValue() would fail.
        $opts = $this->makeOptions(['a' => 'one'], ['a']);

        $opts->setValue('a', 'changed');

        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------
    // save
    // ------------------------------------------------------------------

    public function testSavePersistsCurrentValuesWithAutoloadDisabled(): void
    {
        $this->stubStored(['a' => 'one', 'b' => 'two']);

        // No write in the constructor; exactly one on save() with the new values.
        Functions\expect('update_option')
            ->once()
            ->with(self::OPTION_NAME, ['a' => 'changed', 'b' => 'two'], false)
            ->andReturn(true);

        $opts = new TestOptions(self::OPTION_NAME, ['a', 'b']);
        $opts->setValue('a', 'changed');
        $opts->save();
    }

    public function testSaveWritesEvenWhenNothingChanged(): void
    {
        $this->stubStored(['a' => 'one']);
        $this->expectWrite(['a' => 'one']);

        $opts = new TestOptions(self::OPTION_NAME, ['a']);
        $opts->save();
    }

    // ------------------------------------------------------------------
    // Intended usage: subclasses define their own option name and keys
    // ------------------------------------------------------------------

    public function testConstructorIsProtectedSoOnlySubclassesCanConstruct(): void
    {
        $ctor = new \ReflectionMethod(WordPressOptions::class, '__construct');

        $this->assertTrue($ctor->isProtected());
    }

    public function testSubclassSuppliesItsOwnOptionNameAndKeys(): void
    {
        Functions\when('get_option')->justReturn(['api_key' => 'abc', 'mode' => 'live']);
        Functions\expect('update_option')->never();

        $settings = new SampleSettings();

        $this->assertSame(SampleSettings::OPTION_NAME, $settings->getOptionName());
        $this->assertSame(['api_key', 'mode'], array_keys($settings->getValues()));
        $this->assertSame('abc', $settings->getValue('api_key'));
        $this->assertNull($settings->getValue('theme')); // belongs to OtherSettings
    }

    public function testSubclassesReadAndWriteTheirOwnOptionsIndependently(): void
    {
        $db = [
            SampleSettings::OPTION_NAME => ['api_key' => 'abc', 'mode' => 'live'],
            // OtherSettings has no stored option yet.
        ];
        $writes = [];

        Functions\when('get_option')->alias(
            static fn(string $name, mixed $default = false): mixed => $db[$name] ?? $default
        );
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value, mixed $autoload = null) use (&$writes): bool {
                $writes[$name] = $value;
                return true;
            }
        );

        $sample = new SampleSettings();
        $other  = new OtherSettings();

        // Only the option that was missing/incomplete is initialized on load.
        $this->assertSame([OtherSettings::OPTION_NAME => ['theme' => '']], $writes);

        $sample->setValue('mode', 'test');
        $sample->save();

        $this->assertSame(
            ['api_key' => 'abc', 'mode' => 'test'],
            $writes[SampleSettings::OPTION_NAME]    // @phpstan-ignore offsetAccess.notFound
        );
        $this->assertSame(['theme' => ''], $writes[OtherSettings::OPTION_NAME]);

        // Keys from one subclass are not valid on the other.
        $this->assertFalse($sample->setValue('theme', 'dark'));
        $this->assertFalse($other->setValue('mode', 'live'));
    }
}
