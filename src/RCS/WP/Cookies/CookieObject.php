<?php

declare(strict_types=1);

namespace RCS\WP\Cookies;

use RCS\Traits\SerializeAsArrayTrait;

/**
 * This class represents the cookie data stored in the database.
 *
 */
class CookieObject
{
    use SerializeAsArrayTrait;

    private int $expires;

    public function __construct(
        private mixed $value,
        int $lifetime

    ) {
        $this->expires = \time() + $lifetime;
    }

    public function hasExpired(): bool
    {
        return \time() > $this->expires;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
