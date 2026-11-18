<?php

declare(strict_types=1);

namespace RCS\WP\ShortURL;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RCS\WP\WordPressOptions;

/**
 * Requires dev dependency: brain/monkey (which brings in mockery).
 *
 * WordPress functions get_option() and update_option() are stubbed with
 * Brain Monkey, so no WordPress install is needed.
 */
#[CoversClass(ShortUrlOptions::class)]
#[CoversClass(WordPressOptions::class)]
final class ShortUrlOptionsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const NAME = 'raincity_wpf_shorturl_options';

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

    /**
     * Stub get_option() to return $stored for our option name.
     * update_option() is stubbed as a no-op unless a test sets an expectation first.
     */
    private function givenStoredOption(mixed $stored): void
    {
        Functions\when('get_option')->alias(
            static fn(string $name, mixed $default = false): mixed =>
            $name === self::NAME ? $stored : $default
        );
    }

    private function allowUpdates(): void
    {
        Functions\when('update_option')->justReturn(true);
    }

    /**
     * Build an instance whose stored data is already clean, so the constructor
     * does not write anything back.
     *
     * Pass $stubUpdate = false when the test has already registered its own
     * Functions\expect('update_option') — Brain Monkey cannot add an expectation
     * to a function that was already defined with Functions\when().
     */
    private function cleanInstance(
        string $prefix = 'https://rc.example/',
        bool $stubUpdate = true
    ): ShortUrlOptions {
        $this->givenStoredOption([ShortUrlOptions::URL_PREFIX => $prefix]);

        if ($stubUpdate) {
            $this->allowUpdates();
        }

        return new ShortUrlOptions();
    }

    // ------------------------------------------------------------------
    // Class shape
    // ------------------------------------------------------------------

    #[Test]
    public function itExtendsWordPressOptions(): void
    {
        $this->assertInstanceOf(WordPressOptions::class, $this->cleanInstance());
    }

    #[Test]
    public function itExposesExpectedConstants(): void
    {
        $this->assertSame('raincity_wpf_shorturl_options', ShortUrlOptions::OPTIONS_NAME);
        $this->assertSame('urlPrefix', ShortUrlOptions::URL_PREFIX);
    }

    #[Test]
    public function getOptionNameReturnsConfiguredName(): void
    {
        $this->assertSame(self::NAME, $this->cleanInstance()->getOptionName());
    }

    // ------------------------------------------------------------------
    // Constructor / loading behavior
    // ------------------------------------------------------------------

    #[Test]
    public function constructorReadsTheCorrectOptionName(): void
    {
        $requested = [];
        Functions\when('get_option')->alias(
            static function (string $name, mixed $default = false) use (&$requested): mixed {
                $requested[] = $name;
                return $default;
            }
        );
        $this->allowUpdates();

        new ShortUrlOptions();

        $this->assertSame([self::NAME], $requested);
    }

    #[Test]
    public function missingOptionDefaultsToEmptyStringAndIsPersisted(): void
    {
        $this->givenStoredOption([]);
        Functions\expect('update_option')
            ->once()
            ->with(self::NAME, [ShortUrlOptions::URL_PREFIX => ''], false)
            ->andReturn(true);

        $options = new ShortUrlOptions();

        $this->assertSame('', $options->getUrlPrefix());
        $this->assertSame([ShortUrlOptions::URL_PREFIX => ''], $options->getValues());
    }

    #[Test]
    #[DataProvider('nonArrayStoredProvider')]
    public function nonArrayStoredValueIsTreatedAsEmpty(mixed $stored): void
    {
        $this->givenStoredOption($stored);
        Functions\expect('update_option')
            ->once()
            ->with(self::NAME, [ShortUrlOptions::URL_PREFIX => ''], false)
            ->andReturn(true);

        $this->assertSame('', (new ShortUrlOptions())->getUrlPrefix());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonArrayStoredProvider(): array
    {
        return [
            'false (option missing)' => [false],
            'null'                   => [null],
            'string'                 => ['https://rc.example/'],
            'int'                    => [42],
        ];
    }

    #[Test]
    public function validStoredValueIsLoadedWithoutWritingBack(): void
    {
        $this->givenStoredOption([ShortUrlOptions::URL_PREFIX => 'https://rc.example/']);
        Functions\expect('update_option')->never();

        $options = new ShortUrlOptions();

        $this->assertSame('https://rc.example/', $options->getUrlPrefix());
    }

    #[Test]
    public function staleKeysArePrunedAndPersisted(): void
    {
        $this->givenStoredOption([
            ShortUrlOptions::URL_PREFIX => 'https://rc.example/',
            'oldKey'                    => 'obsolete',
        ]);
        Functions\expect('update_option')
            ->once()
            ->with(self::NAME, [ShortUrlOptions::URL_PREFIX => 'https://rc.example/'], false)
            ->andReturn(true);

        $options = new ShortUrlOptions();

        $this->assertSame([ShortUrlOptions::URL_PREFIX => 'https://rc.example/'], $options->getValues());
        $this->assertNull($options->getValue('oldKey'));
    }

    #[Test]
    #[DataProvider('coercionProvider')]
    public function storedScalarsAreCoercedToStringsAndNonScalarsToEmpty(mixed $stored, string $expected): void
    {
        $this->givenStoredOption([ShortUrlOptions::URL_PREFIX => $stored]);
        $this->allowUpdates();

        $this->assertSame($expected, (new ShortUrlOptions())->getUrlPrefix());
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function coercionProvider(): array
    {
        return [
            'string'      => ['abc', 'abc'],
            'int'         => [123, '123'],
            'float'       => [1.5, '1.5'],
            'true'        => [true, '1'],
            'false'       => [false, ''],
            'null'        => [null, ''],
            'array'       => [['x'], ''],
            'object'      => [new \stdClass(), ''],
        ];
    }

    // ------------------------------------------------------------------
    // getUrlPrefix()
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function prefixProvider(): array
    {
        return [
            'with trailing slash'    => ['https://rc.example/'],
            'without trailing slash' => ['https://rc.example'],
            'path style prefix'      => ['/go/'],
            'unicode'                => ['https://例え.jp/s/'],
        ];
    }

    #[Test]
    #[DataProvider('prefixProvider')]
    public function getUrlPrefixReturnsStoredValueUnmodified(string $prefix): void
    {
        $this->assertSame($prefix, $this->cleanInstance($prefix)->getUrlPrefix());
    }

    #[Test]
    public function getUrlPrefixReflectsValueChangedWithSetValue(): void
    {
        $options = $this->cleanInstance();

        $options->setValue(ShortUrlOptions::URL_PREFIX, 'https://new.example/');

        $this->assertSame('https://new.example/', $options->getUrlPrefix());
    }

    // ------------------------------------------------------------------
    // getValues() / getValue() / setValue()
    // ------------------------------------------------------------------

    #[Test]
    public function getValuesReturnsOnlyValidKeys(): void
    {
        $this->assertSame(
            [ShortUrlOptions::URL_PREFIX => 'https://rc.example/'],
            $this->cleanInstance()->getValues()
        );
    }

    #[Test]
    #[DataProvider('invalidKeyProvider')]
    public function getValueReturnsNullForInvalidKey(string $key): void
    {
        $this->assertNull($this->cleanInstance()->getValue($key));
    }

    #[Test]
    #[DataProvider('invalidKeyProvider')]
    public function setValueRejectsInvalidKey(string $key): void
    {
        $options = $this->cleanInstance();

        $this->assertFalse($options->setValue($key, 'value'));
        $this->assertNull($options->getValue($key));
        $this->assertSame([ShortUrlOptions::URL_PREFIX => 'https://rc.example/'], $options->getValues());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeyProvider(): array
    {
        return [
            'unknown key'       => ['bogus'],
            'empty string'      => [''],
            'wrong case'        => ['urlprefix'],
            'whitespace padded' => [' urlPrefix '],
            'numeric string'    => ['0'],
        ];
    }

    #[Test]
    public function setValueAcceptsValidKeyAndUpdatesInMemory(): void
    {
        $options = $this->cleanInstance();

        $this->assertTrue($options->setValue(ShortUrlOptions::URL_PREFIX, 'https://new.example/'));
        $this->assertSame('https://new.example/', $options->getValue(ShortUrlOptions::URL_PREFIX));
        $this->assertSame([ShortUrlOptions::URL_PREFIX => 'https://new.example/'], $options->getValues());
    }

    #[Test]
    public function setValueDoesNotPersistUntilSaveIsCalled(): void
    {
        $this->givenStoredOption([ShortUrlOptions::URL_PREFIX => 'https://rc.example/']);
        Functions\expect('update_option')->never();

        $options = new ShortUrlOptions();
        $options->setValue(ShortUrlOptions::URL_PREFIX, 'https://new.example/');
    }

    #[Test]
    public function setValueAllowsEmptyString(): void
    {
        $options = $this->cleanInstance();

        $this->assertTrue($options->setValue(ShortUrlOptions::URL_PREFIX, ''));
        $this->assertSame('', $options->getUrlPrefix());
    }

    // ------------------------------------------------------------------
    // save()
    // ------------------------------------------------------------------

    #[Test]
    public function savePersistsCurrentValuesWithAutoloadDisabled(): void
    {
        // Clean stored data means the constructor doesn't write, so the only
        // update_option call is the one from save().
        Functions\expect('update_option')
            ->once()
            ->with(self::NAME, [ShortUrlOptions::URL_PREFIX => 'https://new.example/'], false)
            ->andReturn(true);

        $options = $this->cleanInstance(stubUpdate: false);
        $options->setValue(ShortUrlOptions::URL_PREFIX, 'https://new.example/');

        $options->save();
    }

    #[Test]
    public function savePersistsUnchangedValuesToo(): void
    {
        Functions\expect('update_option')
            ->once()
            ->with(self::NAME, [ShortUrlOptions::URL_PREFIX => 'https://rc.example/'], false)
            ->andReturn(true);

        $options = $this->cleanInstance(stubUpdate: false);

        $options->save();
    }
}
