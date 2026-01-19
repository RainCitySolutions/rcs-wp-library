<?php
declare(strict_types = 1);
namespace RCS\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('\RCS\Exception\InvalidStateException')]
class InvalidStateExceptionTest extends TestCase
{
    private const ERROR_MSG = 'Test exception message';
    private const PREV_MSG = 'PrevException';
    private const ERROR_CODE = 500;

    public function testCtor_onlyMessage(): void
    {
        $obj = new InvalidStateException(self::ERROR_MSG);

        $this->assertEquals(InvalidStateException::MESSAGE_PREFIX . self::ERROR_MSG, $obj->getMessage());
        $this->assertEquals(0, $obj->getCode());
        $this->assertNull($obj->getPrevious());
    }

    public function testCtor_msgAndCode(): void
    {
        $obj = new InvalidStateException(self::ERROR_MSG, self::ERROR_CODE);

        $this->assertEquals(InvalidStateException::MESSAGE_PREFIX . self::ERROR_MSG, $obj->getMessage());
        $this->assertEquals(self::ERROR_CODE, $obj->getCode());
        $this->assertNull($obj->getPrevious());
    }

    public function testCtor_allParams(): void
    {
        $prevException = new \Exception(self::PREV_MSG);

        $obj = new InvalidStateException(self::ERROR_MSG, self::ERROR_CODE, $prevException);

        $this->assertEquals(InvalidStateException::MESSAGE_PREFIX . self::ERROR_MSG, $obj->getMessage());
        $this->assertEquals(self::ERROR_CODE, $obj->getCode());

        $this->assertNotNull($obj->getPrevious());
        $this->assertEquals($prevException, $obj->getPrevious());
    }
}
