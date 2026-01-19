<?php
declare(strict_types=1);
namespace RCS\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ScopeTimer::class)]
final class ScopeTimerTest extends TestCase
{
    public function testConstructorStartsTimer(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $timer = $this->createMock(TimerInterface::class);
        $timer->expects(self::once())
              ->method('start');

        new ScopeTimer($logger, $timer, 'Executing...');
    }

    public function testDestructorLogsWithFormattedMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $timer  = $this->createMock(TimerInterface::class);

        // Timer should start and then provide a time string
        $timer->expects(self::once())->method('start');
        $timer->expects(self::once())->method('getTime')->willReturn('0.123 seconds');

        $expectedMessage = 'Finished work in 0.123 seconds';

        // Logger should log with formatted message
        $logger->expects(self::once())
               ->method('info')
               ->with($expectedMessage);

        // Create and immediately destroy ScopeTimer
        $scopeTimer = new ScopeTimer($logger, $timer, 'Finished work in %s');
        unset($scopeTimer);
    }

    public function testDestructorHandlesMessageWithoutPlaceholder(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $timer  = $this->createMock(TimerInterface::class);

        $timer->expects(self::once())->method('start');
        $timer->expects(self::once())->method('getTime')->willReturn('2.345 seconds');

        $expectedMessage = 'Process complete 2.345 seconds';

        // Even though the string has no "%s", sprintf will append argument safely
        $logger->expects(self::once())
               ->method('info')
               ->with($expectedMessage);

        $scopeTimer = new ScopeTimer($logger, $timer, 'Process complete %s');
        unset($scopeTimer);
    }
}
