<?php
declare(strict_types=1);
namespace RCS\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Psr\Log\LoggerInterface;

abstract class BaseLogger implements LoggerInterface
{
    private LoggerInterface $backingLogger;

    /**
     *
     */
    public function __construct(
        string $LoggerName
    ) {
        $logger = new Logger($LoggerName);

        $formatter = new LineFormatter(
            $this->getMsgFormat(),
            $this->getDateFormat(),
            false,
            true
        );
        $formatter->setMaxLevelNameLength(3);

        /** @var HandlerInterface[] */
        $handlers = [];
        $this->addHandlers($handlers);

        foreach ($handlers as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($formatter);
            }
            $logger->pushHandler($handler);
        }

        /** @var array<ProcessorInterface|callable> */
        $processors = [];
        $this->addProcessors($processors);

        foreach ($processors as $processor) {
            $logger->pushProcessor($processor);
        }

        $this->backingLogger = $logger;
    }

    /**
     * Fetch the date format to be used in log lines.
     * 
     * The default is 'M d H:i:s'.
     * Classes should overide this function to use a different format.
     *
     * @return string The date foramt.
     */
    protected function getDateFormat(): string
    {
        return 'M d H:i:s';
    }

    /**
     * Fetch the log message format to be used in log lines.
     * 
     * The default is
     * '%datetime% %level_name% %extra.class% [%extra.reqId%] (%extra.userId%/%extra.userName%) : %message% %context%'.
     * Classes should override this function to use a different format.
     * 
     * @return string
     */
    protected function getMsgFormat(): string
    {
        return join(
            ' ',
            [
                '%datetime%',
                '%level_name%',
                '%extra.class%',
                '[%extra.reqId%]',
                '(%extra.userId%/%extra.userName%)',
                ':',
                '%message% %context%'
            ]
        ) . PHP_EOL;
    }

    /**
     * Add to the set of handlers to be invoked by the logger.
     * 
     * @param HandlerInterface[] $handlers
     */
    protected function addHandlers(array &$handlers): void
    {
    }

    /**
     * Add to the set of processors to be invoked by the logger.
     * 
     * @param array<ProcessorInterface|callable> $processors
     */
    protected function addProcessors(array &$processors): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->alert($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::critical()
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->critical($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::debug()
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->debug($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::emergency()
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->emergency($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::error()
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->error($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::info()
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->info($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->log($level, $message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::notice()
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->notice($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::warning()
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->backingLogger->warning($message, $context);
    }
}
