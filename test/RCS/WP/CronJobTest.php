<?php
declare(strict_types=1);
namespace RCS\WP;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use RCS\Util\ReflectionHelper;
use RCS\WP\BgProcess\BgProcessInterface;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockBuilder;
use RCS\WP\BgProcess\BgTaskInterface;

#[CoversClass(CronJob::class)]
#[UsesClass(ReflectionHelper::class)]
final class CronJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testInitializeCronJobSchedulesEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        Functions\expect('add_action')->once();
        Functions\expect('wp_clear_scheduled_hook')->never();
        Functions\expect('wp_get_schedules')->once()->andReturn([
            CronJob::EVERY_5_MINUTES => ['interval' => 300, 'display' => 'Every Five Minutes']
        ]);
        Functions\expect('wp_next_scheduled')->once()->with('testJob')->andReturn(false);
        Functions\expect('wp_schedule_event')->once()->with(self::isType('int'), 'every5minutes', 'testJob');

        $cronJob = new TestCronJob($logger);
        $cronJob->exposeInitialize('testJob', CronJob::EVERY_5_MINUTES);

        self::assertSame('testJob', ReflectionHelper::getObjectProperty(CronJob::class, 'cronJobName', $cronJob));
        self::assertSame(CronJob::EVERY_5_MINUTES, ReflectionHelper::getObjectProperty(CronJob::class, 'cronJobInterval', $cronJob));
    }

    public function testOneTimeCronJobClearsScheduledHook(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        Functions\expect('add_action')->once()->andReturnUsing(function ($hook, $callback) {
            $callback(); // simulate WP calling the hook
        });
            Functions\expect('wp_clear_scheduled_hook')->once()->with('oneTimeJob');
            Functions\expect('wp_get_schedules')->once()->andReturn([
                CronJob::ONE_TIME_CRONJOB => ['interval' => 60, 'display' => 'One Time']
            ]);
            Functions\expect('wp_next_scheduled')->once()->andReturn(false);
            Functions\expect('wp_schedule_event')->once();

            $cronJob = new TestCronJob($logger);
            $cronJob->exposeInitialize('oneTimeJob', CronJob::ONE_TIME_CRONJOB);

            self::assertTrue($cronJob->runJobCalled);
    }

    public function testUnsupportedIntervalLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Unsupported Cron Job interval'));

        Functions\expect('wp_get_schedules')->once()->andReturn([]);
        Functions\expect('add_action')->once();
        Functions\expect('wp_schedule_event')->never();

        $cronJob = new TestCronJob($logger);
        $cronJob->exposeInitialize('badJob', 'unsupportedInterval');
    }

    public function testIsJobActive(): void
    {
        $mock = $this->getMockBuilder(TestBackgroundProcess::class)
            ->onlyMethods(['is_active', 'is_process_running'])
            ->getMock()
            ;
        $mock->method('is_active')->willReturn(true);
        $mock->method('is_process_running')->willReturn(true);

        self::assertTrue(ReflectionHelper::invokeObjectMethod(CronJob::class, null, 'isJobActive', $mock));
    }
}

class TestBackgroundProcess
    extends \WP_Background_Process
    implements BgProcessInterface
{

    public function task($item) {}

    public function pushToQueue(BgTaskInterface $task): self {
        return $this;
    }
}

class TestCronJob
    extends CronJob
{
    public bool $runJobCalled = false;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    protected function runJob(): void
    {
        $this->runJobCalled = true;
    }

    public static function isJobActive(\RCS\WP\BgProcess\BgProcessInterface $bgProcess): bool
    {
        return parent::isJobActive($bgProcess);
    }


    public function exposeInitialize(string $jobName, string $interval, ?int $startTime = null): void
    {
        $this->initializeCronJob($jobName, $interval, $startTime);
    }
};
