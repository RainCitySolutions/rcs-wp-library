<?php
declare(strict_types=1);
namespace RCS\WP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PluginInfo::class)]
final class PluginInfoTest extends TestCase
{
    private string $fakePluginFile;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp(); // Initialize BrainMonkey

        $this->fakePluginFile = sys_get_temp_dir() . '/myplugin/myplugin.php';
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown(); // Cleanup BrainMonkey
        parent::tearDown();
    }

    public function testConstructorAndGetters(): void
    {
        // Mock WordPress functions
        \Brain\Monkey\Functions\expect('get_plugin_data')
            ->once()
            ->with($this->fakePluginFile, false, false)
            ->andReturn([
                'Name' => 'My Plugin',
                'Version' => '1.2.3',
                'TextDomain' => 'myplugin',
            ]);

        \Brain\Monkey\Functions\expect('plugin_dir_path')
            ->once()
            ->with($this->fakePluginFile)
            ->andReturn('/fake/path/');

        \Brain\Monkey\Functions\expect('plugin_dir_url')
            ->once()
            ->with($this->fakePluginFile)
            ->andReturn('https://example.com/wp-content/plugins/myplugin/');

        \Brain\Monkey\Functions\expect('wp_upload_dir')
            ->once()
            ->andReturn([
                'basedir' => sys_get_temp_dir(),
                'baseurl' => 'https://example.com/wp-content/uploads',
            ]);

        \Brain\Monkey\Functions\expect('plugin_basename')
            ->once()
            ->with($this->fakePluginFile)
            ->andReturn('myplugin/myplugin.php');

        $pluginInfo = new PluginInfo($this->fakePluginFile);

        $this->assertSame($this->fakePluginFile, $pluginInfo->getEntryPointFile());
        $this->assertSame('myplugin/myplugin.php', $pluginInfo->getFile());
        $this->assertSame('/fake/path/', $pluginInfo->getPath());
        $this->assertSame('https://example.com/wp-content/plugins/myplugin/', $pluginInfo->getUrl());
        $this->assertSame('1.2.3', $pluginInfo->getVersion());
        $this->assertSame('myplugin', $pluginInfo->getSlug());
        $this->assertSame('My Plugin', $pluginInfo->getName());
        $this->assertStringContainsString(sys_get_temp_dir(), $pluginInfo->getWriteDir());
    }

    public function testIsPluginActiveReturnsBool(): void
    {
        \Brain\Monkey\Functions\expect('is_plugin_active')
            ->once()
            ->with('some-plugin-file.php')
            ->andReturn(true);

        $this->assertTrue(PluginInfo::isPluginActive('some-plugin-file.php'));
    }
}
