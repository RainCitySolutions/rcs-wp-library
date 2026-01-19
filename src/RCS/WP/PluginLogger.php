<?php
declare(strict_types = 1);
namespace RCS\WP;

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

class PluginLogger implements LoggerInterface
{
    private LoggerInterface $backingLogger;

    /**
     *
     * @param PluginInfoInterface $pluginInfo
     */
    public function __construct(
        PluginInfoInterface $pluginInfo
        )
    {
        $logDir = $pluginInfo->getWriteDir() . 'logs';
        wp_mkdir_p($logDir);

        $logFile = $logDir . DIRECTORY_SEPARATOR . $pluginInfo->getSlug() . '.log';

        $dateFormat = 'M d H:i:s';
        $msgFormat = join(' ', [
            '%datetime%',
            '%level_name%',
            '[%extra.reqId%]',
            ':',
            '%message% %context% %extra%'
        ]
            ).PHP_EOL;

            $formatter = new LineFormatter ($msgFormat, $dateFormat, false, true);
            $formatter->setMaxLevelNameLength(3);

//             $handler = new StreamHandler($this->logFile);
//             $handler->setFormatter($formatter); //  attach the formatter to the handler

            $handler = new RotatingFileHandler($logFile, 14, Level::Debug);
            $handler->setFormatter($formatter); //  attach the formatter to the handler

            $logger = new Logger($pluginInfo->getSlug());
            $logger->pushHandler($handler);
            $logger->pushProcessor(new PsrLogMessageProcessor(null, true));
            $logger->pushProcessor(function (LogRecord $record): LogRecord {
                if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                    $reqId = $_SERVER['REQUEST_TIME_FLOAT'];
                } else {
                    $reqId = $_SERVER['REQUEST_TIME'];
                }

                $record->extra['reqId'] = str_pad(strval($reqId), 15, '0', STR_PAD_RIGHT);

                return $record;
            });

            $this->backingLogger = $logger;
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::alert()
     */
    public function alert(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->alert($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::critical()
     */
    public function critical(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->critical($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::debug()
     */
    public function debug(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->debug($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::emergency()
     */
    public function emergency(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->emergency($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::error()
     */
    public function error(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->error($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::info()
     */
    public function info(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->info($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function log($level, string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->log($level, $message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::notice()
     */
    public function notice(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->notice($message, $context);
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::warning()
     */
    public function warning(string|\Stringable $message, array $context = array()): void
    {
        $this->backingLogger->warning($message, $context);
    }
}
