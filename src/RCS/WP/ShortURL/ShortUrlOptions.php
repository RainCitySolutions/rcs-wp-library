<?php

declare(strict_types=1);

namespace RCS\WP\ShortURL;

use RCS\WP\WordPressOptions;

class ShortUrlOptions extends WordPressOptions
{
    const OPTIONS_NAME = 'raincity_wpf_shorturl_options';

    const URL_PREFIX = 'urlPrefix';

    /**
     * Initialize the collections used to maintain the values.
     */
    public function __construct()
    {
        parent::__construct(
            self::OPTIONS_NAME,
            [
                self::URL_PREFIX
            ]
        );
    }

    public function getUrlPrefix(): ?string
    {
        return $this->getValue(self::URL_PREFIX);
    }
}
