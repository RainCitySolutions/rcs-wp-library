<?php

declare(strict_types=1);

namespace RCS\Logging;

use Psr\Log\LoggerInterface;

/**
 * Registers a shutdown function and if there is an error at shutdown, writes
 * the error to the log.
 */
class LastErrorLogger
{
    private static $shutdownFunctionRegistered = false;

    public function __construct(
        LoggerInterface $logger
    ) {
        if (!self::$shutdownFunctionRegistered) {
            self::$shutdownFunctionRegistered = true;

            register_shutdown_function(function () use ($logger) {
                self::shutdownFunction($logger);
            });
        }
    }

    private static function shutdownFunction(LoggerInterface $logger): void
    {
        /** @var array<string,mixed>|null */
        $error = error_get_last();

        if (!is_null($error)) {
            switch ($error['type']) {
                case E_ERROR:
                    $level = \Psr\Log\LogLevel::CRITICAL;
                    break;

                case E_WARNING:
                    $level = \Psr\Log\LogLevel::WARNING;
                    break;

                case E_NOTICE:
                    if (preg_match('/^Constant .* already defined$/', $error['message'])) {
                        $level = \Psr\Log\LogLevel::DEBUG;
                    } else {
                        $level = \Psr\Log\LogLevel::NOTICE;
                    }
                    break;

                case E_DEPRECATED:
                    $level = \Psr\Log\LogLevel::DEBUG;
                    break;

                default:
                    $level = \Psr\Log\LogLevel::INFO;
                    break;
            }

            // Don't log DEBUG messages
            if (\Psr\Log\LogLevel::DEBUG != $level) {
                $logger->log($level, 'PHP Error: ', $error);
            }
        }
    }
}
