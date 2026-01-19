<?php
declare(strict_types=1);
namespace RCS\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;

#[CoversClass(InMemoryLogger::class)]
final class InMemoryLoggerTest extends TestCase
{
    private InMemoryLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new InMemoryLogger();
    }

    public function testLoggerImplementsPsrInterface(): void
    {
        self::assertInstanceOf(\Psr\Log\LoggerInterface::class, $this->logger);
    }

    public function testSetupCreatesLoggerAndHandler(): void
    {
        // Using reflection to ensure setupLogger() configured everything correctly
        $reflection = new \ReflectionClass($this->logger);

        $loggerProp = $reflection->getProperty('logger');
        $loggerProp->setAccessible(true);
        $logger = $loggerProp->getValue($this->logger);

        $handlerProp = $reflection->getProperty('handler');
        $handlerProp->setAccessible(true);
        $handler = $handlerProp->getValue($this->logger);

        self::assertInstanceOf(Logger::class, $logger);
        self::assertInstanceOf(FormatterInterface::class, $handler->getFormatter(), 'Handler should have a formatter');
    }

    public function testLogMethodsProduceRecords(): void
    {
        $this->logger->debug('debug msg');
        $this->logger->info('info msg');
        $this->logger->notice('notice msg');
        $this->logger->warning('warning msg');
        $this->logger->error('error msg');
        $this->logger->critical('critical msg');
        $this->logger->alert('alert msg');
        $this->logger->emergency('emergency msg');

        $msgs = $this->logger->getLogMsgs();

        self::assertNotEmpty($msgs);
        self::assertCount(8, $msgs, 'Should have 8 log entries');
        self::assertStringContainsString('debug msg', implode("\n", $msgs));
        self::assertStringContainsString('info msg', implode("\n", $msgs));
    }

    public function testInterpolatesContextVariables(): void
    {
        $this->logger->info('User {user} logged in', ['user' => 'alice']);

        $msgs = $this->logger->getLogMsgs();

        self::assertNotEmpty($msgs);
        self::assertStringContainsString('alice', $msgs[0]);
    }

    public function testLogWithExplicitLevel(): void
    {
        $this->logger->log(Level::Warning, 'manual log test');
        $msgs = $this->logger->getLogMsgs();

        self::assertStringContainsString('manual log test', $msgs[0]);
    }

    public function testLogMessagesAreFormattedCorrectly(): void
    {
        $this->logger->info('formatted test');
        $msgs = $this->logger->getLogMsgs();

        self::assertMatchesRegularExpression(
            '/^[A-Z][a-z]{2} [0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} INF InMemoryLogger: formatted test \r?\n/',
            $msgs[0],
            'Message should match expected Monolog LineFormatter format'
            );
    }

    public function testGetLogMsgsReturnsEmptyWhenNoLogs(): void
    {
        $msgs = $this->logger->getLogMsgs();
        self::assertSame([], $msgs, 'Should return an empty array when no logs exist');
    }
}
