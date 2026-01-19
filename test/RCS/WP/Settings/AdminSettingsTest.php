<?php
declare(strict_types=1);
namespace RCS\WP\Settings;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Fixtures\TestAdminSettings;
use Fixtures\TestAdminSettingsTab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RCS\WP\PluginInfoInterface;
use PHPUnit\Framework\Attributes\UsesClass;
use RCS\Util\ReflectionHelper;

#[CoversClass(AdminSettings::class)]
#[UsesClass(AdminSettingsTab::class)]
#[UsesClass(ReflectionHelper::class)]
final class AdminSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function createSettings(TestAdminSettingsTab ...$tabs): TestAdminSettings
    {
        $pluginInfo = $this->createMock(PluginInfoInterface::class);
        $logger     = $this->createMock(LoggerInterface::class);

        $pluginInfo->method('getSlug')->willReturn('test-plugin');
        $pluginInfo->method('getUrl')->willReturn('https://example.com');
        $pluginInfo->method('getVersion')->willReturn('1.0.0');
        $pluginInfo->method('getFile')->willReturn('test-plugin/plugin.php');

        return new TestAdminSettings(
            $pluginInfo,
            $tabs,
            'Test Options',
            'test-options',
            'Test Menu',
            $logger
            );
    }

    #[Test]
    public function it_registers_tabs_uniquely(): void
    {
        $tab = $this->createMock(TestAdminSettingsTab::class);

        $settings = $this->createSettings($tab, $tab);

        $tabs = ReflectionHelper::getObjectProperty(TestAdminSettings::class, 'tabs', $settings);

        self::assertSame(1, count($tabs));
    }

//     #[Test]
//     public function add_settings_initializes_only_active_tab(): void
//     {
//         Functions\when('is_admin')->justReturn(true);

//         $tab1 = $this->createMock(TestAdminSettingsTab::class);
//         $tab2 = $this->createMock(TestAdminSettingsTab::class);

//         $tab1->method('getId')->willReturn('tab1');
//         $tab2->method('getId')->willReturn('tab2');

//         $_GET['tab'] = 'tab2';

//         $tab2->expects(self::once())->method('initSettings')->with('test-options');
//         $tab1->expects(self::never())->method('initSettings');

//         $settings = $this->createSettings($tab1, $tab2);
//         $settings->addSettings();
//     }

    #[Test]
    public function enqueue_scripts_called_for_active_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab = new TestAdminSettingsTab(
            'Tab',
            $this->createMock(\RCS\WP\PluginOptionsInterface::class),
            $this->createMock(LoggerInterface::class)
            );

        $_GET['tab'] = $tab->getId();

        $settings = $this->createSettings($tab);

        $settings->onAdminEnqueueScripts();

        $this->assertNotEmpty($tab->called['onEnqueueScripts']);
    }

    #[Test]
    public function local_sanitize_delegates_to_active_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab = new TestAdminSettingsTab(
            'Tab',
            $this->createMock(\RCS\WP\PluginOptionsInterface::class),
            $this->createMock(LoggerInterface::class)
            );

        $_GET['tab'] = $tab->getId();

        $settings = $this->createSettings($tab);

        $settings->localSanitize(['foo' => 'bar']);

        $this->assertNotEmpty($tab->called['sanitize']);
    }

    #[Test]
    public function plugin_action_links_adds_settings_link_for_own_plugin(): void
    {
        Functions\when('admin_url')->justReturn('https://example.com/options');
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html__')->returnArg();

        $settings = $this->createSettings();

        $links = $settings->addPluginActionLinks(
            ['Deactivate'],
            'test-plugin/plugin.php',
            null,
            'all'
            );

        self::assertStringContainsString('Settings', $links[0]);
    }

}
