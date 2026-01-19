<?php
declare(strict_types=1);
namespace RCS\Logging;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RCS\Util\ReflectionHelper;

#[CoversClass(\RCS\Logging\Timer::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class TimerTest extends TestCase
{
    private const SLEEP_TIME_MICROSECONDS = 550000;
    private const SLEEP_TIME_SECONDS = self::SLEEP_TIME_MICROSECONDS / 1000000;
    private const START_PROPERTY = 'start';
    private const STOP_PROPERTY = 'stop';
    private const PAUSE_PROPERTY = 'pause';
    private const ELAPSED_PROPERTY = 'elapsed';
    private const LAP_KEY = 'lapKey';
    private const SECONDS_TIME_PATTERN = '/^\d\.?\d{0,3} seconds$/';

    public function testCtor_noParam(): void
    {
        $timer = new Timer();

        $startVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        self::assertEquals(floatval(0), $startVal);
    }

    public function testCtor_doNotStart(): void
    {
        $timer = new Timer(false);

        $startVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        self::assertEquals(floatval(0), $startVal);
    }

    public function testCtor_doStart(): void
    {
        $timer = new Timer(true);

        $startVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        self::assertNotEquals(floatval(0), $startVal);
    }

    public function testStart(): void
    {
        $timer = new Timer(false);

        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::START_PROPERTY));

        $timer->start();

        self::assertNotEquals(floatval(0), $this->getTimerProperty($timer, self::START_PROPERTY));
        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::STOP_PROPERTY));
    }

    public function testStop_notStarted(): void
    {
        $timer = new Timer(false);

        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::START_PROPERTY));

        $timer->stop();

        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::STOP_PROPERTY));
    }

    public function testStop_started(): void
    {
        $timer = new Timer(true);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->stop();

        $startVal = $this->getTimerProperty($timer, self::START_PROPERTY);
        $stopVal = $this->getTimerProperty($timer, self::STOP_PROPERTY);

        self::assertNotEquals(floatval(0), $startVal);
        self::assertNotEquals(floatval(0), $stopVal);
        self::assertGreaterThan($startVal, $stopVal);

        $elapsed = $stopVal - $startVal;

        self::assertGreaterThan(self::SLEEP_TIME_SECONDS - 0.1, $elapsed); // within a tenth before
        self::assertLessThan(self::SLEEP_TIME_SECONDS + 0.1, $elapsed);    // with a tenth after
    }

    public function testStart_restart(): void
    {
        $timer = new Timer(false);

        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::START_PROPERTY));

        $timer->start();

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->stop();
        $timer->start();

        self::assertNotEquals(floatval(0), $this->getTimerProperty($timer, self::START_PROPERTY));
        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::STOP_PROPERTY));
    }

    public function testPause_notStarted(): void
    {
        $timer = new Timer(false);

        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::START_PROPERTY));

        $timer->pause();

        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::STOP_PROPERTY));
        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::PAUSE_PROPERTY));
        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::ELAPSED_PROPERTY));
    }

    public function testPause_started(): void
    {
        $timer = new Timer(true);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->pause();

        $startVal = $this->getTimerProperty($timer, self::START_PROPERTY);
        $pauseVal = $this->getTimerProperty($timer, self::PAUSE_PROPERTY);
        $elapsedVal = $this->getTimerProperty($timer, self::ELAPSED_PROPERTY);

        self::assertNotEquals(floatval(0), $startVal);
        self::assertNotEquals(floatval(0), $pauseVal);
        self::assertNotEquals(floatval(0), $elapsedVal);

        self::assertGreaterThan($startVal, $pauseVal);

        // Time between start and pause should be just over 1 second
        self::assertGreaterThanOrEqual(self::SLEEP_TIME_SECONDS, round($elapsedVal, 2));
        self::assertLessThan(self::SLEEP_TIME_SECONDS * 2, $elapsedVal);
    }

    public function testPause_alreadyStopped(): void
    {
        $timer = new Timer(true);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->stop();
        $timer->pause();

        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::PAUSE_PROPERTY));
        self::assertEquals(floatval(0), $this->getTimerProperty($timer, self::ELAPSED_PROPERTY));
    }

    public function testPause_alreadyPaused(): void
    {
        $timer = new Timer(true);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->pause();

        $orgPauseVal = $this->getTimerProperty($timer, self::PAUSE_PROPERTY);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->pause();

        $curPauseVal = $this->getTimerProperty($timer, self::PAUSE_PROPERTY);

        self::assertNotEquals(floatval(0), $orgPauseVal);
        self::assertNotEquals(floatval(0), $curPauseVal);

        self::assertEquals($orgPauseVal, $curPauseVal);
    }

    public function testResume_notStartedNotPaused(): void
    {
        $timer = new Timer(false);

        $orgStartVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->resume();

        $resumeStartVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        self::assertEquals(floatval(0), $orgStartVal);
        self::assertEquals(floatval(0), $resumeStartVal);
    }

    public function testResume_startedNotPaused(): void
    {
        $timer = new Timer(true);

        $orgStartVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->resume();

        $resumeStartVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        self::assertNotEquals(floatval(0), $orgStartVal);
        self::assertNotEquals(floatval(0), $resumeStartVal);
        self::assertEquals($orgStartVal, $resumeStartVal);
    }

    public function testPauseResumeStop(): void
    {
        $timer = new Timer(true);

        $orgStartVal = $this->getTimerProperty($timer, self::START_PROPERTY);

        usleep(self::SLEEP_TIME_MICROSECONDS);
        $timer->pause();

        usleep(self::SLEEP_TIME_MICROSECONDS);
        $timer->resume();

        usleep(self::SLEEP_TIME_MICROSECONDS);
        $timer->stop();

        $startVal = $this->getTimerProperty($timer, self::START_PROPERTY);
        $stopVal = $this->getTimerProperty($timer, self::STOP_PROPERTY);
        $elapsedVal = $this->getTimerProperty($timer, self::ELAPSED_PROPERTY);

        $startStopDiff = $stopVal - $startVal;

        self::assertNotEquals(floatval(0), $orgStartVal);
        self::assertNotEquals(floatval(0), $startVal);
        self::assertNotEquals(floatval(0), $stopVal);
        self::assertNotEquals(floatval(0), $elapsedVal);

        self::assertGreaterThan($startVal, $stopVal);

        // Time between initial start and pause should be just over SLEEP_TIME second
        self::assertGreaterThanOrEqual(self::SLEEP_TIME_SECONDS, round($elapsedVal, 2, PHP_ROUND_HALF_UP));
        self::assertLessThan(self::SLEEP_TIME_SECONDS * 2, $elapsedVal);

        // Time between resume and stop should be just over SLEEP_TIME second
        self::assertGreaterThanOrEqual(self::SLEEP_TIME_SECONDS, round($startStopDiff, 1));
        self::assertLessThan(self::SLEEP_TIME_SECONDS * 2, $startStopDiff);

        // Total elapsed time should be just over SLEEP_TIME * 2 seconds
        self::assertGreaterThan(self::SLEEP_TIME_SECONDS * 2, $startStopDiff + $elapsedVal);
        self::assertLessThan(self::SLEEP_TIME_SECONDS * 3, $startStopDiff + $elapsedVal);
    }

    public function testTimeToString_noTime(): void
    {
        $timer = new Timer();

        $result = ReflectionHelper::invokeObjectMethod(Timer::class, $timer, 'timeToString', 0.0);

        self::assertEquals(Timer::NO_TIME_MESSAGE, $result);
    }

    public function testTimeToString_mills(): void
    {
        $timer = new Timer();

        $testTime = 0.215;

        $result = ReflectionHelper::invokeObjectMethod(Timer::class, $timer, 'timeToString', $testTime);

        self::assertMatchesRegularExpression('/^0.215 seconds$/', $result);
    }

    public function testTimeToString_seconds(): void
    {
        $timer = new Timer();

        $testTime = 5.0;

        $result = ReflectionHelper::invokeObjectMethod(Timer::class, $timer, 'timeToString', $testTime);

        self::assertMatchesRegularExpression('/^5 seconds$/', $result);
    }

    public function testTimeToString_minutes(): void
    {
        $timer = new Timer();

        $testTime = (3.0 * 60) + 24.0;  // 3 minutes, 24 seconds

        $result = ReflectionHelper::invokeObjectMethod(Timer::class, $timer, 'timeToString', $testTime);

        self::assertMatchesRegularExpression('/^3 minutes 24 seconds$/', $result);
    }

    public function testTimeToString_hours(): void
    {
        $timer = new Timer();

        $testTime = (5.0 * 3600) + (27.0 * 60) + 52.671;  // 5 hours 27 minutes 52.671 seconds

        $result = ReflectionHelper::invokeObjectMethod(Timer::class, $timer, 'timeToString', $testTime);

        self::assertMatchesRegularExpression('/^5 hours 27 minutes 52.671 seconds$/', $result);
    }

    public function testGetTime_explicitStop(): void
    {
        $timer = new Timer(true);

        usleep(self::SLEEP_TIME_MICROSECONDS);
        usleep(self::SLEEP_TIME_MICROSECONDS);

        $timer->stop();

        $result = $timer->getTime();

        self::assertIsString($result);
        self::assertMatchesRegularExpression(self::SECONDS_TIME_PATTERN, $result);
    }

    public function testGetTime_impliedStop(): void
    {
        $timer = new Timer(true);

        usleep(self::SLEEP_TIME_MICROSECONDS);

        $result = $timer->getTime();

        self::assertIsString($result);
        self::assertMatchesRegularExpression(self::SECONDS_TIME_PATTERN, $result);
    }

    public function testGetTime_laps(): void
    {
        $timer = new Timer(true);

        usleep(self::SLEEP_TIME_MICROSECONDS);
        $timer->lap(self::LAP_KEY);
        usleep(self::SLEEP_TIME_MICROSECONDS);
        $timer->lap(self::LAP_KEY);
        usleep(self::SLEEP_TIME_MICROSECONDS);
        $timer->lap(self::LAP_KEY);
        usleep(self::SLEEP_TIME_MICROSECONDS);
        $timer->stop();

        $result = $timer->getTime();

        self::assertIsArray($result);
        self::assertCount(4, $result);

        foreach($result as $key => $lap) {
            self::assertMatchesRegularExpression('/^('.self::LAP_KEY.' \d|Total)$/', $key);
            self::assertMatchesRegularExpression(self::SECONDS_TIME_PATTERN, $lap);
        }
    }

    private function getTimerProperty(Timer $timer, string $property): float
    {
        return ReflectionHelper::getObjectProperty(Timer::class, $property, $timer);
    }
}
