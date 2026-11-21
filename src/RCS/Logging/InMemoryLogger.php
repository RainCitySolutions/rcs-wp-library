<?php
declare(strict_types=1);
namespace RCS\Logging;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LogLevel;

class InMemoryLogger extends BaseLogger
{
    private const LOGGER_NAME = 'InMemoryLogger';

    private TestHandler $handler;

    public function __construct()
    {
        parent::__construct(self::LOGGER_NAME);
    }

    protected function getMsgFormat(): string
    {
        return '%datetime% %level_name% : %message% %context%' . PHP_EOL;
    }

    protected function addHandlers(array &$handlers): void
    {
        $this->handler = new TestHandler();
        $handlers[] = $this->handler;
    }

    protected function addProcessors(array &$processors): void
    {
        $processors[] = new PsrLogMessageProcessor(null, true);
    }

    /**
     * Fetch the formatted log messages.
     *
     * @return array<string>
     */
    public function getLogMsgs(): array
    {
        $result = array();

        foreach ($this->handler->getRecords() as $rcd) {
            $result[] = $rcd->formatted;
        }

        return $result;
    }

    /**
     * @param int|string|Level|LogLevel::* $level Logging level value or name
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function hasRecords(int|string|Level|LogLevel $level): bool
    {
        return $this->handler->hasRecords($level);
    }
}
