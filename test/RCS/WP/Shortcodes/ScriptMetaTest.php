<?php
declare(strict_types=1);
namespace RCS\WP\Shortcodes;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ScriptMeta::class)]
final class ScriptMetaTest extends TestCase
{
    #[Test]
    public function it_sets_required_properties(): void
    {
        $meta = new ScriptMeta(
            id: 'app',
            url: 'https://example.com/app.js'
            );

        self::assertSame('app', $meta->id);
        self::assertSame('https://example.com/app.js', $meta->url);
        self::assertSame([], $meta->deps);
        self::assertSame('async', $meta->strategy);
    }

    #[Test]
    public function it_accepts_dependencies_and_strategy(): void
    {
        $meta = new ScriptMeta(
            id: 'vendor',
            url: '/vendor.js',
            deps: ['jquery', 'wp-element'],
            strategy: 'defer'
            );

        self::assertSame('vendor', $meta->id);
        self::assertSame('/vendor.js', $meta->url);
        self::assertSame(['jquery', 'wp-element'], $meta->deps);
        self::assertSame('defer', $meta->strategy);
    }
}
