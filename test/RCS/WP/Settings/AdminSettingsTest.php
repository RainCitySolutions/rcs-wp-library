<?php

declare(strict_types=1);

namespace RCS\WP\Settings;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fixtures\TestAdminSettingsTab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RCS\Util\ReflectionHelper;
use RCS\WP\PluginInfoInterface;
use RCS\WP\WordPressOptions;

class TestAdminSettings extends AdminSettings
{
    /**
     * @param iterable<AdminSettingsTab> $tabs
     */
    public function __construct(
        PluginInfoInterface $pluginInfo,
        iterable $tabs,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $pluginInfo,
            'Test Options',
            'test-options',
            'Test Menu',
            $tabs,
            $logger
        );
    }
}

#[CoversClass(AdminSettings::class)]
#[UsesClass(AdminSettingsTab::class)]
#[UsesClass(ReflectionHelper::class)]
final class AdminSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $_GET  = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET  = [];
        $_POST = [];

        // Mockery expectations (Brain Monkey's expect()/expectAdded()) are not
        // counted by PHPUnit, so add them to the assertion count.
        $container = \Mockery::getContainer();
        $this->addToAssertionCount($container->mockery_getExpectationCount());

        Monkey\tearDown();
        parent::tearDown();
    }

    private function createSettings(AdminSettingsTab ...$tabs): TestAdminSettings
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
            $logger
        );
    }

    /**
     * @return AdminSettingsTab&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createTabMock(string $id, string $name = 'Tab'): AdminSettingsTab
    {
        $tab = $this->createMock(TestAdminSettingsTab::class);
        $tab->method('getId')->willReturn($id);
        $tab->method('getName')->willReturn($name);

        return $tab;
    }

    private function createRealTab(): TestAdminSettingsTab
    {
        return new TestAdminSettingsTab(
            'Tab',
            $this->createMock(WordPressOptions::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @return array<string,AdminSettingsTab>
     */
    private function getTabs(TestAdminSettings $settings): array
    {
        return ReflectionHelper::getObjectProperty(TestAdminSettings::class, 'tabs', $settings);
    }

    // ------------------------------------------------------------------
    // Construction / hook registration
    // ------------------------------------------------------------------

    #[Test]
    public function it_registers_tabs_uniquely(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab = $this->createTabMock('tab1');

        $settings = $this->createSettings($tab, $tab);

        self::assertCount(1, $this->getTabs($settings));
    }

    #[Test]
    public function it_registers_tabs_with_distinct_ids(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $settings = $this->createSettings(
            $this->createTabMock('tab1'),
            $this->createTabMock('tab2')
        );

        self::assertSame(['tab1', 'tab2'], array_keys($this->getTabs($settings)));
    }

    #[Test]
    public function first_registered_tab_wins_on_duplicate_id(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $first  = $this->createTabMock('dup');
        $second = $this->createTabMock('dup');

        $settings = $this->createSettings($first, $second);

        self::assertSame($first, $this->getTabs($settings)['dup']);
    }

    #[Test]
    public function it_does_not_register_tabs_or_hooks_outside_admin(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->createSettings($this->createTabMock('tab1'));

        self::assertSame([], $this->getTabs($settings));
        self::assertFalse(has_action('admin_init'));
        self::assertFalse(has_action('admin_menu'));
        self::assertFalse(has_action('admin_enqueue_scripts'));
        self::assertFalse(has_filter('plugin_action_links'));
    }

    #[Test]
    public function it_registers_hooks_in_admin(): void
    {
        Functions\when('is_admin')->justReturn(true);

        Actions\expectAdded('admin_enqueue_scripts')->once();
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_menu')->once();
        Filters\expectAdded('plugin_action_links')->once()->with(\Mockery::type('array'), 10, 4);

        $this->createSettings($this->createTabMock('tab1'));
    }

    // ------------------------------------------------------------------
    // addSettingsMenu
    // ------------------------------------------------------------------

    #[Test]
    public function add_settings_menu_registers_options_page(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->createSettings();

        Functions\expect('add_options_page')
            ->once()
            ->with(
                'Test Options',
                'Test Menu',
                'manage_options',
                'test-options',
                [$settings, 'renderSettingsPage']
            );

        $settings->addSettingsMenu();
    }

    // ------------------------------------------------------------------
    // addSettings
    // ------------------------------------------------------------------

    #[Test]
    public function add_settings_registers_actions_for_all_tabs_and_inits_only_active_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $_GET['tab'] = 'tab2';

        $tab1->expects(self::once())->method('registerActions');
        $tab2->expects(self::once())->method('registerActions');

        $tab1->expects(self::never())->method('initSettings');
        $tab2->expects(self::once())->method('initSettings')->with('test-options');

        $settings = $this->createSettings($tab1, $tab2);
        $settings->addSettings();
    }

    #[Test]
    public function add_settings_uses_first_tab_when_none_requested(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $tab1->expects(self::once())->method('initSettings')->with('test-options');
        $tab2->expects(self::never())->method('initSettings');

        $settings = $this->createSettings($tab1, $tab2);
        $settings->addSettings();
    }

    // ------------------------------------------------------------------
    // Active tab resolution
    // ------------------------------------------------------------------

    #[Test]
    public function unknown_tab_in_query_falls_back_to_first_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $_GET['tab'] = 'does-not-exist';

        $tab1->expects(self::once())->method('initSettings');
        $tab2->expects(self::never())->method('initSettings');

        $this->createSettings($tab1, $tab2)->addSettings();
    }

    #[Test]
    public function non_string_tab_in_query_falls_back_to_first_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $_GET['tab'] = ['tab2'];

        $tab1->expects(self::once())->method('initSettings');
        $tab2->expects(self::never())->method('initSettings');

        $this->createSettings($tab1, $tab2)->addSettings();
    }

    #[Test]
    public function active_tab_is_resolved_from_post_referer(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $_POST['_wp_http_referer'] = '/wp-admin/options-general.php?page=test-options&tab=tab2';

        $tab1->expects(self::never())->method('initSettings');
        $tab2->expects(self::once())->method('initSettings')->with('test-options');

        $this->createSettings($tab1, $tab2)->addSettings();
    }

    #[Test]
    public function get_tab_takes_precedence_over_post_referer(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $_GET['tab']               = 'tab1';
        $_POST['_wp_http_referer'] = '/wp-admin/options-general.php?page=test-options&tab=tab2';

        $tab1->expects(self::once())->method('initSettings');
        $tab2->expects(self::never())->method('initSettings');

        $this->createSettings($tab1, $tab2)->addSettings();
    }

    #[Test]
    public function post_referer_without_query_falls_back_to_first_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $_POST['_wp_http_referer'] = '/wp-admin/options-general.php';

        $tab1->expects(self::once())->method('initSettings');
        $tab2->expects(self::never())->method('initSettings');

        $this->createSettings($tab1, $tab2)->addSettings();
    }

    #[Test]
    public function post_referer_without_tab_param_falls_back_to_first_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab1 = $this->createTabMock('tab1');
        $tab2 = $this->createTabMock('tab2');

        $_POST['_wp_http_referer'] = '/wp-admin/options-general.php?page=test-options';

        $tab1->expects(self::once())->method('initSettings');
        $tab2->expects(self::never())->method('initSettings');

        $this->createSettings($tab1, $tab2)->addSettings();
    }

    // ------------------------------------------------------------------
    // Enqueue scripts
    // ------------------------------------------------------------------

    #[Test]
    public function enqueue_scripts_called_for_active_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab = $this->createRealTab();

        $_GET['tab'] = $tab->getId();

        $settings = $this->createSettings($tab);

        $settings->onAdminEnqueueScripts();

        $this->assertNotEmpty($tab->called['onEnqueueScripts']);
    }

    #[Test]
    public function enqueue_scripts_passes_plugin_slug_url_and_version(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab = $this->createTabMock('tab1');
        $tab->expects(self::once())
            ->method('onEnqueueScripts')
            ->with('test-plugin', 'https://example.com', '1.0.0');

        $this->createSettings($tab)->onAdminEnqueueScripts();
    }

    // ------------------------------------------------------------------
    // Sanitize
    // ------------------------------------------------------------------

    #[Test]
    public function local_sanitize_delegates_to_active_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab = $this->createRealTab();

        $_GET['tab'] = $tab->getId();

        $settings = $this->createSettings($tab);

        $settings->localSanitize(['foo' => 'bar']);

        $this->assertNotEmpty($tab->called['sanitize']);
    }

    #[Test]
    public function local_sanitize_passes_plugin_slug_and_input(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $tab = $this->createTabMock('tab1');
        $tab->expects(self::once())
            ->method('sanitize')
            ->with('test-plugin', ['foo' => 'bar']);

        $this->createSettings($tab)->localSanitize(['foo' => 'bar']);
    }

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------

    private function stubRenderFunctions(bool $canManage = true): void
    {
        Functions\when('current_user_can')->justReturn($canManage);
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/options-general.php');
        Functions\when('add_query_arg')->alias(
            static fn(array $args, string $url): string => $url . '?' . http_build_query($args)
        );
    }

    /**
     * Separate from stubRenderFunctions() because Brain Monkey doesn't allow a
     * function to be both stubbed with when() and given an expect().
     */
    private function stubSettingsApiFunctions(): void
    {
        Functions\when('settings_fields')->justReturn();
        Functions\when('do_settings_sections')->justReturn();
        Functions\when('submit_button')->justReturn();
    }

    #[Test]
    public function render_settings_page_outputs_nothing_without_capability(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $this->stubRenderFunctions(false);

        $settings = $this->createSettings($this->createTabMock('tab1'));

        ob_start();
        $settings->renderSettingsPage();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    #[Test]
    public function render_settings_page_outputs_nothing_without_tabs(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $this->stubRenderFunctions();

        $settings = $this->createSettings();

        ob_start();
        $settings->renderSettingsPage();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    #[Test]
    public function render_settings_page_renders_tab_links_and_marks_active_tab(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $this->stubRenderFunctions();
        $this->stubSettingsApiFunctions();

        $tab1 = $this->createTabMock('tab1', 'First');
        $tab2 = $this->createTabMock('tab2', 'Second');
        $tab2->expects(self::once())->method('renderPostFormData');
        $tab1->expects(self::never())->method('renderPostFormData');

        $_GET['tab'] = 'tab2';

        $settings = $this->createSettings($tab1, $tab2);

        ob_start();
        $settings->renderSettingsPage();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Test Options', $output);
        self::assertStringContainsString('page=test-options&tab=tab1', $output);
        self::assertStringContainsString('page=test-options&tab=tab2', $output);
        self::assertMatchesRegularExpression('/class="nav-tab ">First</', $output);
        self::assertMatchesRegularExpression('/class="nav-tab nav-tab-active">Second</', $output);
        self::assertStringContainsString('action="options.php"', $output);
    }

    #[Test]
    public function render_settings_page_calls_settings_api_functions(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $this->stubRenderFunctions();

        Functions\expect('settings_fields')->once()->with('test-options');
        Functions\expect('do_settings_sections')->once()->with('test-options');
        Functions\expect('submit_button')->once()->with('Save Changes');

        $settings = $this->createSettings($this->createTabMock('tab1'));

        ob_start();
        $settings->renderSettingsPage();
        ob_end_clean();
    }

    // ------------------------------------------------------------------
    // Plugin action links
    // ------------------------------------------------------------------

    #[Test]
    public function plugin_action_links_adds_settings_link_for_own_plugin(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('admin_url')->alias(static fn(string $path): string => 'https://example.com/' . $path);
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html__')->returnArg();

        $settings = $this->createSettings();

        $links = $settings->addPluginActionLinks(
            ['Deactivate'],
            'test-plugin/plugin.php',
            null,
            'all'
        );

        self::assertCount(2, $links);
        self::assertStringContainsString('Settings', $links[0]);
        self::assertStringContainsString('options-general.php?page=test-options', $links[0]);
        self::assertSame('Deactivate', $links[1]);
    }

    #[Test]
    public function plugin_action_links_unchanged_for_other_plugins(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->createSettings();

        $actions = ['Deactivate'];

        $links = $settings->addPluginActionLinks(
            $actions,
            'other-plugin/plugin.php',
            [],
            'all'
        );

        self::assertSame($actions, $links);
    }
}
