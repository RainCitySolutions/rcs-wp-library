<?php

declare(strict_types=1);

namespace RCS\WP;

use WP_Error;

/**
 * Trait to define wp_die method so that when unit tests are running PHP
 * won't actually die.
 *
 * Utilize by adding 'use InterceptWpDieTrait;' with the class definition and
 * calling $this->wp_die() instead of wp_die().
 * 
 * Relies on define('PHPUNIT_RUNNING').
 *
 * @phpstan-ignore trait.unused
 */
trait InterceptWpDieTrait
{
    use \RCS\Traits\InterceptDieTrait {
        \RCS\Traits\InterceptDieTrait::die as parentDie;
    }

    public function die(string $msg = ''): void
    {
        $this->parentDie($msg);
    }

    /**
     * 
     * @param string|WP_Error $msg 
     * @param string|int $title 
     * @param string|array<string,mixed>|int $args 
     * @return void 
     */
    public function wp_die(string|\WP_Error $msg = '', string|int $title = '', string|array|int $args = array()): void
    {
        if (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING) {
            wp_die($msg, $title, $args);
        }
    }
}
