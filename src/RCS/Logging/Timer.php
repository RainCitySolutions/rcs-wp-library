<?php
declare(strict_types=1);
namespace RCS\Logging;

/**
 * Simple PHP script timing class.
 *
 * Derived from work by Jonathan Jones
 *
 * @author Blair Cooper
 */
class Timer implements TimerInterface
{
    public const NO_TIME_MESSAGE = 'No time to return.';

    private float $start = 0.0;
    private float $pause = 0.0;
    private float $stop = 0.0;
    private float $elapsed = 0.0;
    private float $lapTotalTime = 0.0;
    /** @var array<string> */
    private array $laps = array();
    private int $count = 1;

    /**
     * Instantiation method.
     *
     * If true is passed then the timer starts immediately. If false is
     * passed or the argument is omitted the timer is not started and start()
     * must be called to start the timer.
     *
     * @param bool $startNow If set to true the timer immediately starts.
     *      Defaults to false.
     */
    public function __construct(bool $startNow = false)
    {
        if ($startNow) {
            $this->start();
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\Logging\TimerInterface::start()
     */
    public function start(): void
    {
        $this->start = $this->getMicroTime();
        $this->stop = 0.0;  // reset the stop time
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\Logging\TimerInterface::stop()
     */
    public function stop(): void
    {
        // Don't set stop if we haven't started
        if (0.0 !== $this->start) {
            $this->stop = $this->getMicroTime();
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\Logging\TimerInterface::pause()
     */
    public function pause(): void
    {
        // Don't set pause if we haven't started or we have already stopped or we are currently paused
        if (0.0 !== $this->start && 0.0 === $this->stop && 0.0 === $this->pause) {
            $this->pause = $this->getMicroTime();
            $this->elapsed += ($this->pause - $this->start);
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\Logging\TimerInterface::resume()
     */
    public function resume(): void
    {
        if (0.0 !== $this->pause) {
            $this->start = $this->getMicroTime();
            $this->pause = 0.0;
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\Logging\TimerInterface::lap()
     */
    public function lap($key = ''): void
    {
        $key = ($key === '') ? 'Lap' : $key;
        if (isset($this->start)) {
            $this->stop();
            $this->lapTotalTime += ($this->stop - $this->start);
            $this->laps[$key . ' ' . $this->count] = $this->getLapTime();
            $this->start();
            $this->count++;
        }
    }

    /**
     * Gets the time(s) in a human readable format.
     *
     * If lap() was called, returns an array of times, with an entry for each
     * lap and an entry for the total time.
     *
     * Otherwise returns the total elapsed time.
     *
     * @return string|array<string> The elapsed time or an array of times.
     */
    public function getTime(): string|array
    {
        $this->stop();

        if (!empty($this->laps)) {
            $this->laps['Total'] = $this->timeToString($this->lapTotalTime);
            return $this->laps;
        }

        return $this->timeToString();
    }

    /**
     * Get the time.
     * @return string lap time to lap() function
     */
    private function getLapTime(): string
    {
        return $this->timeToString();
    }

    /**
     * Get the microtime.
     * @return float microtime
     */
    private function getMicroTime(): float
    {
        return microtime(true);
    }

    /**
     * Convert the time to a readable string for display or logging.
     *
     * @param float $timeSeconds Seconds gathered from the `getTime` function
     *
     * @return string time in a displayable string
     */
    private function timeToString(?float $timeSeconds = null): string
    {
        if (is_null($timeSeconds)) {
            $timeSeconds = ($this->stop - $this->start) + $this->elapsed;
        }
        $timeSeconds = $this->roundMicroTime($timeSeconds);

        // Hours?? Just because we can.
        $hours   = floor(fdiv($timeSeconds, 60 * 60));
        $minutes = floor(fmod(fdiv($timeSeconds, 60), 60));
        $seconds = fmod($timeSeconds, 60);
        $seconds = round($seconds, 3, PHP_ROUND_HALF_UP);

        $hours = ($hours == 0) ? '' : $hours . ' hours ';
        $minutes = ($minutes == 0) ? '' : $minutes . ' minutes ';
        $seconds = ($seconds == 0) ? '' : $seconds . ' seconds';

        return ($hours == '' && $minutes == '' && $seconds == '') ?
            self::NO_TIME_MESSAGE :
            $hours . $minutes . $seconds;
    }

    /**
     * Round up the microtime .5 and down .4
     * @param float $microTime Time from `timeToString` function
     * @return float time rounded
     */
    private function roundMicroTime($microTime)
    {
        return round($microTime, 4, PHP_ROUND_HALF_UP);
    }
}
