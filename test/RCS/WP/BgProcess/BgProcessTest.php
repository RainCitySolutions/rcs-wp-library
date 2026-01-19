<?php
declare(strict_types=1);
namespace RCS\WP\BgProcess;

use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;

#[CoversClass(BgProcess::class)]
final class BgProcessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testConstructorSetsLoggerAndParams(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $params = ['foo' => 'bar'];

        $sut = new TestBgProcess($logger, $params);

        $this->assertSame($logger, $this->getProp($sut, 'logger'));
        $this->assertSame($params, $this->getProp($sut, 'taskParams'));
    }

    public function testPushToQueueDelegatesToParent(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        /** @var BgTaskInterface|Mockery\MockInterface $task */
        $task = Mockery::mock(BgTaskInterface::class);

        $sut = new TestBgProcess($logger);

        $result = $sut->pushToQueue($task);

        $this->assertTrue($sut->pushToQueueCalled);
        $this->assertSame($task, $sut->passedTask);
        $this->assertSame($sut, $result);
    }

    public function testPushToQueueCallsParent(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        /** @var BgTaskInterface|Mockery\MockInterface $task */
        $task = Mockery::mock(BgTaskInterface::class);

        $sut = new TestBgProcess($logger);

        $result = $sut->push_to_queue($task);

        $this->assertTrue($sut->pushToQueueCalled);
        $this->assertSame($task, $sut->passedTask);
        $this->assertSame($sut, $result);
    }

    public function testPushToQueueCallsDoingItWrongIfNotBgTaskInterface(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $sut = new TestBgProcess($logger);

        \Brain\Monkey\Functions\expect('_doing_it_wrong')
        ->once()
        ->with(
            'push_to_queue',
            Mockery::type('string'),
            '1.0'
            );

        \Brain\Monkey\Functions\expect('__')
        ->once()
        ->andReturn('Translated');

        $result = $sut->push_to_queue('invalid');

        $this->assertSame($sut, $result);
    }

    public function testRunTaskReturnsFalseWhenTaskRunTrue(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        /** @var BgTaskInterface|Mockery\MockInterface $task */
        $task = Mockery::mock(BgTaskInterface::class);

        $task->shouldReceive('run')->once()->andReturn(true);

        $sut = new TestBgProcess($logger);

        $this->assertFalse($sut->callRunTask($task, $logger));
    }

    public function testRunTaskReturnsTaskWhenRunFalse(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        /** @var BgTaskInterface|Mockery\MockInterface $task */
        $task = Mockery::mock(BgTaskInterface::class);

        $task->shouldReceive('run')->once()->andReturn(false);

        $sut = new TestBgProcess($logger);

        $this->assertSame($task, $sut->callRunTask($task, $logger));
    }

    public function testTaskReturnsFalseIfNotInstanceOfBgTaskInterface(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        $sut = new TestBgProcess($logger);

        $this->assertFalse($sut->callTask(new \stdClass()));
    }

    public function testTaskCallsRunTaskIfValid(): void
    {
        /** @var LoggerInterface|Mockery\MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        /** @var BgTaskInterface|Mockery\MockInterface $task */
        $task = Mockery::mock(BgTaskInterface::class);

        $task->shouldReceive('run')->once()->andReturn(false);

        $sut = new TestBgProcess($logger);

        $result = $sut->callTask($task);
        $this->assertSame($task, $result);
    }

    public function testUnlockProcessCallsSaveThenParentUnlock(): void
    {

        \Brain\Monkey\Functions\expect('delete_site_transient')
            ->once()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('wp_doing_ajax')
            ->once()
            ->andReturn(false);

        \Brain\Monkey\Functions\expect('wp_generate_uuid4')
            ->once()
            ->andReturn('12345678-90AB-CDEF-1234-567890ABCDEF');

            $logger = Mockery::mock(LoggerInterface::class);

        $sut = new TestBgProcess($logger);

        $result = $sut->callUnlock();

        $this->assertTrue($sut->saveCalled);
        $this->assertTrue($sut->parentUnlockCalled);
        $this->assertSame($sut, $result);
    }

    /**
     * Helper to read protected/private property.
     */
    private function getProp(object $object, string $name): mixed
    {
        $ref = new \ReflectionProperty($object, $name);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }
}

class TestBgProcess extends BgProcess
{
    public bool $pushToQueueCalled = false;
    public bool $saveCalled = false;
    public bool $parentUnlockCalled = false;
    public object $passedTask;

    public function __construct($logger, $params = []) { parent::__construct($logger, $params); }
    public function pushToQueue($task): self
    {
        $this->pushToQueueCalled = true;
        $this->passedTask = $task;

        return parent::pushToQueue($task);  // @phpstan-ignore return.type
    }
    public function save() { $this->saveCalled = true; return $this; }
    protected function unlock_process() { parent::unlock_process(); $this->parentUnlockCalled = true; return $this; }
    public function callUnlock(): self { return $this->unlock_process(); }
    public function callRunTask(BgTaskInterface $task, LoggerInterface $logger): BgTaskInterface|bool { return $this->runTask($task, $logger); }
    public function callTask(object $item): BgTaskInterface|bool { return $this->task($item); }
};
