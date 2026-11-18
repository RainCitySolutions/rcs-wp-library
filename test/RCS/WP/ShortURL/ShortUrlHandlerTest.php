<?php

declare(strict_types=1);

namespace RCS\WP\ShortURL;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Requires (composer require --dev):
 *   phpunit/phpunit ^11.0, brain/monkey ^2.6, mockery/mockery ^1.6
 */

// WordPress constant used by get_page_by_path() calls.
if (!\defined('OBJECT')) {
    \define('OBJECT', 'OBJECT');
}

/**
 * Minimal stand-in for \wpdb so Mockery has a real class to mock.
 */
class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';

    public function get_charset_collate(): string
    {
        return '';
    }

    public function prepare(string $query, mixed ...$args): string
    {
        return $query;
    }

    public function get_var(string $query): mixed
    {
        return null;
    }

    /** @return array<int, object> */
    public function get_results(string $query): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $format
     */
    public function insert(string $table, array $data, array $format): int|false
    {
        return false;
    }

    /** @param array<string, mixed> $where */
    public function delete(string $table, array $where): int|false
    {
        return false;
    }
}

/**
 * Test double that removes the network dependency (curl) and exposes the
 * protected methods we want to test directly.
 */
final class TestableShortUrlHandler extends ShortUrlHandler
{
    public bool $urlReachable = true;

    /** @var list<string> */
    public array $verifiedUrls = [];

    protected function verifyUrlExists(string $fqUrl): bool
    {
        $this->verifiedUrls[] = $fqUrl;

        return $this->urlReachable;
    }

    public function exposeValidateUrlFormat(string $url): mixed
    {
        return $this->validateUrlFormat($url);
    }

    /** @param array<string, string> $parts */
    public function exposeBuildUrl(array $parts): string
    {
        return $this->build_url($parts);
    }

    public function exposeValidateUrl(string $url): string
    {
        return $this->validateUrl($url);
    }
}

#[CoversClass(ShortUrlHandler::class)]
final class ShortUrlHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const HOME = 'https://example.com';
    private const TABLE = 'wp_raincity_wpf_short_urls';
    private const PAGE_TYPES = ['page', 'post', 'attachement']; // (sic) spelling matches the class

    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->serverBackup = $_SERVER;
        ShortUrlHandler::$verifyUrlReferences = true;

        Functions\when('home_url')->alias(
            static fn(string $path = ''): string => self::HOME . ($path === '' ? '' : '/' . ltrim($path, '/'))
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        ShortUrlHandler::$verifyUrlReferences = true;
        unset($GLOBALS['wpdb']);

        Monkey\tearDown();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function newHandler(string $prefix = '/su/'): TestableShortUrlHandler
    {
        return new TestableShortUrlHandler($prefix);
    }

    /**
     * Creates a mock $wpdb, installs it as the global, and makes prepare()
     * substitute %s placeholders so queries can be matched by pattern.
     */
    private function createWpdb(): MockInterface&FakeWpdb
    {
        /** @var MockInterface&FakeWpdb $wpdb */
        $wpdb = Mockery::mock(FakeWpdb::class);
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturnUsing(
            static fn(string $query, mixed ...$args): string => vsprintf(str_replace('%s', "'%s'", $query), $args)
        );

        $GLOBALS['wpdb'] = $wpdb;

        return $wpdb;
    }

    /** Stubs the "does this long URL already have a short code?" lookup. */
    private function stubUrlLookup(MockInterface $wpdb, ?string $result): void
    {
        $wpdb->shouldReceive('get_var')
            ->with(Mockery::pattern('/SELECT short_code FROM .* WHERE long_url/'))
            ->andReturn($result);
    }

    // ------------------------------------------------------------------
    // Constructor
    // ------------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function validPrefixProvider(): array
    {
        return [
            'default prefix'            => ['su'],
            'already slashed'           => ['/su/'],
            'single character'          => ['a'],
            'backslashes'               => ['\\su\\'],
            'max length (30 chars)'     => [str_repeat('a', 30)],
        ];
    }

    #[DataProvider('validPrefixProvider')]
    public function testConstructorAcceptsValidPrefix(string $prefix): void
    {
        $handler = new ShortUrlHandler($prefix);

        $this->assertInstanceOf(ShortUrlHandler::class, $handler);
    }

    /** @return array<string, array{string}> */
    public static function invalidPrefixProvider(): array
    {
        return [
            'empty'                     => [''],
            'only a slash'              => ['/'],
            'only slashes'              => ['//'],
            'only whitespace'           => ['   '],
            'too long (31 chars)'       => [str_repeat('a', 31)],
        ];
    }

    #[DataProvider('invalidPrefixProvider')]
    public function testConstructorRejectsInvalidPrefix(string $prefix): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid format for Short URL prefix');

        new ShortUrlHandler($prefix);
    }

    public function testConstructorRegistersHooks(): void
    {
        Actions\expectAdded('plugins_loaded')
            ->once()
            ->with([ShortUrlHandler::class, 'upgradeDbTable']);

        Actions\expectAdded('template_redirect')
            ->once()
            ->with(
                Mockery::on(
                    static fn($cb): bool => is_array($cb)
                        && $cb[0] instanceof ShortUrlHandler
                        && $cb[1] === 'templateRedirectAction'
                ),
                9
            );

        new ShortUrlHandler('/su/');
    }

    public function testConstructorDoesNotRegisterHooksWhenPrefixInvalid(): void
    {
        Actions\expectAdded('plugins_loaded')->never();
        Actions\expectAdded('template_redirect')->never();

        try {
            new ShortUrlHandler('');
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    // ------------------------------------------------------------------
    // upgradeDbTable
    // ------------------------------------------------------------------

    public function testUpgradeDbTableRunsDbDeltaWithCreateTableSql(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_charset_collate')->once()->andReturn('DEFAULT CHARACTER SET utf8mb4');

        Functions\expect('dbDelta')
            ->once()
            ->with(Mockery::on(static function ($sql): bool {
                return is_string($sql)
                    && str_contains($sql, 'CREATE TABLE ' . self::TABLE)
                    && str_contains($sql, 'short_code varchar(32) NOT NULL')
                    && str_contains($sql, 'long_url varchar(255) NOT NULL')
                    && str_contains($sql, 'UNIQUE KEY short_code (short_code)')
                    && str_contains($sql, 'DEFAULT CHARACTER SET utf8mb4');
            }));

        ShortUrlHandler::upgradeDbTable();
    }

    // ------------------------------------------------------------------
    // build_url
    // ------------------------------------------------------------------

    /** @return array<string, array{array<string, string>, string}> */
    public static function buildUrlProvider(): array
    {
        return [
            'all parts' => [
                [
                    'scheme' => 'https',
                    'user' => 'u',
                    'pass' => 'p',
                    'host' => 'example.org',
                    'port' => '8080',
                    'path' => '/a/b',
                    'query' => 'x=1&y=2',
                    'fragment' => 'top',
                ],
                'https://u:p@example.org:8080/a/b?x=1&y=2#top',
            ],
            'scheme, host and path' => [
                ['scheme' => 'http', 'host' => 'example.org', 'path' => '/a'],
                'http://example.org/a',
            ],
            'user without password' => [
                ['scheme' => 'https', 'user' => 'u', 'host' => 'example.org'],
                'https://u@example.org',
            ],
            'path only' => [
                ['path' => '/a'],
                '/a',
            ],
            'empty' => [
                [],
                '',
            ],
        ];
    }

    /** @param array<string, string> $parts */
    #[DataProvider('buildUrlProvider')]
    public function testBuildUrl(array $parts, string $expected): void
    {
        $this->assertSame($expected, $this->newHandler()->exposeBuildUrl($parts));
    }

    // ------------------------------------------------------------------
    // validateUrlFormat
    // ------------------------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function validUrlFormatProvider(): array
    {
        return [
            'absolute URL is unchanged'     => ['https://example.org/page?x=1#frag', 'https://example.org/page?x=1#frag'],
            'root-relative path'            => ['/about', 'https://example.com/about'],
            'relative path gets a slash'    => ['about', 'https://example.com/about'],
            'relative path with query'      => ['/about?x=1', 'https://example.com/about?x=1'],
        ];
    }

    #[DataProvider('validUrlFormatProvider')]
    public function testValidateUrlFormatReturnsFullyQualifiedUrl(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->newHandler()->exposeValidateUrlFormat($input));
    }

    public function testValidateUrlFormatReturnsFalseForInvalidUrl(): void
    {
        // Host labels may not start with a hyphen.
        $this->assertFalse($this->newHandler()->exposeValidateUrlFormat('http://-example.com'));
    }

    // ------------------------------------------------------------------
    // validateUrl
    // ------------------------------------------------------------------

    public function testValidateUrlThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL does not have a valid format.');
        $this->expectExceptionCode(400);

        $this->newHandler()->exposeValidateUrl('http://-example.com');
    }

    /** @return array<string, array{string}> */
    public static function internalUrlProvider(): array
    {
        return [
            'absolute on our host' => ['https://example.com/about'],
            'root-relative'        => ['/about'],
            'relative'             => ['about'],
        ];
    }

    #[DataProvider('internalUrlProvider')]
    public function testValidateUrlReturnsSitePathForExistingPage(string $input): void
    {
        Functions\expect('get_page_by_path')
            ->once()
            ->with('/about', OBJECT, self::PAGE_TYPES)
            ->andReturn((object) ['ID' => 42]);

        $handler = $this->newHandler();
        $handler->urlReachable = false; // proves we never fall back to an HTTP check

        $this->assertSame('/about', $handler->exposeValidateUrl($input));
        $this->assertSame([], $handler->verifiedUrls);
    }

    public function testValidateUrlFallsBackToHttpCheckWhenPageNotFound(): void
    {
        Functions\expect('get_page_by_path')->once()->andReturn(null);

        $handler = $this->newHandler();

        $this->assertSame('/custom-route', $handler->exposeValidateUrl('/custom-route'));
        $this->assertSame([self::HOME . '/custom-route'], $handler->verifiedUrls);
    }

    public function testValidateUrlThrows404WhenInternalPageMissingAndUnreachable(): void
    {
        Functions\expect('get_page_by_path')->once()->andReturn(null);

        $handler = $this->newHandler();
        $handler->urlReachable = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page or URL does not appear to exist.');
        $this->expectExceptionCode(404);

        $handler->exposeValidateUrl('/missing');
    }

    public function testValidateUrlSkipsVerificationForMissingInternalPageWhenDisabled(): void
    {
        ShortUrlHandler::$verifyUrlReferences = false;
        Functions\expect('get_page_by_path')->once()->andReturn(null);

        $handler = $this->newHandler();
        $handler->urlReachable = false;

        $this->assertSame('/missing', $handler->exposeValidateUrl('/missing'));
        $this->assertSame([], $handler->verifiedUrls);
    }

    public function testValidateUrlReturnsExternalUrlUnchangedWhenReachable(): void
    {
        $handler = $this->newHandler();

        $this->assertSame('https://example.org/page', $handler->exposeValidateUrl('https://example.org/page'));
        $this->assertSame(['https://example.org/page'], $handler->verifiedUrls);
    }

    public function testValidateUrlThrows404WhenExternalUrlUnreachable(): void
    {
        $handler = $this->newHandler();
        $handler->urlReachable = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL does not appear to exist.');
        $this->expectExceptionCode(404);

        $handler->exposeValidateUrl('https://example.org/gone');
    }

    public function testValidateUrlSkipsVerificationForExternalUrlWhenDisabled(): void
    {
        ShortUrlHandler::$verifyUrlReferences = false;

        $handler = $this->newHandler();
        $handler->urlReachable = false;

        $this->assertSame('https://example.org/gone', $handler->exposeValidateUrl('https://example.org/gone'));
        $this->assertSame([], $handler->verifiedUrls);
    }

    // ------------------------------------------------------------------
    // createShortUrl
    // ------------------------------------------------------------------

    public function testCreateShortUrlRejectsEmptyUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No URL was supplied.');
        $this->expectExceptionCode(400);

        $this->newHandler()->createShortUrl('');
    }

    public function testCreateShortUrlRejectsUrlLongerThan255Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Long URL must be 255 characters or less.');
        $this->expectExceptionCode(400);

        $this->newHandler()->createShortUrl('https://example.org/' . str_repeat('a', 236)); // 256 chars
    }

    public function testCreateShortUrlAcceptsUrlOfExactly255Characters(): void
    {
        $url = 'https://example.org/' . str_repeat('a', 235); // 255 chars
        $this->assertSame(255, strlen($url));

        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, 'existing1');

        $this->assertSame(
            self::HOME . '/su/existing1',
            $this->newHandler()->createShortUrl($url)
        );
    }

    public function testCreateShortUrlRejectsInvalidUrlFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL does not have a valid format.');
        $this->expectExceptionCode(400);

        $this->newHandler()->createShortUrl('http://-example.com');
    }

    public function testCreateShortUrlPropagatesNotFoundForUnreachableUrl(): void
    {
        $handler = $this->newHandler();
        $handler->urlReachable = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(404);

        $handler->createShortUrl('https://example.org/gone');
    }

    public function testCreateShortUrlReusesExistingShortCode(): void
    {
        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, 'abcd1234');
        $wpdb->shouldReceive('insert')->never();

        $result = $this->newHandler()->createShortUrl('https://example.org/page');

        $this->assertSame(self::HOME . '/su/abcd1234', $result);
    }

    public function testCreateShortUrlInsertsNewMappingAndReturnsShortUrl(): void
    {
        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('get_var')
            ->once()
            ->with(Mockery::pattern('/SELECT 1 FROM .* WHERE short_code/'))
            ->andReturn(null);

        $stored = [];
        $wpdb->shouldReceive('insert')
            ->once()
            ->with(self::TABLE, Mockery::type('array'), ['%s', '%s', '%d'])
            ->andReturnUsing(static function (string $table, array $data) use (&$stored): int {
                $stored = $data;

                return 1;
            });

        $result = $this->newHandler()->createShortUrl('https://example.org/page');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $stored['short_code']);
        $this->assertSame('https://example.org/page', $stored['long_url']);
        $this->assertFalse($stored['front_end']);
        $this->assertSame(self::HOME . '/su/' . $stored['short_code'], $result);
    }

    public function testCreateShortUrlFlagsFrontEndRequests(): void
    {
        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('get_var')
            ->with(Mockery::pattern('/SELECT 1 FROM/'))
            ->andReturn(null);

        $wpdb->shouldReceive('insert')
            ->once()
            ->with(
                self::TABLE,
                Mockery::on(static fn(array $d): bool => $d['front_end'] === true),
                ['%s', '%s', '%d']
            )
            ->andReturn(1);

        $this->newHandler()->createShortUrl('https://example.org/page', true);
    }

    public function testCreateShortUrlStoresSitePathForInternalUrls(): void
    {
        Functions\expect('get_page_by_path')->once()->andReturn((object) ['ID' => 1]);

        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('get_var')
            ->with(Mockery::pattern('/SELECT 1 FROM/'))
            ->andReturn(null);
        $wpdb->shouldReceive('insert')
            ->once()
            ->with(
                self::TABLE,
                Mockery::on(static fn(array $d): bool => $d['long_url'] === '/about'),
                ['%s', '%s', '%d']
            )
            ->andReturn(1);

        $this->newHandler()->createShortUrl('https://example.com/about');
    }

    public function testCreateShortUrlRegeneratesCodeOnCollision(): void
    {
        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, null);

        // First candidate code already exists, second one is free.
        $wpdb->shouldReceive('get_var')
            ->twice()
            ->with(Mockery::pattern('/SELECT 1 FROM/'))
            ->andReturn('1', null);

        $wpdb->shouldReceive('insert')->once()->andReturn(1);

        $this->newHandler()->createShortUrl('https://example.org/page');
    }

    public function testCreateShortUrlThrowsWhenInsertFails(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->last_error = 'Something broke';
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('get_var')
            ->with(Mockery::pattern('/SELECT 1 FROM/'))
            ->andReturn(null);
        $wpdb->shouldReceive('insert')->once()->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Something broke');

        $this->newHandler()->createShortUrl('https://example.org/page');
    }

    // ------------------------------------------------------------------
    // addShortUrl
    // ------------------------------------------------------------------

    public function testAddShortUrlRejectsEmptyShortCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No Short URL supplied.');
        $this->expectExceptionCode(400);

        $this->newHandler()->addShortUrl('', 'https://example.org/page');
    }

    public function testAddShortUrlRejectsShortCodeLongerThan32Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Short URL must be 32 characters or less.');
        $this->expectExceptionCode(400);

        $this->newHandler()->addShortUrl(str_repeat('a', 33), 'https://example.org/page');
    }

    public function testAddShortUrlAcceptsShortCodeOfExactly32Characters(): void
    {
        $code = str_repeat('a', 32);

        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('insert')->once()->andReturn(1);

        $this->assertSame(
            self::HOME . '/su/' . $code,
            $this->newHandler()->addShortUrl($code, 'https://example.org/page')
        );
    }

    public function testAddShortUrlRejectsEmptyLongUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No URL was supplied.');
        $this->expectExceptionCode(400);

        $this->newHandler()->addShortUrl('promo', '');
    }

    public function testAddShortUrlRejectsLongUrlLongerThan255Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Long URL must be 255 characters or less.');
        $this->expectExceptionCode(400);

        $this->newHandler()->addShortUrl('promo', 'https://example.org/' . str_repeat('a', 236));
    }

    public function testAddShortUrlRejectsInvalidUrlFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL does not have a valid format.');

        $this->newHandler()->addShortUrl('promo', 'http://-example.com');
    }

    public function testAddShortUrlInsertsCustomCodeAndReturnsShortUrl(): void
    {
        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('insert')
            ->once()
            ->with(
                self::TABLE,
                ['short_code' => 'promo', 'long_url' => 'https://example.org/page', 'front_end' => true],
                ['%s', '%s', '%d']
            )
            ->andReturn(1);

        $result = $this->newHandler()->addShortUrl('promo', 'https://example.org/page', true);

        $this->assertSame(self::HOME . '/su/promo', $result);
    }

    public function testAddShortUrlRejectsUrlThatAlreadyHasShortCode(): void
    {
        $wpdb = $this->createWpdb();
        $this->stubUrlLookup($wpdb, 'existing');
        $wpdb->shouldReceive('insert')->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The URL already has a short URL 'existing'");
        $this->expectExceptionCode(400);

        $this->newHandler()->addShortUrl('promo', 'https://example.org/page');
    }

    public function testAddShortUrlReportsDuplicateShortCode(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->last_error = "Duplicate entry 'promo' for key 'short_code'";
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('insert')->once()->andReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A URL has already been added with the short URL 'promo'");
        $this->expectExceptionCode(400);

        $this->newHandler()->addShortUrl('promo', 'https://example.org/page');
    }

    /**
     * Characterization test: documents CURRENT behaviour, where database
     * errors other than "Duplicate" are swallowed and a short URL is still
     * returned even though nothing was stored. Update this test if the
     * class is changed to rethrow.
     */
    public function testAddShortUrlCurrentlySwallowsNonDuplicateDbErrors(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->last_error = 'Lost connection to server';
        $this->stubUrlLookup($wpdb, null);
        $wpdb->shouldReceive('insert')->once()->andReturn(false);

        $result = $this->newHandler()->addShortUrl('promo', 'https://example.org/page');

        $this->assertSame(self::HOME . '/su/promo', $result);
    }

    // ------------------------------------------------------------------
    // getFrontEndUrls
    // ------------------------------------------------------------------

    public function testGetFrontEndUrlsMapsRowsToObjects(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_results')
            ->once()
            ->with(Mockery::pattern('/SELECT short_code, long_url FROM .*raincity_wpf_short_urls WHERE front_end = 1/'))
            ->andReturn([
                (object) ['short_code' => 'aaa', 'long_url' => '/about'],
                (object) ['short_code' => 'bbb', 'long_url' => 'https://example.org/x'],
            ]);

        $urls = $this->newHandler()->getFrontEndUrls();

        $this->assertCount(2, $urls);
        $this->assertEquals(
            (object) ['shortCode' => 'aaa', 'shortUrl' => '/su/aaa', 'longUrl' => '/about'],
            $urls[0]
        );
        $this->assertEquals(
            (object) ['shortCode' => 'bbb', 'shortUrl' => '/su/bbb', 'longUrl' => 'https://example.org/x'],
            $urls[1]
        );
    }

    public function testGetFrontEndUrlsReturnsEmptyArrayWhenNoRows(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_results')->once()->andReturn([]);

        $this->assertSame([], $this->newHandler()->getFrontEndUrls());
    }

    // ------------------------------------------------------------------
    // deleteShortCode
    // ------------------------------------------------------------------

    public function testDeleteShortCodeDeletesExistingRow(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_var')
            ->once()
            ->with(Mockery::pattern("/SELECT 1 FROM .* WHERE short_code = 'abc'/"))
            ->andReturn('1');
        $wpdb->shouldReceive('delete')
            ->once()
            ->with(self::TABLE, ['short_code' => 'abc'])
            ->andReturn(1);

        $this->newHandler()->deleteShortCode('abc');
    }

    public function testDeleteShortCodeDoesNothingWhenCodeDoesNotExist(): void
    {
        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_var')->once()->andReturn(null);
        $wpdb->shouldReceive('delete')->never();

        $this->newHandler()->deleteShortCode('nope');
    }

    // ------------------------------------------------------------------
    // templateRedirectAction
    // ------------------------------------------------------------------

    public function testTemplateRedirectRedirectsKnownShortCode(): void
    {
        $_SERVER['REQUEST_URI'] = '/su/abc12345';

        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_var')
            ->once()
            ->with(Mockery::pattern("/SELECT long_url FROM .* WHERE short_code = 'abc12345'/"))
            ->andReturn('https://example.org/long');

        Functions\expect('wp_redirect')->once()->with('https://example.org/long');

        $this->newHandler()->templateRedirectAction();
    }

    public function testTemplateRedirectIgnoresQueryStringWhenExtractingCode(): void
    {
        $_SERVER['REQUEST_URI'] = '/su/abc12345?utm_source=mail';

        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_var')
            ->once()
            ->with(Mockery::pattern("/WHERE short_code = 'abc12345'/"))
            ->andReturn('/about');

        Functions\expect('wp_redirect')->once()->with('/about');

        $this->newHandler()->templateRedirectAction();
    }

    public function testTemplateRedirectDoesNothingForUnknownShortCode(): void
    {
        $_SERVER['REQUEST_URI'] = '/su/unknown';

        $wpdb = $this->createWpdb();
        $wpdb->shouldReceive('get_var')->once()->andReturn(null);

        Functions\expect('wp_redirect')->never();

        $this->newHandler()->templateRedirectAction();
    }

    public function testTemplateRedirectDoesNothingWhenUriDoesNotMatchPrefix(): void
    {
        $_SERVER['REQUEST_URI'] = '/some/other/page';

        // No $wpdb installed: touching the database here would be an error.
        Functions\expect('wp_redirect')->never();

        $this->newHandler()->templateRedirectAction();
    }

    public function testTemplateRedirectDoesNothingWhenRequestUriIsMissing(): void
    {
        unset($_SERVER['REQUEST_URI']);

        Functions\expect('wp_redirect')->never();

        $this->newHandler()->templateRedirectAction();
    }

    public function testTemplateRedirectDoesNothingWhenShortCodeIsEmpty(): void
    {
        $_SERVER['REQUEST_URI'] = '/su/';

        // Empty code short-circuits before any query is prepared.
        Functions\expect('wp_redirect')->never();

        $this->newHandler()->templateRedirectAction();
    }
}
