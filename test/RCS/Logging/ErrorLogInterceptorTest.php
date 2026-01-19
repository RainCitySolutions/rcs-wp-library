<?php
declare(strict_types=1);
namespace RCS\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

#[CoversClass(ErrorLogInterceptor::class)]
final class ErrorLogInterceptorTest extends TestCase
{
    private bool $restoreHandler;

    protected function setUp(): void
    {
        $this->restoreHandler = true;
    }

    protected function tearDown(): void
    {
        if ($this->restoreHandler) {
            // Always restore to prevent test interference
            restore_error_handler();
        }
    }

    public function testConstructorThrowsOnEmptyArray(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Constructor argument is not an array of errors to ignore');

        $this->restoreHandler = false;

        new ErrorLogInterceptor([]);    // NOSONAR - not useless
    }

    public function testConstructorThrowsWhenNoValidErrorTypes(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('No valid error types/numbers provided');

        $this->restoreHandler = false;

        new ErrorLogInterceptor([       // NOSONAR - not useless
            999999 => ['Some message']
        ]);
    }

    public function testIgnoredErrorIsFiltered(): void
    {
        $interceptor = new ErrorLogInterceptor([
            E_USER_WARNING => ['skip_me']
        ]);

        $result = $interceptor->handler(
            E_USER_WARNING,
            'This is a skip_me test warning',
            __FILE__,
            __LINE__
            );

        self::assertTrue($result, 'Ignored error should return true');
    }

    public function testNonIgnoredErrorCallsOriginalHandler(): void
    {
        // Set a dummy handler first
        set_error_handler(static fn() => false);

        $interceptor = new ErrorLogInterceptor([
            E_USER_WARNING => ['ignore_this']
        ]);

        $result = $interceptor->handler(
            E_USER_WARNING,
            'Different message',
            __FILE__,
            __LINE__
            );

        // Restore the handled added by ErrorLogInterceptor so tearDown()
        // will remove the static handler we added for this test.
        restore_error_handler();

        self::assertFalse($result, 'Non-ignored error should delegate to previous handler');
    }

    public function testHandlerReturnsFalseIfNoOriginalHandler(): void
    {
        $interceptor = new ErrorLogInterceptor([
            E_USER_NOTICE => ['foo']
        ]);

        // Simulate null origHandler
        $reflection = new \ReflectionClass($interceptor);
        $prop = $reflection->getProperty('origHandler');
        $prop->setAccessible(true);
        $prop->setValue($interceptor, null);

        $result = $interceptor->handler(E_USER_NOTICE, 'bar', __FILE__, __LINE__);

        self::assertFalse($result, 'Handler should return false when no original handler exists');
    }
}
