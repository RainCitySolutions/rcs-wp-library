<?php
declare(strict_types=1);
namespace RCS\Logging;

use Psr\Log\LoggerInterface;

/**
 * A convienence class to time a period of execution. The timer starts when
 * an instance of the class is created and ends when the instance goes out
 * of scope.
 *
 * When using it is necessary to assign a new instance of the class to a
 * variable that will determine the scope.
 *      e.g. $myTimer = new ScopeTimer($logger, "log message %s");
 *
 * If no variable is used the instance will go out of scope immediately after
 * creation resulting in no time.
 *      e.g. new ScopeTimer($logger, "log message %s");
 *
 * The log message string can contain a "%s" which will be replaced with the
 * execution time.
 *
 */
class ScopeTimer {
    /**
     * Log a timer message when the instance goes out of scope.
     *
     * @param LoggerInterface $logger
     * @param string $msg The message to be written to the log when the
     *      variable goes out of scope. The string may contain a "%s" which
     *      will be replaeced with the execution time.
     * @param TimerInterface|null $timer Defaults to \RCS\Logging\Timer if not
     *      provided.
     */
    public function __construct(
        protected readonly LoggerInterface $logger,
        private readonly string $msg,
        protected ?TimerInterface $timer = null
        )
    {
        $this->timer ??= new Timer();

        $this->timer->start();
    }

    public function __destruct() {
        $timeStr = $this->timer->getTime();

        $this->logger->debug(sprintf($this->msg, $timeStr));
    }
}
