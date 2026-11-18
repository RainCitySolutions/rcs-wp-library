<?php

declare(strict_types=1);

namespace RCS\WP;

final class RedirectionManager
{
    public function __construct(
        private readonly RedirectionHelperInf $helper
    ) {
        add_filter('login_redirect', [$this, 'loginRedirect'], 10, 3);
        add_filter('logout_redirect', [$this, 'logoutRedirect'], 10, 3);
    }

    /**
     * Handles login redirection
     *
     * @param string $redirectTo Default redirect
     * @param string $requestedRedirectTo Requested redirect
     * @param \WP_User|\WP_Error $user WP_User if user logged in, WP_Error otherwise
     *
     * @return string New redirect
     */
    public function loginRedirect(string $redirectTo, string $requestedRedirectTo, \WP_User|\WP_Error $user): string
    {
        $redirectUrl = $redirectTo;

        if ($user instanceof \WP_User) {
            $url = $this->helper->getLoginRedirectUrl($user, $requestedRedirectTo);
            if (isset($url)) {
                $redirectUrl = $url;
            }
        }

        return $redirectUrl;
    }

    /**
     * Handles logout redirection
     *
     * @param string $redirectTo Default redirect
     * @param string $requestedRedirectTo Requested redirect
     * @param \WP_User|\WP_Error $user WP_User if user logged in, WP_Error otherwise
     *
     * @return string New redirect
     */
    public function logoutRedirect(string $redirectTo, string $requestedRedirectTo, \WP_User|\WP_Error $user): string
    {
        $redirectUrl = $redirectTo;

        if ($user instanceof \WP_User) {
            $helperUrl = $this->helper->getLogoutRedirectUrl($user);

            if (isset($helperUrl)) {
                $redirectUrl = $helperUrl;
            }
        }

        return $redirectUrl;
    }
}
