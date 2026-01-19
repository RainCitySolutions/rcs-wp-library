<?php
declare(strict_types=1);
namespace RCS\WP\Shortcodes;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RCS\WP\PluginInfoInterface;
use RCS\WP\Fixtures\TestShortcode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ShortcodeBase::class)]
#[UsesClass(ScriptMeta::class)]
#[UsesClass(StyleMeta::class)]
final class ShortcodeBaseTest extends TestCase
{
    #[Before]
    protected function setUpBrainMonkey(): void
    {
        Monkey\setUp();
    }

    #[After]
    protected function tearDownBrainMonkey(): void
    {
        Monkey\tearDown();
    }

    #[Test]
    public function it_registers_shortcode_and_enqueues_assets(): void
    {
        $shortcodeHandler = null;
        // --- WordPress expectations ---

        Functions\expect('add_shortcode')
            ->once()
            ->with(
                'test_shortcode',
                \Mockery::on(function (callable $fn) use (&$shortcodeHandler): bool {
                    $shortcodeHandler = $fn;
                    return true;
                })
            );

        Functions\when('is_admin')->justReturn(false);

        Functions\expect('wp_enqueue_script')
        ->once()
        ->with(
            'test-script',
            '/script.js',
            ['jquery'],
            '1.2.3',
            ['strategy' => 'async']
            );

        Functions\expect('wp_enqueue_style')
        ->once()
        ->with(
            'test-style',
            '/style.css',
            [],
            '1.2.3'
            );

        // --- PluginInfo mock ---

        $pluginInfo = $this->createMock(PluginInfoInterface::class);
        $pluginInfo
        ->method('getVersion')
        ->willReturn('1.2.3');

        // --- Instantiate (constructor registers shortcode) ---

        new TestShortcode($pluginInfo);     // NOSONAR - not useless instantiation

        self::assertIsCallable($shortcodeHandler);

        $html= $shortcodeHandler([]);

        self::assertIsString($html);
    }

    #[Test]
    public function shortcode_callback_renders_output(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $callback = null;

        Functions\expect('add_shortcode')
        ->once()
        ->with(
            'test_shortcode',
            \Mockery::on(function (callable $fn) use (&$callback): bool {
                $callback = $fn;
                return true;
            })
            );

        Functions\when('wp_enqueue_script')->justReturn(null);
        Functions\when('wp_enqueue_style')->justReturn(null);

        $pluginInfo = $this->createMock(PluginInfoInterface::class);
        $pluginInfo
        ->method('getVersion')
        ->willReturn('1.0.0');

        new TestShortcode($pluginInfo);

        self::assertIsCallable($callback);

        $result = $callback([], '');

        self::assertSame('rendered-content', $result);
    }
}
