<?php
declare(strict_types=1);
namespace RCS\Logging;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Simple PHP script timing class.
 *
 * Derived from work by Jonathan Jones
 *
 * @author Blair Cooper
 */
final class Timer implements TimerInterface
{
    public const NO_TIME_MESSAGE = '0 seconds';

    private const DEFAULT_LAP_KEY = 'Lap';

    private ClockInterface $clock;

    private bool $started = false;
    private bool $stopped = false;

    /** Clock reading when the current running segment began; null when not running. */
    private ?float $runningSince = null;

    /** Time accumulated in the current lap (or the whole run if no laps). */
    private float $elapsed = 0.0;

    /** Sum of all completed laps. */
    private float $lapTotalTime = 0.0;

    /** @var array<string, string> */
    private array $laps = [];
    private int $count = 1;
    private string $lastLapKey = self::DEFAULT_LAP_KEY;

    /**
     * @param bool $startNow If true the timer starts immediately. Defaults to false.
     * @param ClockInterface|null $clock Time source. Defaults to the system clock.
     */
    public function __construct(bool $startNow = false, ?ClockInterface $clock = null)
    {
        $this->clock = $clock ?? new NativeClock();

        if ($startNow) {
            $this->start();
        }
    }

    /**
     * {@inheritDoc}
     *
     * Starting always begins a fresh run: prior time, pauses and laps are cleared.
     */
    public function start(): void
    {
        $this->started = true;
        $this->stopped = false;
        $this->elapsed = 0.0;
        $this->lapTotalTime = 0.0;
        $this->laps = [];
        $this->count = 1;
        $this->lastLapKey = self::DEFAULT_LAP_KEY;
        $this->runningSince = $this->now();
    }

    /**
     * {@inheritDoc}
     */
    public function stop(): void
    {
        if (!$this->started || $this->stopped) {
            return;
        }

        $this->elapsed = $this->currentElapsed();
        $this->runningSince = null;
        $this->stopped = true;
    }

    /**
     * {@inheritDoc}
     */
    public function pause(): void
    {
        // Only a running timer can be paused (not unstarted, stopped or already paused).
        if (null === $this->runningSince) {
            return;
        }

        $this->elapsed = $this->currentElapsed();
        $this->runningSince = null;
    }

    /**
     * {@inheritDoc}
     */
    public function resume(): void
    {
        if ($this->started && !$this->stopped && null === $this->runningSince) {
            $this->runningSince = $this->now();
        }
    }

    /**
     * {@inheritDoc}
     *
     * Ignored if the timer has not been started or has been stopped. If the
     * timer is paused, the lap is recorded and the timer stays paused.
     */
    public function lap(string $key = ''): void
    {
        if (!$this->started || $this->stopped) {
            return;
        }

        $this->lastLapKey = ('' === $key) ? self::DEFAULT_LAP_KEY : $key;

        $lapTime = $this->currentElapsed();
        $this->lapTotalTime += $lapTime;
        $this->laps[$this->lastLapKey . ' ' . $this->count++] = $this->timeToString($lapTime);

        // Begin the next lap, preserving the running/paused state.
        $this->elapsed = 0.0;
        if (null !== $this->runningSince) {
            $this->runningSince = $this->now();
        }
    }

    /**
     * Gets the time(s) in a human readable format.
     *
     * Calling this stops the timer. Repeated calls return the same result.
     *
     * If lap() was called, returns an array with an entry for each lap, an
     * entry for the time after the last lap() call, and a "Total" entry.
     * Otherwise returns the total elapsed time as a string.
     *
     * @return string|array<string, string>
     */
    public function getTime(): string|array
    {
        $this->stop();

        if ([] === $this->laps) {
            return $this->timeToString($this->elapsed);
        }

        // Build the result locally so repeated calls don't mutate state.
        $result = $this->laps;
        $result[$this->lastLapKey . ' ' . $this->count] = $this->timeToString($this->elapsed);
        $result['Total'] = $this->timeToString($this->lapTotalTime + $this->elapsed);

        return $result;
    }

    private function currentElapsed(): float
    {
        if (null === $this->runningSince) {
            return $this->elapsed;
        }

        return $this->elapsed + ($this->now() - $this->runningSince);
    }

    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    /**
     * Convert seconds to a readable string for display or logging.
     */
    private function timeToString(float $timeSeconds): string
    {
        // Round once, up front, so 59.9996 becomes "1 minute" rather than "60 seconds".
        $timeSeconds = round(max(0.0, $timeSeconds), 3, PHP_ROUND_HALF_UP);

        $hours   = (int) floor($timeSeconds / 3600);
        $minutes = (int) floor(fmod($timeSeconds, 3600) / 60);
        $seconds = round(fmod($timeSeconds, 60), 3, PHP_ROUND_HALF_UP);

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . (1 === $hours ? ' hour' : ' hours');
        }
        if ($minutes > 0) {
            $parts[] = $minutes . (1 === $minutes ? ' minute' : ' minutes');
        }
        if ($seconds > 0.0) {
            $parts[] = $seconds . (1.0 === $seconds ? ' second' : ' seconds');
        }

        return [] === $parts ? self::NO_TIME_MESSAGE : implode(' ', $parts);
    }
}
