<?php

declare(strict_types=1);

namespace RCS\WP;

interface RedirectionHelperInf
{
    public function getLoginRedirectUrl(\WP_User $user, ?string $requestedRedirect): ?string;
    public function getLogoutRedirectUrl(\WP_User $user): ?string;
}
