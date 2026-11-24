<?php

declare(strict_types=1);

namespace RCS\Logging;

use Psr\Log\LoggerInterface;

class MethodLogger
{
    private readonly string $method;

    /**
     * The $timer parameter is present primary for testing.
     *
     * @param LoggerInterface $logger
     * @param TimerInterface|null $timer Defaults to \RCS\Logging\Timer if not
     *      provided.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private ?TimerInterface $timer = null
    ) {
        $this->timer ??= new Timer();
        $this->method = $this->getCallingMethodName();

        $logger->debug("Entering {$this->method}");

        $this->timer->start();
    }

    public function __destruct()
    {
        $timeStr = $this->timer->getTime();

        $this->logger->debug("Exiting {$this->method} after {$timeStr}");
    }

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
