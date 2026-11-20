<?php

declare(strict_types=1);

namespace RCS\WP\Shortcodes;

use RCS\WP\Shortcodes\Documentation\ShortcodeDocumentation;
use RCS\WP\WordPressHelper;

class EmailShortCode implements ShortcodeImplInf
{
    use ShortcodeImplTrait;

    /**
     *
     * {@inheritDoc}
     */
    public static function getTagName(): string
    {
        return 'raincity_wpf_email';
    }

    /**
     *
     * {@inheritDoc}
     */
    public function renderShortcode(array $attrs = [], ?string $content = null): string
    {
        $wpUser = WordPressHelper::getWPUser();

        return $wpUser->user_email;
    }

    /**
     *
     * {@inheritDoc}
     */
    public function getDocumentation(array $documentation): array
    {
        array_push($documentation, new ShortCodeDocumentation(
            self::getTagName(),
            'Renders the email address of the currently logged in user, if there is one.'
        ));

        return $documentation;
    }
}
