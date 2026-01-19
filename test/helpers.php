<?php
declare(strict_types=1);


namespace RCS\WP\Validation;

function is_email(string $email): bool
{
    // Minimal deterministic validation for unit tests
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
