<?php
declare(strict_types = 1);
namespace RCS\Logging;

interface TimerInterface
{
    /**
     * Starts the timer.
     *
     * Resets the timer on each call.
     */
    public function start(): void;

    /**
     * Stops the timer.
     *
     * If the timer has not be started, nothing happens.
     */
    public function stop(): void;

    /**
     * Pauses the timer.
     *
     * If the timer has not been started, or has already been stopped,
     * nothing happens.
     */
    public function pause(): void;

    /**
     * Resumes the timer after a pause is called.
     *
     * If pause has not been called nothing happens.
     */
    public function resume(): void;

    /**
     * Used to build an array of times for multiple timers, adding a key
     * parameter can be used to name the `lap`
     *
     * @param string $key Used as the key in the kay value pair array.
     */
    public function lap($key = ''): void;

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
    public function getTime(): string|array;
}
