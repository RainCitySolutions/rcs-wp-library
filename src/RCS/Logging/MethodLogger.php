<?php
declare(strict_types=1);
namespace RCS\Logging;

use Psr\Log\LoggerInterface;

class MethodLogger extends ScopeTimer
{
    /**
     * The $timer parameter is present primary for testing.
     *
     * @param LoggerInterface $logger
     * @param TimerInterface|null $timer Defaults to \RCS\Logging\Timer if not
     *      provided.
     */
    public function __construct(
        LoggerInterface $logger,
        ?TimerInterface $timer = null
        )
    {
        $method = $this->getCallingMethodName();

        parent::__construct($logger, "Exiting {$method} after %s", $timer);

        $logger->debug("Entering {$method}");
    }

    // public function __destruct()
    // {
    //     $this->timer->stop();

    //     $this->logger->debug("Exiting {$this->method} after {$this->timer->getTime()}");
    // }

    private function getCallingMethodName(): string
    {
        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2];

        $str = '';
        if (isset($caller['class'])) {
            $str .= $caller['class'];
        }
        if (isset($caller['type'])) {
            $str .= $caller['type'];
        }

        $str .= $caller['function'];

        return $str;
    }
}
