<?php
declare(strict_types=1);
namespace RCS\WP;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use RCS\Logging\BaseLogger;

class PluginLogger extends BaseLogger
{
    private string $logFile;

    /**
     *
     * @param PluginInfoInterface $pluginInfo
     */
    public function __construct(
        PluginInfoInterface $pluginInfo
    ) {
        $logDir = $pluginInfo->getWriteDir() . 'logs';
        wp_mkdir_p($logDir);

        $this->logFile = $logDir . DIRECTORY_SEPARATOR . $pluginInfo->getSlug() . '.log';

        parent::__construct($pluginInfo->getSlug());
    }

    // As of Nov. 2026 - this is the default
    // protected function getDateFormat(): string
    // {
    //     return 'M d H:i:s';
    // }

    // As of Nov. 2026 - this is the default
    // protected function getMsgFormat(): string
    // {
    //     return join(
    //         ' ',
    //         [
    //             '%datetime%',
    //             '%level_name%',
    //             '%extra.class%',
    //             '[%extra.reqId%]',
    //             '(%extra.userId%/%extra.userName%)',
    //             ':',
    //             '%message% %context%'
    //         ]
    //     ).PHP_EOL;
    // }

    protected function addHandlers(array &$handlers): void
    {
        $handlers[] = new RotatingFileHandler($this->logFile, 14, Level::Debug);
    }

    protected function addProcessors(array &$processors): void
    {
        // Custom process to trim and pad classname added by IntrospectionProcessor

        // Classname processor
        $processors[] = function (LogRecord $record): LogRecord {
            if (isset($record->extra['class'])) {
                $record->extra['class'] = str_pad(
                    substr(
                        strrchr('\\' . $record->extra['class'], '\\'),
                        1
                    ),
                    30
                );
            }

            return $record;
        };

        // Introspection processor
        $processors[] = new IntrospectionProcessor(
            skipClassesPartials: [
                    //                BaseLogger::class,
                PluginLogger::class
            ]
        );

        $processors[] = new PsrLogMessageProcessor(null, true);

        // Request Id processor
        $processors[] = function (LogRecord $record): LogRecord {
            if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                $reqId = $_SERVER['REQUEST_TIME_FLOAT'];
            } else {
                $reqId = $_SERVER['REQUEST_TIME'];
            }

            $record->extra['reqId'] = str_pad(strval($reqId), 15, '0', STR_PAD_RIGHT);

            return $record;
        };

        // User context processor
        $processors[] = function (LogRecord $record): LogRecord {
            if (function_exists('wp_get_current_user')) {
                $wpUser = wp_get_current_user();

                if ($wpUser->exists()) {
                    $record->extra['userId'] = $wpUser->ID;
                    $record->extra['userName'] = $wpUser->user_login;
                }
            }

            return $record;
        };
    }
}
