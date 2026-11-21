<?php
declare(strict_types=1);
namespace RCS\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RCS\Logging\BaseLogger;

/**
 * Concrete BaseLogger whose hooks can be configured from the test.
 */
class ConfigurableLogger extends BaseLogger
{
    /**
     * @param HandlerInterface[]              $testHandlers
     * @param array<callable>                 $testProcessors
     */
    public function __construct(
        string $name,
        private array $testHandlers = [],
        private array $testProcessors = [],
        private ?string $testMsgFormat = null,
        private ?string $testDateFormat = null
    ) {
        // Properties above are assigned before parent::__construct() runs,
        // which is required because the parent calls addHandlers()/addProcessors().
        parent::__construct($name);
    }

    protected function addHandlers(array &$handlers): void
    {
        foreach ($this->testHandlers as $h) {
            $handlers[] = $h;
        }
    }

    protected function addProcessors(array &$processors): void
    {
        foreach ($this->testProcessors as $p) {
            $processors[] = $p;
        }
    }

    protected function getMsgFormat(): string
    {
        return $this->testMsgFormat ?? parent::getMsgFormat();
    }

    protected function getDateFormat(): string
    {
        return $this->testDateFormat ?? parent::getDateFormat();
    }
}

/**
 * Subclass that relies entirely on the defaults.
 */
class DefaultsLogger extends BaseLogger
{
    public function exposeDateFormat(): string
    {
        return $this->getDateFormat();
    }

    public function exposeMsgFormat(): string
    {
        return $this->getMsgFormat();
    }
}

#[CoversClass(BaseLogger::class)]
final class BaseLoggerTest extends TestCase
{
    /**
     * @return array<string, array{string, Level}>
     */
    public static function levelMethodProvider(): array
    {
        return [
            'debug' => ['debug', Level::Debug],
            'info' => ['info', Level::Info],
            'notice' => ['notice', Level::Notice],
            'warning' => ['warning', Level::Warning],
            'error' => ['error', Level::Error],
            'critical' => ['critical', Level::Critical],
            'alert' => ['alert', Level::Alert],
            'emergency' => ['emergency', Level::Emergency],
        ];
    }

    // ------------------------------------------------------------------
    // Basics
    // ------------------------------------------------------------------

    public function testImplementsPsrLoggerInterface(): void
    {
        $logger = new ConfigurableLogger('test');

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testLoggingWithNoHandlersDoesNotThrow(): void
    {
        $logger = new ConfigurableLogger('test');

        $logger->info('nobody is listening');

        $this->addToAssertionCount(1);
    }

    public function testDefaultDateFormat(): void
    {
        $logger = new DefaultsLogger('test');

        $this->assertSame('M d H:i:s', $logger->exposeDateFormat());
    }

    public function testDefaultMessageFormat(): void
    {
        $logger = new DefaultsLogger('test');

        $expected = '%datetime% %level_name% %extra.class% [%extra.reqId%] '
            . '(%extra.userId%/%extra.userName%) : %message% %context%'
            . PHP_EOL;

        $this->assertSame($expected, $logger->exposeMsgFormat());
    }

    // ------------------------------------------------------------------
    // Level methods / delegation
    // ------------------------------------------------------------------

    #[DataProvider('levelMethodProvider')]
    public function testLevelMethodDelegatesToBackingLogger(string $method, Level $level): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler]);

        $logger->$method('hello world');

        $this->assertCount(1, $handler->getRecords());
        $record = $handler->getRecords()[0];
        $this->assertSame($level, $record->level);
        $this->assertSame('hello world', $record->message);
    }

    #[DataProvider('levelMethodProvider')]
    public function testLevelMethodPassesContext(string $method, Level $level): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler]);
        $context = ['foo' => 'bar', 'n' => 42];

        $logger->$method('with context', $context);

        $record = $handler->getRecords()[0];
        $this->assertSame($context, $record->context);
    }

    public function testLevelMethodAcceptsStringable(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler]);
        $message = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable message';
            }
        };

        $logger->info($message);

        $this->assertSame('stringable message', $handler->getRecords()[0]->message);
    }

    /**
     * @return array<string, array{string, Level}>
     */
    public static function psrLevelProvider(): array
    {
        return [
            'debug' => [LogLevel::DEBUG, Level::Debug],
            'info' => [LogLevel::INFO, Level::Info],
            'notice' => [LogLevel::NOTICE, Level::Notice],
            'warning' => [LogLevel::WARNING, Level::Warning],
            'error' => [LogLevel::ERROR, Level::Error],
            'critical' => [LogLevel::CRITICAL, Level::Critical],
            'alert' => [LogLevel::ALERT, Level::Alert],
            'emergency' => [LogLevel::EMERGENCY, Level::Emergency],
        ];
    }

    #[DataProvider('psrLevelProvider')]
    public function testLogDelegatesWithGivenLevel(string $psrLevel, Level $expected): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler]);

        $logger->log($psrLevel, 'generic log', ['k' => 'v']);

        $record = $handler->getRecords()[0];
        $this->assertSame($expected, $record->level);
        $this->assertSame('generic log', $record->message);
        $this->assertSame(['k' => 'v'], $record->context);
    }

    public function testLogAcceptsIntegerLevel(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler]);

        $logger->log(Level::Warning->value, 'int level');

        $this->assertSame(Level::Warning, $handler->getRecords()[0]->level);
    }

    public function testLoggerNameIsUsedAsChannel(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('my-channel', [$handler]);

        $logger->info('x');

        $this->assertSame('my-channel', $handler->getRecords()[0]->channel);
    }

    // ------------------------------------------------------------------
    // Handlers
    // ------------------------------------------------------------------

    public function testAllHandlersReceiveRecords(): void
    {
        $h1 = new TestHandler();
        $h2 = new TestHandler();
        $logger = new ConfigurableLogger('test', [$h1, $h2]);

        $logger->error('boom');

        $this->assertTrue($h1->hasErrorThatContains('boom'));
        $this->assertTrue($h2->hasErrorThatContains('boom'));
    }

    public function testFormattableHandlersReceiveLineFormatter(): void
    {
        $h1 = new TestHandler();
        $h2 = new TestHandler();

        // The formatter is attached as a side effect of construction.
        new ConfigurableLogger('test', [$h1, $h2]);

        $this->assertInstanceOf(LineFormatter::class, $h1->getFormatter());
        $this->assertInstanceOf(LineFormatter::class, $h2->getFormatter());
    }

    public function testNonFormattableHandlerIsStillPushed(): void
    {
        $handler = $this->createMock(HandlerInterface::class);
        $handler->method('isHandling')->willReturn(true);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(
                static fn(LogRecord $r): bool => $r->message === 'plain handler'
            ))
            ->willReturn(false);

        $logger = new ConfigurableLogger('test', [$handler]);

        $logger->info('plain handler');
    }

    // ------------------------------------------------------------------
    // Processors
    // ------------------------------------------------------------------

    public function testProcessorsAreApplied(): void
    {
        $handler = new TestHandler();
        $processor = static fn(LogRecord $r): LogRecord =>
            $r->with(extra: array_merge($r->extra, ['added' => 'yes']));

        $logger = new ConfigurableLogger('test', [$handler], [$processor]);

        $logger->info('processed');

        $this->assertSame('yes', $handler->getRecords()[0]->extra['added']);
    }

    public function testMultipleProcessorsAreAllApplied(): void
    {
        $handler = new TestHandler();
        $p1 = static fn(LogRecord $r): LogRecord =>
            $r->with(extra: array_merge($r->extra, ['one' => 1]));
        $p2 = static fn(LogRecord $r): LogRecord =>
            $r->with(extra: array_merge($r->extra, ['two' => 2]));

        $logger = new ConfigurableLogger('test', [$handler], [$p1, $p2]);

        $logger->info('processed');

        $extra = $handler->getRecords()[0]->extra;
        $this->assertSame(1, $extra['one']);
        $this->assertSame(2, $extra['two']);
    }

    public function testProcessorsAreNotInvokedWhenNothingHandlesTheRecord(): void
    {
        // Monolog skips processing if no handler is handling the record.
        $called = 0;
        $processor = static function (LogRecord $r) use (&$called): LogRecord {
            $called++;
            return $r;
        };
        $handler = new TestHandler(Level::Error);

        $logger = new ConfigurableLogger('test', [$handler], [$processor]);

        $logger->debug('ignored');
        $this->assertSame(0, $called);

        $logger->error('kept');
        $this->assertSame(1, $called);
    }

    // ------------------------------------------------------------------
    // Formatting
    // ------------------------------------------------------------------

    public function testLevelNameIsTruncatedToThreeCharacters(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler], [], '%level_name%|%message%');

        $logger->info('a');
        $logger->warning('b');
        $logger->emergency('c');

        $formatted = array_map(
            static fn(LogRecord $r): string => (string) $r->formatted,
            $handler->getRecords()
        );
        $this->assertSame(['INF|a', 'WAR|b', 'EME|c'], $formatted);
    }

    public function testDefaultFormatWithExtras(): void
    {
        $handler = new TestHandler();
        $processor = static fn(LogRecord $r): LogRecord => $r->with(extra: [
            'class' => 'MyClass',
            'reqId' => 'req-1',
            'userId' => 42,
            'userName' => 'alice',
        ]);
        $logger = new ConfigurableLogger('test', [$handler], [$processor]);

        $logger->info('hello', ['a' => 1]);

        $line = (string) $handler->getRecords()[0]->formatted;
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-z]{2} \d{2} \d{2}:\d{2}:\d{2} INF MyClass \[req-1\] \(42\/alice\) : hello \{"a":1\}\R$/',
            $line
        );
    }

    public function testDefaultFormatWithoutExtrasStillLogsMessage(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler]);

        $logger->info('no extras');

        $line = (string) $handler->getRecords()[0]->formatted;
        $this->assertStringContainsString('INF', $line);
        $this->assertStringContainsString(': no extras', $line);
        $this->assertStringNotContainsString('%extra', $line);
    }

    public function testFormattedLineEndsWithNewline(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler]);

        $logger->info('newline');

        $line = (string) $handler->getRecords()[0]->formatted;
        $this->assertStringEndsWith(PHP_EOL, $line);
    }

    public function testCustomMessageFormatIsUsed(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler], [], '[%channel%] %message%');

        $logger->info('custom');

        $this->assertSame('[test] custom', $handler->getRecords()[0]->formatted);
    }

    public function testCustomDateFormatIsUsed(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler], [], '%datetime%', 'Y');

        $logger->info('dated');

        $this->assertSame(date('Y'), $handler->getRecords()[0]->formatted);
    }

    public function testDefaultDateFormatIsApplied(): void
    {
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler], [], '%datetime%');

        $logger->info('dated');

        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-z]{2} \d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $handler->getRecords()[0]->formatted
        );
    }

    public function testNewlinesInMessageAreAllowed(): void
    {
        // The formatter is built with allowInlineLineBreaks = true.
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler], [], '%message%');

        $logger->info("line1\nline2");

        $this->assertSame("line1 line2", $handler->getRecords()[0]->formatted);
    }

    public function testEmptyContextIsRenderedAsEmptyArray(): void
    {
        // The formatter is built with ignoreEmptyContextAndExtra = false.
        $handler = new TestHandler();
        $logger = new ConfigurableLogger('test', [$handler], [], '%message% %context%');

        $logger->info('ctx');

        $this->assertSame('ctx ', $handler->getRecords()[0]->formatted);
    }
}
