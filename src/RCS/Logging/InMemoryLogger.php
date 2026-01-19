<?php
declare(strict_types=1);
namespace RCS\Logging;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

class InMemoryLogger implements LoggerInterface
{
    private const LOGGER_NAME = 'InMemoryLogger';

    private Logger $logger;
    private TestHandler $handler;


    public function __construct()
    {
        $this->setupLogger();
    }

    protected function setupLogger(): void
    {
        $this->logger = new Logger(self::LOGGER_NAME);

        $dateformat = 'M d H:i:s';
        $format = '%datetime% %level_name% %channel%: %message% %context%'.PHP_EOL;

        $formatter = new LineFormatter ($format, $dateformat, false, true);
        $formatter->setMaxLevelNameLength(3);

        $this->handler = new TestHandler();
        $this->handler->setFormatter($formatter); //  attach the formatter to the handler

        $this->logger->pushHandler($this->handler); // push the handler to Monolog

        $this->logger->pushProcessor(new PsrLogMessageProcessor(null, true));
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
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::alert()
     */
    public function alert(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->alert($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::critical()
     */
    public function critical(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->critical($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::debug()
     */
    public function debug(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::emergency()
     */
    public function emergency(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->emergency($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::error()
     */
    public function error(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::info()
     */
    public function info(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function log(mixed $level, string|\Stringable $message, array $context = array()): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::notice()
     */
    public function notice(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->notice($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::warning()
     */
    public function warning(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->warning($message, $context);
    }
}
