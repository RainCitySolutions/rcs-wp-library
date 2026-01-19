<?php
declare(strict_types = 1);
namespace RCS\Exception;

class InvalidStateException extends \Exception
{
    public const MESSAGE_PREFIX = 'Invalid State: ';

    /**
     * {@inheritDoc}
     * @see \Exception::__construct()
     */
    public function __construct(string $message = '""', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE_PREFIX . $message, $code, $previous);
    }
}
