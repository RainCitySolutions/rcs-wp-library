<?php

declare(strict_types=1);

namespace RCS\WP\Cookies;

use Override;
use Psr\Log\LoggerInterface;
use RCS\WP\CronJob;

/**
 * A cron job to clean up expired cookies.
 *
 */
class CookieCronJob extends CronJob
{
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        parent::initializeCronJob('cprn_cookie_cron', 'daily');
    }

    #[Override]
    protected function runJob(): void
    {
        global $wpdb;

        $prefix = $wpdb->esc_like(WordPressCookie::COOKIE_PREFIX) . '%';
        $optionNames = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix
            ),
            ARRAY_A
        );

        foreach ($optionNames as $optionName) {
            /** @var CookieObject $cookie */
            $cookie = \get_option($optionName);

            if ($cookie->hasExpired()) {
                \delete_option($optionName);
            }
        }
    }
}
