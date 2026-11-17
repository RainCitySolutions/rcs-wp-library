<?php
declare(strict_types = 1);

namespace RCS\WP\Cookies\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RCS\WP\Cookies\WordPressCookie;
use RCS\WP\Cookies\WordPressCookieException;
use RuntimeException;

/*
 * WordPressCookie calls every function fully qualified, so the tests use
 * Brain Monkey (built on Patchwork) to stub both the WordPress transient
 * functions and the PHP internals. The internals must be listed under
 * "redefinable-internals" in patchwork.json.
 *
 * Limitation: Patchwork passes arguments by value, so the by-reference
 * $file/$line arguments of headers_sent() can't be populated. The class sees
 * '' and 0 for them, so tests match the message format rather than values.
 */
#[CoversClass(WordPressCookie::class)]
#[CoversClass(WordPressCookieException::class)]
final class WordPressCookieTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const COOKIE_NAME = 'my_cookie';
    private const HASH_KEY = 'secret-key';
    private const EXISTING_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
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

    public function testExistingCookieValueIsUsedAsTransientKey(): void
    {
        Functions\expect('get_transient')->once()->with(self::EXISTING_HASH)->andReturn('stored');

        $this->assertSame('stored', $this->withExistingCookie()->getCookieValue());
    }

    // ---------------------------------------------------------------
    // Construction: cookie present but invalid (client-controlled input)
    // ---------------------------------------------------------------

    /**
     * 
     * @return array<string,mixed>
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

        $this->headersSent(false);
        Functions\when('uniqid')->justReturn('fresh');
        Functions\when('time')->justReturn(1_000);
        Functions\expect('setcookie')
            ->once()
            ->with(self::COOKIE_NAME, $expectedHash, 1_000 + 86400, '/', '', false, true)
            ->andReturn(true);

        // The invalid client value must never be used as a transient key.
        Functions\expect('get_transient')->once()->with($expectedHash)->andReturn(false);
        Functions\expect('set_transient')->once()->with($expectedHash, 'x', 86400)->andReturn(true);
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
        Functions\expect('set_transient')->once()->with($hash, 'v', 86400)->andReturn(true);

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
                static fn (string $msg): bool => preg_match(self::HEADERS_SENT_MESSAGE, $msg) === 1
            ));

        try {
            $this->makeCookie();
            $this->fail('Expected WordPressCookieException');
        }
        catch (WordPressCookieException) {
            // expected
        }
    }

    public function testDoesNotSetCookieWhenHeadersSent(): void
    {
        $this->headersSent(true);
        Functions\expect('setcookie')->never();

        try {
            $this->makeCookie();
        }
        catch (WordPressCookieException) {
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

    public function testGetCookieValueReturnsNullWhenNoTransient(): void
    {
        // WordPress returns false for a missing/expired transient.
        Functions\expect('get_transient')->once()->with(self::EXISTING_HASH)->andReturn(false);

        $this->assertNull($this->withExistingCookie()->getCookieValue());
    }

    /**
     * 
     * @return array<string,mixed>
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
            'empty array'   => [[]],
            'array'         => [['a' => 1, 'b' => [2, 3]]],
            'object'        => [(object) ['x' => 'y']],
        ];
    }

    #[DataProvider('storedValuesProvider')]
    public function testGetCookieValueReturnsStoredValue(mixed $value): void
    {
        Functions\expect('get_transient')->once()->with(self::EXISTING_HASH)->andReturn($value);

        $this->assertEquals($value, $this->withExistingCookie()->getCookieValue());
    }

    public function testGetCookieValueUsesGeneratedHashForNewCookie(): void
    {
        $this->headersSent(false);
        Functions\when('uniqid')->justReturn('u1');
        Functions\when('setcookie')->justReturn(true);
        Functions\expect('get_transient')->once()->with(self::hashFor('u1'))->andReturn('value');

        $this->assertSame('value', $this->makeCookie()->getCookieValue());
    }

    // ---------------------------------------------------------------
    // setCookieValue()
    // ---------------------------------------------------------------

    public function testSetCookieValueStoresTransientWithHashAndLifetime(): void
    {
        Functions\expect('set_transient')
            ->once()
            ->with(self::EXISTING_HASH, ['k' => 'v'], 1800)
            ->andReturn(true);

        $_COOKIE[self::COOKIE_NAME] = self::EXISTING_HASH;

        $this->assertTrue($this->makeCookie(1800)->setCookieValue(['k' => 'v']));
    }

    public function testSetCookieValueUsesDefaultLifetime(): void
    {
        Functions\expect('set_transient')->once()->with(self::EXISTING_HASH, 'x', 86400)->andReturn(true);

        $this->withExistingCookie()->setCookieValue('x');
    }

    public function testSetCookieValueReturnsFalseWhenTransientFails(): void
    {
        Functions\expect('set_transient')->once()->andReturn(false);

        $this->assertFalse($this->withExistingCookie()->setCookieValue('x'));
    }

    public function testSetThenGetRoundTrip(): void
    {
        $store = [];
        Functions\when('set_transient')->alias(static function (string $k, mixed $v) use (&$store): bool {
            $store[$k] = $v;
            return true;
        });
        Functions\when('get_transient')->alias(static function (string $k) use (&$store): mixed {
            return $store[$k] ?? false;
        });

        $cookie = $this->withExistingCookie();
        $cookie->setCookieValue('round-trip');

        $this->assertSame('round-trip', $cookie->getCookieValue());
    }

    // ---------------------------------------------------------------
    // deleteCookieValue()
    // ---------------------------------------------------------------

    public function testDeleteCookieValueDeletesTransientByHash(): void
    {
        Functions\expect('delete_transient')->once()->with(self::EXISTING_HASH);

        $this->withExistingCookie()->deleteCookieValue();
    }

    public function testValueIsGoneAfterDelete(): void
    {
        $store = [];
        Functions\when('set_transient')->alias(static function (string $k, mixed $v) use (&$store): bool {
            $store[$k] = $v;
            return true;
        });
        Functions\when('get_transient')->alias(static function (string $k) use (&$store): mixed {
            return $store[$k] ?? false;
        });
        Functions\when('delete_transient')->alias(static function (string $k) use (&$store): bool {
            unset($store[$k]);
            return true;
        });

        $cookie = $this->withExistingCookie();
        $cookie->setCookieValue('temp');
        $cookie->deleteCookieValue();

        $this->assertNull($cookie->getCookieValue());
    }

    public function testDeleteDoesNotTouchHttpCookie(): void
    {
        Functions\expect('setcookie')->never();
        Functions\when('delete_transient')->justReturn(true);

        $this->withExistingCookie()->deleteCookieValue();
    }
}
