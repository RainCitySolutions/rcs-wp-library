<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StyleMeta::class)]
final class StyleMetaTest extends TestCase
{
    #[Test]
    public function it_sets_required_properties(): void
    {
        $meta = new StyleMeta(
            id: 'main-style',
            url: 'https://example.com/style.css'
            );

        self::assertSame('main-style', $meta->id);
        self::assertSame('https://example.com/style.css', $meta->url);
        self::assertSame([], $meta->deps);
    }

    #[Test]
    public function it_accepts_dependencies(): void
    {
        $meta = new StyleMeta(
            id: 'theme-style',
            url: '/theme.css',
            deps: ['wp-blocks', 'wp-editor']
            );

        self::assertSame('theme-style', $meta->id);
        self::assertSame('/theme.css', $meta->url);
        self::assertSame(['wp-blocks', 'wp-editor'], $meta->deps);
    }
}
