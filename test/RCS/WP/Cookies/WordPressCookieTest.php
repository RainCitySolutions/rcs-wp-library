<?php

declare(strict_types=1);

namespace RCS\WP\Cookies\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RCS\WP\Cookies\CookieObject;
use RCS\WP\Cookies\WordPressCookie;
use RCS\WP\Cookies\WordPressCookieException;
use RuntimeException;

/*
 * WordPressCookie calls every function fully qualified, so the tests use
 * Brain Monkey (built on Patchwork) to stub both the WordPress option and
 * transient functions and the PHP internals. The internals must be listed
 * under "redefinable-internals" in patchwork.json.
 *
 * Values are stored as CookieObject instances in WordPress options. The
 * transient functions are only used to read (and delete) values written by
 * the prior storage mechanism.
 *
 * Limitation: Patchwork passes arguments by value, so the by-reference
 * $file/$line arguments of headers_sent() can't be populated. The class sees
 * '' and 0 for them, so tests match the message format rather than values.
 */

#[CoversClass(WordPressCookie::class)]
#[CoversClass(WordPressCookieException::class)]
#[UsesClass(CookieObject::class)]
final class WordPressCookieTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const COOKIE_NAME = 'my_cookie';
    private const HASH_KEY = 'secret-key';
    private const EXISTING_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const OPTION_PREFIX = '_cprn_cookie_';
    private const HEADERS_SENT_MESSAGE = '/^Unable to create cookie, headers already sent, line \d+ in /';

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $_COOKIE = [];
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeCookie(int $lifetime = 86400, string $path = '/'): WordPressCookie
    {
        return new WordPressCookie($this->logger, self::COOKIE_NAME, self::HASH_KEY, $lifetime, $path);
    }

    private function withExistingCookie(): WordPressCookie
    {
        $_COOKIE[self::COOKIE_NAME] = self::EXISTING_HASH;
        return $this->makeCookie();
    }

    private static function hashFor(string $uniqid): string
    {
        return hash('sha256', self::HASH_KEY . '-' . $uniqid);
    }

    private static function optionKey(string $hash): string
    {
        return self::OPTION_PREFIX . $hash;
    }

    /** Stubs headers_sent() (the new-cookie path asks it). */
    private function headersSent(bool $sent): void
    {
        Functions\when('headers_sent')->justReturn($sent);
    }

    /**
     * Stubs setcookie() and returns a reference to the list of recorded calls.
     *
     * @return list<array<int, mixed>>
     */
    private function &recordSetcookie(): array
    {
        $calls = [];
        Functions\when('setcookie')->alias(static function (...$args) use (&$calls): bool {
            $calls[] = $args;
            return true;
        });
        return $calls;
    }

    /**
     * Stubs the option functions with an in-memory store and returns a
     * reference to it. A missing option reads as false, as in WordPress.
     *
     * @return array<string, mixed>
     */
    private function &fakeOptions(): array
    {
        /** @var array<string, mixed> $store */
        $store = [];
        Functions\when('update_option')->alias(static function (string $k, mixed $v) use (&$store): bool {
            $store[$k] = $v;
            return true;
        });
        Functions\when('get_option')->alias(static function (string $k) use (&$store): mixed {
            return $store[$k] ?? false;
        });
        Functions\when('delete_option')->alias(static function (string $k) use (&$store): bool {
            unset($store[$k]);
            return true;
        });
        return $store;
    }

    /**
     * Stubs the transient functions with an in-memory store (the prior
     * storage mechanism) and returns a reference to it.
     *
     * @return array<string, mixed>
     */
    private function &fakeTransients(): array
    {
        /** @var array<string, mixed> $store */
        $store = [];
        Functions\when('get_transient')->alias(static function (string $k) use (&$store): mixed {
            return $store[$k] ?? false;
        });
        Functions\when('delete_transient')->alias(static function (string $k) use (&$store): bool {
            unset($store[$k]);
            return true;
        });
        return $store;
    }

    // ---------------------------------------------------------------
    // Construction: cookie already present
    // ---------------------------------------------------------------

    public function testExistingCookieDoesNotSetANewCookie(): void
    {
        Functions\expect('setcookie')->never();

        $this->withExistingCookie();
    }

    public function testExistingCookieDoesNotCheckHeadersSent(): void
    {
        // Even if headers have been sent, an existing valid cookie must not throw.
        Functions\expect('headers_sent')->never();
        $this->logger->expects($this->never())->method('warning');

        $this->withExistingCookie();
    }

    public function testExistingCookieValueIsUsedAsOptionKey(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(self::optionKey(self::EXISTING_HASH))
            ->andReturn(new CookieObject('stored', 3600));
        Functions\expect('get_transient')->never();

        $this->assertSame('stored', $this->withExistingCookie()->getCookieValue());
    }

    // ---------------------------------------------------------------
    // Construction: cookie present but invalid (client-controlled input)
    // ---------------------------------------------------------------

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function invalidCookieValuesProvider(): array
    {
        $valid = str_repeat('a', 64);

        return [
            'empty string'        => [''],
            'too short'           => [str_repeat('a', 63)],
            'too long'            => [str_repeat('a', 65)],
            'trailing newline'    => [$valid . "\n"],
            'non-hex characters'  => [str_repeat('g', 64)],
            'uppercase hex'       => [str_repeat('A', 64)],
            'path traversal'      => ['../../etc/passwd'],
            'sql-ish'             => ["' OR 1=1 --"],
            'leading whitespace'  => [' ' . substr($valid, 1)],
            'null byte'           => [substr($valid, 0, 63) . "\0"],
            'array'               => [[$valid]],
            'nested array'        => [['a' => ['b' => $valid]]],
        ];
    }

    #[DataProvider('invalidCookieValuesProvider')]
    public function testInvalidCookieIsReplacedWithNewCookie(mixed $invalid): void
    {
        $_COOKIE[self::COOKIE_NAME] = $invalid;
        $expectedHash = self::hashFor('fresh');
        $expectedKey = self::optionKey($expectedHash);

        $this->headersSent(false);
        Functions\when('uniqid')->justReturn('fresh');
        Functions\when('time')->justReturn(1_000);
        Functions\expect('setcookie')
            ->once()
            ->with(self::COOKIE_NAME, $expectedHash, 1_000 + 86400, '/', '', false, true)
            ->andReturn(true);

        // The invalid client value must never be used as an option or transient key.
        Functions\expect('get_option')->once()->with($expectedKey)->andReturn(false);
        Functions\expect('get_transient')->once()->with($expectedHash)->andReturn(false);
        Functions\expect('update_option')
            ->once()
            ->with($expectedKey, Mockery::type(CookieObject::class), false)
            ->andReturn(true);
        Functions\expect('delete_option')->once()->with($expectedKey);
        Functions\expect('delete_transient')->once()->with($expectedHash);

        $cookie = $this->makeCookie();
        $cookie->getCookieValue();
        $cookie->setCookieValue('x');
        $cookie->deleteCookieValue();
    }

    #[DataProvider('invalidCookieValuesProvider')]
    public function testInvalidCookieThrowsWhenHeadersAlreadySent(mixed $invalid): void
    {
        $_COOKIE[self::COOKIE_NAME] = $invalid;
        $this->headersSent(true);
        Functions\expect('setcookie')->never();

        $this->logger->expects($this->once())->method('warning');
        $this->expectException(WordPressCookieException::class);

        $this->makeCookie();
    }

    public function testValidCookieIsAcceptedAsIs(): void
    {
        $hash = hash('sha256', 'anything');
        $_COOKIE[self::COOKIE_NAME] = $hash;

        Functions\expect('setcookie')->never();
        Functions\expect('update_option')
            ->once()
            ->with(self::optionKey($hash), Mockery::type(CookieObject::class), false)
            ->andReturn(true);

        $this->makeCookie()->setCookieValue('v');
    }

    public function testOtherCookiesDoNotAffectValidation(): void
    {
        $_COOKIE['unrelated'] = 'garbage';
        $_COOKIE[self::COOKIE_NAME] = self::EXISTING_HASH;

        Functions\expect('setcookie')->never();

        $this->makeCookie();
    }

    // ---------------------------------------------------------------
    // Construction: cookie not present
    // ---------------------------------------------------------------

    public function testNewCookieIsSetWithExpectedArguments(): void
    {
        $this->headersSent(false);
        Functions\when('time')->justReturn(1_700_000_000);
        Functions\when('uniqid')->justReturn('abc123');

        Functions\expect('setcookie')
            ->once()
            ->with(self::COOKIE_NAME, self::hashFor('abc123'), 1_700_003_600, '/shop', '', false, true)
            ->andReturn(true);

        $this->makeCookie(3600, '/shop');
    }

    public function testNewCookieUsesDefaultLifetimeAndPath(): void
    {
        $this->headersSent(false);
        Functions\when('time')->justReturn(1_000);
        $calls = &$this->recordSetcookie();

        $this->makeCookie();

        $this->assertCount(1, $calls);
        $this->assertSame(1_000 + 86400, $calls[0][2], 'Default lifetime should be 24 hours');
        $this->assertSame('/', $calls[0][3], "Default path should be '/'");
    }

    public function testNewCookieIsHttpOnlyAndNotSecure(): void
    {
        $this->headersSent(false);
        $calls = &$this->recordSetcookie();

        $this->makeCookie();

        $this->assertFalse($calls[0][5], 'secure flag');
        $this->assertTrue($calls[0][6], 'httponly flag');
    }

    public function testGeneratedHashIsFixedLengthSha256Hex(): void
    {
        $this->headersSent(false);
        $calls = &$this->recordSetcookie();

        $this->makeCookie();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $calls[0][1]);
    }

    public function testGeneratedHashDoesNotLeakHashKey(): void
    {
        $this->headersSent(false);
        $calls = &$this->recordSetcookie();

        $this->makeCookie();

        $this->assertStringNotContainsString(self::HASH_KEY, (string) $calls[0][1]);
    }

    public function testDifferentInstancesGenerateDifferentHashes(): void
    {
        $this->headersSent(false);
        $calls = &$this->recordSetcookie();

        $this->makeCookie();
        $this->makeCookie();

        $this->assertCount(2, $calls);
        $this->assertNotSame($calls[0][1], $calls[1][1]);
    }

    public function testDifferentHashKeysGenerateDifferentHashes(): void
    {
        $this->headersSent(false);
        Functions\when('uniqid')->justReturn('same');
        $calls = &$this->recordSetcookie();

        new WordPressCookie($this->logger, self::COOKIE_NAME, 'key-one');
        new WordPressCookie($this->logger, self::COOKIE_NAME, 'key-two');

        $this->assertNotSame($calls[0][1], $calls[1][1]);
    }

    public function testNewCookieChecksHeadersSentBeforeSetting(): void
    {
        Functions\expect('headers_sent')->once()->andReturn(false);
        Functions\expect('setcookie')->once()->andReturn(true);

        $this->makeCookie();
    }

    // ---------------------------------------------------------------
    // Construction: headers already sent
    // ---------------------------------------------------------------

    public function testThrowsWhenHeadersSentAndCookieMissing(): void
    {
        $this->headersSent(true);

        $this->expectException(WordPressCookieException::class);
        $this->expectExceptionMessageMatches(self::HEADERS_SENT_MESSAGE);

        $this->makeCookie();
    }

    public function testLogsWarningWhenHeadersSentAndCookieMissing(): void
    {
        $this->headersSent(true);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->callback(
                static fn(string $msg): bool => preg_match(self::HEADERS_SENT_MESSAGE, $msg) === 1
            ));

        try {
            $this->makeCookie();
            $this->fail('Expected WordPressCookieException');
        } catch (WordPressCookieException) {
            // expected
        }
    }

    public function testDoesNotSetCookieWhenHeadersSent(): void
    {
        $this->headersSent(true);
        Functions\expect('setcookie')->never();

        try {
            $this->makeCookie();
        } catch (WordPressCookieException) {
            // expected
        }
    }

    public function testExceptionIsARuntimeException(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new WordPressCookieException('x'));
    }

    // ---------------------------------------------------------------
    // getCookieValue()
    // ---------------------------------------------------------------

    public function testGetCookieValueReturnsNullWhenNothingStored(): void
    {
        // WordPress returns false for a missing option and a missing/expired transient.
        Functions\expect('get_option')->once()->with(self::optionKey(self::EXISTING_HASH))->andReturn(false);
        Functions\expect('get_transient')->once()->with(self::EXISTING_HASH)->andReturn(false);

        $this->assertNull($this->withExistingCookie()->getCookieValue());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function storedValuesProvider(): array
    {
        return [
            'string'        => ['hello'],
            'integer'       => [123],
            'zero'          => [0],
            'empty string'  => [''],
            'null'          => [null],
            'true'          => [true],
            'false'         => [false],
            'empty array'   => [[]],
            'array'         => [['a' => 1, 'b' => [2, 3]]],
            'object'        => [(object) ['x' => 'y']],
        ];
    }

    #[DataProvider('storedValuesProvider')]
    public function testGetCookieValueReturnsValueFromOption(mixed $value): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(self::optionKey(self::EXISTING_HASH))
            ->andReturn(new CookieObject($value, 3600));
        Functions\expect('delete_option')->never();
        // A stored entry is authoritative, even when its value is null or false.
        Functions\expect('get_transient')->never();

        $this->assertEquals($value, $this->withExistingCookie()->getCookieValue());
    }

    public function testGetCookieValueReturnsNullAndDeletesOptionWhenExpired(): void
    {
        $key = self::optionKey(self::EXISTING_HASH);

        Functions\expect('get_option')->once()->with($key)->andReturn(new CookieObject('old', -10));
        Functions\expect('delete_option')->once()->with($key);
        // An expired entry must not fall back to the prior storage mechanism.
        Functions\expect('get_transient')->never();

        $this->assertNull($this->withExistingCookie()->getCookieValue());
    }

    public function testGetCookieValueDoesNotDeleteUnexpiredOption(): void
    {
        Functions\when('get_option')->justReturn(new CookieObject('fresh', 3600));
        Functions\expect('delete_option')->never();

        $this->assertSame('fresh', $this->withExistingCookie()->getCookieValue());
    }

    public function testGetCookieValueFallsBackToTransientWhenNoOption(): void
    {
        Functions\expect('get_option')->once()->with(self::optionKey(self::EXISTING_HASH))->andReturn(false);
        Functions\expect('get_transient')->once()->with(self::EXISTING_HASH)->andReturn('legacy');

        $this->assertSame('legacy', $this->withExistingCookie()->getCookieValue());
    }

    #[DataProvider('storedValuesProvider')]
    public function testGetCookieValueReturnsLegacyTransientValue(mixed $value): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\expect('get_transient')->once()->with(self::EXISTING_HASH)->andReturn($value);

        // A legacy transient of false is indistinguishable from "missing", so it reads as null.
        $expected = $value === false ? null : $value;

        $this->assertEquals($expected, $this->withExistingCookie()->getCookieValue());
    }

    public function testOptionTakesPrecedenceOverLegacyTransient(): void
    {
        Functions\when('get_option')->justReturn(new CookieObject('new', 3600));
        Functions\expect('get_transient')->never();

        $this->assertSame('new', $this->withExistingCookie()->getCookieValue());
    }

    public function testGetCookieValueUsesGeneratedHashForNewCookie(): void
    {
        $hash = self::hashFor('u1');

        $this->headersSent(false);
        Functions\when('uniqid')->justReturn('u1');
        Functions\when('setcookie')->justReturn(true);
        Functions\expect('get_option')
            ->once()
            ->with(self::optionKey($hash))
            ->andReturn(new CookieObject('value', 3600));

        $this->assertSame('value', $this->makeCookie()->getCookieValue());
    }

    // ---------------------------------------------------------------
    // setCookieValue()
    // ---------------------------------------------------------------

    public function testSetCookieValueStoresCookieObjectUnderPrefixedHashKey(): void
    {
        $captured = null;
        Functions\expect('update_option')
            ->once()
            ->with(self::optionKey(self::EXISTING_HASH), Mockery::type(CookieObject::class), false)
            ->andReturnUsing(static function (string $k, CookieObject $entry) use (&$captured): bool {
                $captured = $entry;
                return true;
            });

        $this->assertTrue($this->withExistingCookie()->setCookieValue(['k' => 'v']));
        $this->assertInstanceOf(CookieObject::class, $captured);
        $this->assertSame(['k' => 'v'], $captured->getValue());
    }

    public function testSetCookieValueDoesNotAutoloadOption(): void
    {
        // The third argument ($autoload) must be false so cookie data isn't loaded on every request.
        Functions\expect('update_option')
            ->once()
            ->with(Mockery::any(), Mockery::any(), false)
            ->andReturn(true);

        $this->withExistingCookie()->setCookieValue('x');
    }

    public function testSetCookieValueDoesNotUseTransients(): void
    {
        Functions\when('update_option')->justReturn(true);
        Functions\expect('set_transient')->never();

        $this->withExistingCookie()->setCookieValue('x');
    }

    public function testStoredEntryExpiresAfterConfiguredLifetime(): void
    {
        $now = 1_000;
        Functions\when('time')->alias(static function () use (&$now): int {
            return $now;
        });

        $captured = null;
        Functions\when('update_option')->alias(
            static function (string $k, CookieObject $entry) use (&$captured): bool {
                $captured = $entry;
                return true;
            }
        );

        $_COOKIE[self::COOKIE_NAME] = self::EXISTING_HASH;
        $this->makeCookie(1800)->setCookieValue('x');

        $this->assertInstanceOf(CookieObject::class, $captured);

        $now = 1_000 + 1800;
        $this->assertFalse($captured->hasExpired(), 'Not expired at the end of the lifetime');

        $now = 1_000 + 1801;
        $this->assertTrue($captured->hasExpired(), 'Expired once the lifetime has passed');
    }

    public function testStoredEntryUsesDefaultLifetime(): void
    {
        $now = 1_000;
        Functions\when('time')->alias(static function () use (&$now): int {
            return $now;
        });

        $captured = null;
        Functions\when('update_option')->alias(
            static function (string $k, CookieObject $entry) use (&$captured): bool {
                $captured = $entry;
                return true;
            }
        );

        $this->withExistingCookie()->setCookieValue('x');

        $now = 1_000 + 86400;
        $this->assertFalse($captured->hasExpired());

        $now = 1_000 + 86401;
        $this->assertTrue($captured->hasExpired());
    }

    public function testSetCookieValueReturnsFalseWhenUpdateOptionFails(): void
    {
        Functions\expect('update_option')->once()->andReturn(false);

        $this->assertFalse($this->withExistingCookie()->setCookieValue('x'));
    }

    public function testSetThenGetRoundTrip(): void
    {
        $this->fakeOptions();

        $cookie = $this->withExistingCookie();
        $cookie->setCookieValue('round-trip');

        $this->assertSame('round-trip', $cookie->getCookieValue());
    }

    #[DataProvider('storedValuesProvider')]
    public function testSetThenGetRoundTripPreservesValue(mixed $value): void
    {
        $this->fakeOptions();

        $cookie = $this->withExistingCookie();
        $cookie->setCookieValue($value);

        $this->assertEquals($value, $cookie->getCookieValue());
    }

    public function testSetOverwritesPreviousValue(): void
    {
        $this->fakeOptions();

        $cookie = $this->withExistingCookie();
        $cookie->setCookieValue('first');
        $cookie->setCookieValue('second');

        $this->assertSame('second', $cookie->getCookieValue());
    }

    public function testCookiesWithDifferentHashesAreIsolated(): void
    {
        $store = &$this->fakeOptions();

        $_COOKIE[self::COOKIE_NAME] = self::EXISTING_HASH;
        $this->makeCookie()->setCookieValue('one');

        $otherHash = str_repeat('b', 64);
        $_COOKIE[self::COOKIE_NAME] = $otherHash;
        $other = $this->makeCookie();
        Functions\when('get_transient')->justReturn(false);

        $this->assertNull($other->getCookieValue());
        $this->assertArrayHasKey(self::optionKey(self::EXISTING_HASH), $store);
        $this->assertArrayNotHasKey(self::optionKey($otherHash), $store);
    }

    // ---------------------------------------------------------------
    // deleteCookieValue()
    // ---------------------------------------------------------------

    public function testDeleteCookieValueDeletesOptionAndLegacyTransient(): void
    {
        Functions\expect('delete_option')->once()->with(self::optionKey(self::EXISTING_HASH));
        Functions\expect('delete_transient')->once()->with(self::EXISTING_HASH);

        $this->withExistingCookie()->deleteCookieValue();
    }

    public function testValueIsGoneAfterDelete(): void
    {
        $this->fakeOptions();
        $this->fakeTransients();

        $cookie = $this->withExistingCookie();
        $cookie->setCookieValue('temp');
        $cookie->deleteCookieValue();

        $this->assertNull($cookie->getCookieValue());
    }

    public function testDeleteRemovesLegacyTransientValue(): void
    {
        $this->fakeOptions();
        $transients = &$this->fakeTransients();
        $transients[self::EXISTING_HASH] = 'legacy';

        $cookie = $this->withExistingCookie();
        $this->assertSame('legacy', $cookie->getCookieValue());

        $cookie->deleteCookieValue();

        $this->assertNull($cookie->getCookieValue());
        $this->assertArrayNotHasKey(self::EXISTING_HASH, $transients);
    }

    public function testDeleteDoesNotTouchHttpCookie(): void
    {
        Functions\expect('setcookie')->never();
        Functions\when('delete_option')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);

        $this->withExistingCookie()->deleteCookieValue();
    }
}
