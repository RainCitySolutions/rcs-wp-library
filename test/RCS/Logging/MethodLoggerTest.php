<?php
declare(strict_types=1);
namespace RCS\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RCS\Util\ReflectionHelper;

#[CoversClass(MethodLogger::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
final class MethodLoggerTest extends TestCase
{
    private const ENTERING = 'Entering ';
    private const EXITING = 'Exiting ';

    public function testConstructorLogsEnteringAndStartsTimer(): void
    {
        $messages = [];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
        ->method('debug')
        ->with(self::callback(function (string $msg) use (&$messages): bool {
            $messages[] = $msg;
            return 1 == preg_match('/^('.self::ENTERING.'|'.self::EXITING.').*/', $msg);
        }));

        $timer = $this->createMock(TimerInterface::class);
        $timer->expects(self::once())->method('start');

        // Instantiate the object (constructor should trigger debug + timer start)
        new MethodLogger($logger, $timer);      // NOSONAR - not useless

        self::assertCount(2, $messages);
        self::assertStringStartsWith(self::ENTERING, $messages[0]);
        self::assertStringContainsString('->', $messages[0]);
        self::assertStringStartsWith(self::EXITING, $messages[1]);
        self::assertStringContainsString('->', $messages[1]);
    }

    public function testDestructorLogsExitingAndStopsTimer(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $timer  = $this->createMock(TimerInterface::class);

        // Timer expectations
        $timer->expects(self::once())->method('start');
        $timer->expects(self::once())->method('stop');
        $timer->method('getTime')->willReturn('1.234 seconds');

        $loggedMessages = [];

        // Logger expectations (constructor + destructor)
        $logger->expects(self::exactly(2))
        ->method('debug')
        ->willReturnCallback(function (string $msg) use (&$loggedMessages): void {
            $loggedMessages[] = $msg;
        });

        // Create and destroy object manually
        $methodLogger = new MethodLogger($logger, $timer);
        unset($methodLogger); // Force destructor

        // Verify logs
        self::assertCount(2, $loggedMessages);
        self::assertStringStartsWith(self::ENTERING, $loggedMessages[0]);
        self::assertStringStartsWith(self::EXITING, $loggedMessages[1]);
        self::assertStringContainsString('after 1.234 seconds', $loggedMessages[1]);
    }

    public function testGetCallingMethodNameUsingReflection(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $timer  = $this->createStub(TimerInterface::class);

        $methodLogger = new MethodLogger($logger, $timer);

        $reflection = new \ReflectionClass($methodLogger);
        $method = $reflection->getMethod('getCallingMethodName');
        $method->setAccessible(true);

        // Call the private method
        $result = $method->invoke($methodLogger);

        self::assertIsString($result);
        self::assertStringContainsString('RCS\Logging\MethodLoggerTest', $result);
        self::assertStringContainsString('->', $result);
    }
}
