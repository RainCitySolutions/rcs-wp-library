<?php
declare(strict_types=1);
namespace RCS\WP;

use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(PluginLogger::class)]
final class PluginLoggerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plugin_logger_test';
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
    }

    private function getPluginInfoMock(): PluginInfoInterface
    {
        $mock = $this->createMock(PluginInfoInterface::class);
        $mock->method('getWriteDir')->willReturn($this->logDir . DIRECTORY_SEPARATOR);
        $mock->method('getSlug')->willReturn('myplugin');
        return $mock;
    }

//    #[RunInSeparateProcess]
    public function testLoggerImplementsPsrLoggerInterface(): void
    {
        $pluginInfo = $this->getPluginInfoMock();

        // Mock wp_mkdir_p globally
        \Brain\Monkey\Functions\when('wp_mkdir_p')->alias(fn () => true);

        $logger = new PluginLogger($pluginInfo);

        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }

//    #[RunInSeparateProcess]
    public function testLoggingMethods(): void
    {
        $pluginInfo = $this->getPluginInfoMock();

        // Mock wp_mkdir_p globally
        \Brain\Monkey\Functions\when('wp_mkdir_p')->alias(fn () => true);

        // Inject TestHandler to capture logs
        $logger = new PluginLogger($pluginInfo);

        $reflection = new \ReflectionClass($logger);
        $backingLoggerProp = $reflection->getProperty('backingLogger');
        $backingLoggerProp->setAccessible(true);
        $backingLogger = $backingLoggerProp->getValue($logger);

        $testHandler = new TestHandler();
        $backingLogger->pushHandler($testHandler);

        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->notice('Notice message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        $logger->critical('Critical message');
        $logger->alert('Alert message');
        $logger->emergency('Emergency message');
        $logger->log(LogLevel::INFO, 'Generic log');

        $this->assertTrue($testHandler->hasDebugRecords());
        $this->assertTrue($testHandler->hasInfoRecords());
        $this->assertTrue($testHandler->hasNoticeRecords());
        $this->assertTrue($testHandler->hasWarningRecords());
        $this->assertTrue($testHandler->hasErrorRecords());
        $this->assertTrue($testHandler->hasCriticalRecords());
        $this->assertTrue($testHandler->hasAlertRecords());
        $this->assertTrue($testHandler->hasEmergencyRecords());
    }
}
