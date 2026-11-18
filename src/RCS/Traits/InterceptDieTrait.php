<?php

declare(strict_types=1);

namespace RCS\Traits;

/**
 * Trait to define die method so that when unit tests are running PHP won't
 * actually die.
 *
 * Utilize by adding 'use InterceptDieTrait;' within the class definition
 * and calling $this->die() instead of die().

 * Relies on define('PHPUNIT_RUNNING').
 *
 * @phpstan-ignore trait.unused
 */
trait InterceptDieTrait
{
    public function die($msg = '')
    {
        if (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING) {
            die($msg);
        }
    }
}
