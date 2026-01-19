<?php
declare(strict_types = 1);
namespace RCS\WP\BgProcess;

use Psr\Log\LoggerInterface;

/**
 * Interface for tasks executed by the BgProcess class.
 */
interface BgTaskInterface
{
    /**
     * Run the task.
     *
     * @param BgProcess $bgProcess The background process instance. Useful
     *      when additional tasks need to be added to the queue.
     * @param LoggerInterface $logger
     * @param array<mixed> $params The array of parameters provided to the
     *      background process when it was created.
     *
     * @return bool Returns true if the task is complete. Otherwise returns false.
     */
//     public function run(BgProcessInterface $bgProcess, LoggerInterface $logger, ...$params) : bool;

    /**
     * Run the task.
     *
     * @param BgProcess $bgProcess The background process instance. Useful
     *      when additional tasks need to be added to the queue.
     * @param LoggerInterface $logger
     * @param array<string, mixed> $params The array of parameters provided to the
     *      background process when it was created.
     *
     * @return bool Returns true if the task is complete. Otherwise returns false.
     */
    public function run(BgProcessInterface $bgProcess, LoggerInterface $logger, array $params) : bool;
}
