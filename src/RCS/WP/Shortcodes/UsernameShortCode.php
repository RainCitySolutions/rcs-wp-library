<?php

declare(strict_types=1);

namespace RCS\WP\Shortcodes;

use RCS\WP\Shortcodes\Documentation\ShortcodeDocumentation;
use RCS\WP\WordPressHelper;

class UsernameShortCode implements ShortcodeImplInf
{
    use ShortcodeImplTrait;

    /**
     *
     * {@inheritDoc}
     */
    public static function getTagName(): string
    {
        return 'raincity_wpf_username';
    }

    /**
     *
     * {@inheritDoc}
     */
    public function renderShortcode(array $attrs = [], ?string $content = null): string
    {
        $wpUser = WordPressHelper::getWPUser();

        return $wpUser->user_login;
    }

    /**
     *
     * {@inheritDoc}
     */
    public function getDocumentation(array $documentation): array
    {
        array_push($documentation, new ShortCodeDocumentation(
            $this->getTagName(),
            'Renders the username of the currently logged in user, if there is one.'
        ));

        return $documentation;
    }
}
