<?php
declare(strict_types=1);
namespace RCS\WP;

use Psr\Log\LoggerInterface;
use RCS\WP\BgProcess\BgProcessInterface;

/**
 * Base class for plugin cron jobs
 */
abstract class CronJob
{
    public const ONE_TIME_CRONJOB = 'oneTimeJob';
    public const EVERY_5_MINUTES  = 'every5minutes';
    public const EVERY_10_MINUTES = 'every10minutes';
    public const EVERY_15_MINUTES = 'every15minutes';
    public const EVERY_6_HOURS    = 'every6hours';

    protected string $cronJobName;
    protected string $cronJobInterval;

    /**
     *
     * @param LoggerInterface $logger
     */
    protected function __construct(
        protected LoggerInterface $logger
        )
    {
    }

    /**
     * Initialize the cron job.
     *
     * @param string $jobName The name to use for the job.
     * @param string $interval The interval to run the job. Must be a
     *      validate schedule as returned by wp_get_schedules().
     * @param int|NULL $startTime The timestamp for the first execution. If
     *      not specified default to time().
     */
    protected function initializeCronJob(string $jobName, string $interval, ?int $startTime = null): void
    {
        $this->cronJobName = $jobName;
        $this->cronJobInterval = $interval;

        add_action(
            $jobName,
            function (): void {
                $this->runJob();

                if (self::ONE_TIME_CRONJOB == $this->cronJobInterval) {
                    wp_clear_scheduled_hook($this->cronJobName);
                }
            }
        );

        $this->scheduleCron($jobName, $interval, $startTime ?? time());
    }

    private function scheduleCron(string $jobName, string $interval, int $startTime): bool
    {
        $result = false;

        if (array_key_exists ($interval, wp_get_schedules())) {

            if ( ! wp_next_scheduled($jobName) ) {
                wp_schedule_event( $startTime, $interval, $jobName );
            }

            $result = true;
        }
        else {
            $this->logger->error('Unsupported Cron Job interval: ' . $interval);
        }

        return $result;
    }

    abstract protected function runJob(): void;


    /**
     * Wrapper method to handle the deliciousbrains vs a5hleyrich versions of
     * the WP_Background_Process class that might be loaded in memory.
     *
     * In the deliciousbrains version we can check if the background process
     * is active. In the a5shleyrich version we just assume it is not.
     *
     * @param BgProcessInterface $bgProcess An instance of
     *      \WP_Background_Process from either the deliciousbrains or
     *      a5shleyrich packages.
     *
     * @return bool True if the job is active, false otherwise.
     */
    protected static function isJobActive(BgProcessInterface $bgProcess): bool
    {
        $isJobActive = false;

        if ($bgProcess instanceof \WP_Background_Process) {
            // Does not exist in the a5shleyrich version
            if (method_exists($bgProcess, 'is_active')) { // @phpstan-ignore function.alreadyNarrowedType
                $isJobActive = $bgProcess->is_active();
            } elseif (method_exists($bgProcess, 'is_process_running')) {
                $method = new \ReflectionMethod($bgProcess, 'is_process_running');
                $isJobActive = $method->invoke($bgProcess);
            }
        }

        return $isJobActive;
    }
}

// @codeCoverageIgnoreStart
if (function_exists('add_filter')) {
    add_filter(
        'cron_schedules',
        function (array $schedules): array {
            $schedules[CronJob::ONE_TIME_CRONJOB] = array(
                'interval' => 1 * MINUTE_IN_SECONDS,    // Set to 1 minute but will be cancelled after firing once
                'display'  => esc_html__( 'One Time' )
            );
            $schedules[CronJob::EVERY_5_MINUTES] = array(
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => esc_html__( 'Every Five Minutes' )
            );
            $schedules[CronJob::EVERY_10_MINUTES] = array(
                'interval' => 10 * MINUTE_IN_SECONDS,
                'display'  => esc_html__( 'Every Ten Minutes' )
            );
            $schedules[CronJob::EVERY_15_MINUTES] = array(
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => esc_html__( 'Every Fifteen Minutes' )
            );
            $schedules[CronJob::EVERY_6_HOURS] = array(
                'interval' => 6 * HOUR_IN_SECONDS,
                'display'  => esc_html__( 'Every Six Hours' )
            );

            return $schedules;
        }
        );
}
// @codeCoverageIgnoreEnd
