<?php
declare(strict_types=1);
namespace RCS\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Timer::class)]
final class TimerTest extends TestCase
{
    private const LAP_KEY = 'lapKey';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-01-01 12:00:00');
    }

    private function timer(bool $start = false): Timer
    {
        return new Timer($start, $this->clock);
    }

    // ---- construction / start -------------------------------------------

    public function testCtor_noParam_doesNotStart(): void
    {
        $timer = new Timer(clock: $this->clock);
        $this->clock->sleep(1);

        self::assertSame(Timer::NO_TIME_MESSAGE, $timer->getTime());
    }

    public function testCtor_doNotStart(): void
    {
        $timer = $this->timer(false);
        $this->clock->sleep(1);

        self::assertSame(Timer::NO_TIME_MESSAGE, $timer->getTime());
    }

    public function testCtor_doStart(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(2);

        self::assertSame('2 seconds', $timer->getTime());
    }

    public function testCtor_defaultsToSystemClock(): void
    {
        $timer = new Timer(true);

        self::assertIsString($timer->getTime());
    }

    public function testStart(): void
    {
        $timer = $this->timer();
        $this->clock->sleep(5);   // before start: must not count
        $timer->start();
        $this->clock->sleep(2);

        self::assertSame('2 seconds', $timer->getTime());
    }

    public function testStart_restartClearsPriorState(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(3);
        $timer->lap(self::LAP_KEY);
        $this->clock->sleep(1);
        $timer->pause();
        $timer->stop();

        $timer->start();
        $this->clock->sleep(2);

        self::assertSame('2 seconds', $timer->getTime());
    }

    // ---- stop -----------------------------------------------------------

    public function testStop_notStarted(): void
    {
        $timer = $this->timer();
        $timer->stop();

        self::assertSame(Timer::NO_TIME_MESSAGE, $timer->getTime());
    }

    public function testStop_started(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(0.55);
        $timer->stop();

        self::assertSame('0.55 seconds', $timer->getTime());
    }

    public function testStop_isIdempotent(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->stop();
        $this->clock->sleep(5);
        $timer->stop();

        self::assertSame('1 second', $timer->getTime());
    }

    public function testStop_whilePaused(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->pause();
        $this->clock->sleep(10);
        $timer->stop();

        self::assertSame('1 second', $timer->getTime());
    }

    // ---- pause / resume -------------------------------------------------

    public function testPause_notStarted(): void
    {
        $timer = $this->timer();
        $timer->pause();
        $this->clock->sleep(1);
        $timer->resume();

        self::assertSame(Timer::NO_TIME_MESSAGE, $timer->getTime());
    }

    public function testPause_alreadyStopped(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->stop();
        $timer->pause();
        $timer->resume();
        $this->clock->sleep(5);

        self::assertSame('1 second', $timer->getTime());
    }

    public function testPause_alreadyPaused(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->pause();
        $this->clock->sleep(2);
        $timer->pause();
        $this->clock->sleep(2);

        self::assertSame('1 second', $timer->getTime());
    }

    public function testGetTime_whilePausedExcludesPausedTime(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->pause();
        $this->clock->sleep(10);

        self::assertSame('1 second', $timer->getTime());
    }

    public function testResume_notStarted(): void
    {
        $timer = $this->timer();
        $timer->resume();
        $this->clock->sleep(1);

        self::assertSame(Timer::NO_TIME_MESSAGE, $timer->getTime());
    }

    public function testResume_startedNotPaused(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->resume();
        $this->clock->sleep(1);

        self::assertSame('2 seconds', $timer->getTime());
    }

    public function testResume_afterStop(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->pause();
        $timer->stop();
        $timer->resume();
        $this->clock->sleep(5);

        self::assertSame('1 second', $timer->getTime());
    }

    public function testPauseResumeStop(): void
    {
        $timer = $this->timer(true);

        $this->clock->sleep(0.5);
        $timer->pause();
        $this->clock->sleep(10);
        $timer->resume();
        $this->clock->sleep(0.75);
        $timer->stop();

        self::assertSame('1.25 seconds', $timer->getTime());
    }

    public function testMultiplePauseResumeCyclesAccumulate(): void
    {
        $timer = $this->timer(true);

        for ($i = 0; $i < 3; $i++) {
            $this->clock->sleep(1);
            $timer->pause();
            $this->clock->sleep(100);
            $timer->resume();
        }

        self::assertSame('3 seconds', $timer->getTime());
    }

    // ---- getTime --------------------------------------------------------

    public function testGetTime_explicitStop(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1.1);
        $timer->stop();

        self::assertSame('1.1 seconds', $timer->getTime());
    }

    public function testGetTime_impliedStop(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(0.55);

        self::assertSame('0.55 seconds', $timer->getTime());
    }

    public function testGetTime_isRepeatable(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $first = $timer->getTime();
        $this->clock->sleep(5);

        self::assertSame($first, $timer->getTime());
    }

    // ---- laps -----------------------------------------------------------

    public function testLap_notStartedIsIgnored(): void
    {
        $timer = $this->timer();
        $timer->lap(self::LAP_KEY);

        self::assertSame(Timer::NO_TIME_MESSAGE, $timer->getTime());
    }

    public function testLap_afterStopIsIgnored(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->stop();
        $timer->lap(self::LAP_KEY);

        self::assertSame('1 second', $timer->getTime());
    }

    public function testGetTime_laps(): void
    {
        $timer = $this->timer(true);

        $this->clock->sleep(1);
        $timer->lap(self::LAP_KEY);
        $this->clock->sleep(2);
        $timer->lap(self::LAP_KEY);
        $this->clock->sleep(3);
        $timer->lap(self::LAP_KEY);
        $this->clock->sleep(4);   // time after the last lap() is included
        $timer->stop();

        self::assertSame([
            'lapKey 1' => '1 second',
            'lapKey 2' => '2 seconds',
            'lapKey 3' => '3 seconds',
            'lapKey 4' => '4 seconds',
            'Total'    => '10 seconds',
        ], $timer->getTime());
    }

    public function testGetTime_lapsDefaultKey(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->lap();

        $result = $timer->getTime();

        self::assertSame(['Lap 1', 'Lap 2', 'Total'], array_keys($result));
    }

    public function testGetTime_lapsAreRepeatable(): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep(1);
        $timer->lap(self::LAP_KEY);
        $this->clock->sleep(1);

        self::assertSame($timer->getTime(), $timer->getTime());
    }

    public function testLap_afterPauseDoesNotLeakElapsed(): void
    {
        $timer = $this->timer(true);

        $this->clock->sleep(1);
        $timer->pause();
        $this->clock->sleep(100);
        $timer->resume();
        $this->clock->sleep(2);
        $timer->lap(self::LAP_KEY);   // lap 1 = 1 + 2
        $this->clock->sleep(4);
        $timer->lap(self::LAP_KEY);   // lap 2 = 4

        self::assertSame([
            'lapKey 1' => '3 seconds',
            'lapKey 2' => '4 seconds',
            'lapKey 3' => '0 seconds',
            'Total'    => '7 seconds',
        ], $timer->getTime());
    }

    public function testLap_whilePausedStaysPaused(): void
    {
        $timer = $this->timer(true);

        $this->clock->sleep(1);
        $timer->pause();
        $timer->lap(self::LAP_KEY);
        $this->clock->sleep(100);

        $result = $timer->getTime();

        self::assertSame('1 second', $result['lapKey 1']);
        self::assertSame('1 second', $result['Total']);
    }

    // ---- formatting (via public API) ------------------------------------

    #[DataProvider('formattingProvider')]
    public function testGetTime_formatting(float $seconds, string $expected): void
    {
        $timer = $this->timer(true);
        $this->clock->sleep($seconds);

        self::assertSame($expected, $timer->getTime());
    }

    /** @return array<string, array{float, string}> */
    public static function formattingProvider(): array
    {
        return [
            'sub-millisecond'     => [0.0004, Timer::NO_TIME_MESSAGE],
            'millis'              => [0.215, '0.215 seconds'],
            'one second'          => [1.0, '1 second'],
            'seconds'             => [5.0, '5 seconds'],
            'just under a minute' => [59.9996, '1 minute'],
            'one minute'          => [60.0, '1 minute'],
            'minutes and seconds' => [204.0, '3 minutes 24 seconds'],
            'minutes only'        => [180.0, '3 minutes'],
            'one hour'            => [3600.0, '1 hour'],
            'hours only'          => [18000.0, '5 hours'],
            'hours minutes secs'  => [19672.671, '5 hours 27 minutes 52.671 seconds'],
            'two digit seconds'   => [12.5, '12.5 seconds'],
        ];
    }
}
