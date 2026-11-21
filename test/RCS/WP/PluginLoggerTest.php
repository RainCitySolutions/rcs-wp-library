<?php
declare(strict_types=1);
namespace RCS\WP {

    use Monolog\Handler\RotatingFileHandler;
    use Monolog\Level;
    use Monolog\Logger;
    use Monolog\Processor\IntrospectionProcessor;
    use Monolog\Processor\PsrLogMessageProcessor;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;
    use Psr\Log\LoggerInterface;
    use RCS\Logging\BaseLogger;
    use RCS\WP\PluginInfoInterface;
    use RCS\WP\PluginLogger;

    /**
     * Minimal stand-in for WP_User.
     */
    final class StubWpUser
    {
        public function __construct(
            public int $ID = 0,
            public string $user_login = '',
            private bool $exists = false
        ) {
        }

        public function exists(): bool
        {
            return $this->exists;
        }
    }

    /**
     * Shared, resettable state for the global WordPress function stubs below.
     */
    final class WpStubs
    {
        /** @var string[] */
        public static array $mkdirCalls = [];

        public static ?StubWpUser $user = null;

        public static function reset(): void
        {
            self::$mkdirCalls = [];
            self::$user = new StubWpUser();
        }
    }

    #[CoversClass(PluginLogger::class)]
    final class PluginLoggerTest extends TestCase
    {
        private const SLUG = 'my-plugin';

        private string $baseDir;
        private string $logDir;

        /** @var array<string, mixed> */
        private array $serverBackup;

        protected function setUp(): void
        {
            $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'pluginlogger_' . uniqid('', true) . DIRECTORY_SEPARATOR;
            mkdir($this->baseDir, 0777, true);
            $this->logDir = $this->baseDir . 'logs';

            $this->serverBackup = $_SERVER;
            $_SERVER['REQUEST_TIME_FLOAT'] = 1700000000.123;
            $_SERVER['REQUEST_TIME'] = 1700000000;

            WpStubs::reset();
        }

        protected function tearDown(): void
        {
            $_SERVER = $this->serverBackup;
            WpStubs::reset();
            $this->removeDir($this->baseDir);
        }

        // ------------------------------------------------------------------
        // Helpers
        // ------------------------------------------------------------------

        private function makeLogger(string $slug = self::SLUG): PluginLogger
        {
            $info = $this->createMock(PluginInfoInterface::class);
            // getWriteDir() is expected to end with a separator (the class appends 'logs' directly).
            $info->method('getWriteDir')->willReturn($this->baseDir);
            $info->method('getSlug')->willReturn($slug);

            return new PluginLogger($info);
        }

        private function backingLogger(PluginLogger $logger): Logger
        {
            $prop = new \ReflectionProperty(BaseLogger::class, 'backingLogger');
            $backing = $prop->getValue($logger);
            $this->assertInstanceOf(Logger::class, $backing);

            return $backing;
        }

        /**
         * @return string[] Paths of log files in the log dir.
         */
        private function logFiles(): array
        {
            $files = glob($this->logDir . DIRECTORY_SEPARATOR . '*.log');

            return $files === false ? [] : $files;
        }

        /**
         * Flush/close handlers and return everything that was written.
         */
        private function readLog(PluginLogger $logger): string
        {
            $this->backingLogger($logger)->close();

            $contents = '';
            foreach ($this->logFiles() as $file) {
                $contents .= (string) file_get_contents($file);
            }

            return $contents;
        }

        private function removeDir(string $dir): void
        {
            if (!is_dir($dir)) {
                return;
            }
            $items = scandir($dir);
            foreach ($items === false ? [] : $items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                is_dir($path) ? $this->removeDir($path) : unlink($path);
            }
            rmdir($dir);
        }

        // ------------------------------------------------------------------
        // Construction
        // ------------------------------------------------------------------

        public function testIsABaseLoggerAndPsrLogger(): void
        {
            $logger = $this->makeLogger();

            $this->assertInstanceOf(BaseLogger::class, $logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);
        }

        public function testConstructorCreatesLogDirectoryUnderWriteDir(): void
        {
            $this->makeLogger();

            $this->assertSame([$this->logDir], WpStubs::$mkdirCalls);
            $this->assertDirectoryExists($this->logDir);
        }

        public function testConstructorToleratesExistingLogDirectory(): void
        {
            $this->makeLogger();
            $this->makeLogger();

            $this->assertDirectoryExists($this->logDir);
            $this->assertCount(2, WpStubs::$mkdirCalls);
        }

        public function testSlugIsUsedAsLoggerName(): void
        {
            $logger = $this->makeLogger('another-slug');

            $this->assertSame('another-slug', $this->backingLogger($logger)->getName());
        }

        // ------------------------------------------------------------------
        // Handler / processor wiring
        // ------------------------------------------------------------------

        public function testUsesSingleRotatingFileHandlerAtDebugLevelKeeping14Files(): void
        {
            $backing = $this->backingLogger($this->makeLogger());

            $handlers = $backing->getHandlers();
            $this->assertCount(1, $handlers);

            $handler = $handlers[0];
            $this->assertInstanceOf(RotatingFileHandler::class, $handler);
            $this->assertSame(Level::Debug, $handler->getLevel());

            $maxFiles = new \ReflectionProperty(RotatingFileHandler::class, 'maxFiles');
            $this->assertSame(14, $maxFiles->getValue($handler));
        }

        public function testRegistersExpectedProcessors(): void
        {
            $processors = $this->backingLogger($this->makeLogger())->getProcessors();

            $this->assertCount(5, $processors);
            $this->assertNotEmpty(array_filter(
                $processors,
                static fn($p): bool => $p instanceof IntrospectionProcessor
            ));
            $this->assertNotEmpty(array_filter(
                $processors,
                static fn($p): bool => $p instanceof PsrLogMessageProcessor
            ));
        }

        // ------------------------------------------------------------------
        // Log file output
        // ------------------------------------------------------------------

        public function testWritesToDatedLogFileNamedAfterSlug(): void
        {
            $logger = $this->makeLogger();

            $logger->info('hello');
            $this->readLog($logger);

            $files = $this->logFiles();
            $this->assertCount(1, $files);
            $this->assertMatchesRegularExpression(
                '/^' . preg_quote(self::SLUG, '/') . '-\d{4}-\d{2}-\d{2}\.log$/',
                basename($files[0])
            );
        }

        public function testMultipleMessagesAreAppendedAsSeparateLines(): void
        {
            $logger = $this->makeLogger();

            $logger->info('first');
            $logger->info('second');
            $lines = array_values(array_filter(
                explode(PHP_EOL, $this->readLog($logger)),
                static fn(string $l): bool => $l !== ''
            ));

            $this->assertCount(2, $lines);
            $this->assertStringContainsString('first', $lines[0]);
            $this->assertStringContainsString('second', $lines[1]);
        }

        /**
         * @return array<string, array{string, string}>
         */
        public static function levelProvider(): array
        {
            return [
                'debug' => ['debug', 'DEB'],
                'info' => ['info', 'INF'],
                'notice' => ['notice', 'NOT'],
                'warning' => ['warning', 'WAR'],
                'error' => ['error', 'ERR'],
                'critical' => ['critical', 'CRI'],
                'alert' => ['alert', 'ALE'],
                'emergency' => ['emergency', 'EME'],
            ];
        }

        #[DataProvider('levelProvider')]
        public function testEveryLevelIsWrittenWithThreeLetterLevelName(string $method, string $levelName): void
        {
            $logger = $this->makeLogger();

            $logger->$method('level check');
            $log = $this->readLog($logger);

            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-z]{2} \d{2} \d{2}:\d{2}:\d{2} ' . $levelName . ' /',
                $log
            );
            $this->assertStringContainsString(': level check', $log);
        }

        public function testLineUsesDefaultDateFormat(): void
        {
            $logger = $this->makeLogger();

            $logger->info('date check');
            $log = $this->readLog($logger);

            $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2} \d{2} \d{2}:\d{2}:\d{2} /', $log);
        }

        // ------------------------------------------------------------------
        // PSR message processor
        // ------------------------------------------------------------------

        public function testPsrPlaceholdersAreInterpolatedAndRemovedFromContext(): void
        {
            $logger = $this->makeLogger();

            $logger->info('Hello {name}', ['name' => 'World']);
            $log = $this->readLog($logger);

            $this->assertStringContainsString('Hello World', $log);
            $this->assertStringNotContainsString('{name}', $log);
            // removeUsedContextFields = true, so the used key must not be repeated in %context%
            $this->assertStringNotContainsString('"name"', $log);
        }

        public function testUnusedContextIsStillWritten(): void
        {
            $logger = $this->makeLogger();

            $logger->info('Plain message', ['extra' => 'data']);
            $log = $this->readLog($logger);

            $this->assertStringContainsString('{"extra":"data"}', $log);
        }

        // ------------------------------------------------------------------
        // Request id processor
        // ------------------------------------------------------------------

        public function testRequestIdUsesRequestTimeFloatPaddedWithTrailingZeros(): void
        {
            $_SERVER['REQUEST_TIME_FLOAT'] = 1700000000.123;
            $logger = $this->makeLogger();

            $logger->info('req id');
            $log = $this->readLog($logger);

            $this->assertStringContainsString('[1700000000.1230]', $log);
        }

        public function testRequestIdFallsBackToRequestTime(): void
        {
            unset($_SERVER['REQUEST_TIME_FLOAT']);
            $_SERVER['REQUEST_TIME'] = 1700000000;
            $logger = $this->makeLogger();

            $logger->info('req id');
            $log = $this->readLog($logger);

            $this->assertStringContainsString('[170000000000000]', $log);
        }

        // ------------------------------------------------------------------
        // User processor
        // ------------------------------------------------------------------

        public function testUserIsOmittedWhenNotLoggedIn(): void
        {
            WpStubs::$user = new StubWpUser(0, '', false);
            $logger = $this->makeLogger();

            $logger->info('anonymous');
            $log = $this->readLog($logger);

            $this->assertStringContainsString('(/)', $log);
        }

        public function testUserIdAndNameAreIncludedWhenLoggedIn(): void
        {
            WpStubs::$user = new StubWpUser(7, 'alice', true);
            $logger = $this->makeLogger();

            $logger->info('authenticated');
            $log = $this->readLog($logger);

            $this->assertStringContainsString('(7/alice)', $log);
        }

        // ------------------------------------------------------------------
        // Class name processor
        // ------------------------------------------------------------------

        // Nov 2026 - Doesn't work because Patchwork has something at the top of the call stack
        // public function testClassNameHasNoNamespaceAndIsPaddedTo30Characters(): void
        // {
        //     $logger = $this->makeLogger();

        //     $logger->info('class check');
        //     $log = $this->readLog($logger);

        //     // Format: "<date> INF <class padded to 30> [<reqId>] ..."
        //     $matched = preg_match('/ INF (?<class>[^\s\[\\\\]+ *) \[/', $log, $m);

        //     $this->assertSame(
        //         1,
        //         $matched,
        //         'Class column should contain a namespace-free class name. Log line: ' . $log
        //     );
        //     $this->assertSame(30, strlen($m['class']));
        // }
    }
}

namespace {

    use RCS\WP\WpStubs;

    // Global WordPress stubs. Guarded so a real WP test bootstrap takes precedence.
    // Note: PluginLogger uses function_exists('wp_get_current_user') with a string,
    // so this stub must live in the global namespace.

    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p(string $target): bool
        {
            WpStubs::$mkdirCalls[] = $target;

            return is_dir($target) || mkdir($target, 0777, true);
        }
    }

    if (!function_exists('wp_get_current_user')) {
        function wp_get_current_user(): object
        {
            return WpStubs::$user ?? new \RCS\WP\StubWpUser();
        }
    }
}
