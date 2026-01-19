<?php
declare(strict_types=1);
namespace RCS\WP\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RCS\WP\PluginInfoInterface;
use RCS\WP\PluginOptionsInterface;
use RCS\WP\BgProcess\BgProcessInterface;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use RCS\Util\ReflectionHelper;

#[CoversClass(DatabaseUpdater::class)]
#[UsesClass(ReflectionHelper::class)]
final class DatabaseUpdaterTest extends TestCase
{
    private PluginInfoInterface|MockObject $pluginInfo;
    private PluginOptionsInterface|MockObject $pluginOptions;
    private BgProcessInterface|MockObject $bgProcess;
    private DatabaseUpdatesInterface|MockObject $dbUpdates;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        Monkey\setUp();

        $this->pluginInfo     = $this->createMock(PluginInfoInterface::class);
        $this->pluginOptions  = $this->createMock(PluginOptionsInterface::class);
        $this->bgProcess      = $this->createMock(BgProcessInterface::class);
        $this->dbUpdates      = $this->createMock(DatabaseUpdatesInterface::class);
        $this->logger         = $this->createMock(LoggerInterface::class);

        Functions\expect('get_temp_dir')
            ->andReturn(sys_get_temp_dir() . DIRECTORY_SEPARATOR);

        Functions\expect('add_action')
            ->once()
            ->with('init', \Mockery::type('array'), 0);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
    }

    #[Test]
    public function it_sets_database_version_if_empty(): void
    {
        $this->pluginInfo
            ->method('getSlug')
            ->willReturn('my-plugin');
        $this->pluginInfo
            ->method('getVersion')
            ->willReturn('2.0.0');

        $this->pluginOptions
            ->method('getDatabaseVersion')
            ->willReturn('');

        $this->pluginOptions
            ->expects(self::once())
            ->method('setDatabaseVersion')
            ->with('2.0.0');

        $updater = new DatabaseUpdater(
            $this->pluginInfo,
            $this->pluginOptions,
            $this->bgProcess,
            $this->dbUpdates,
            $this->logger
            );

        $updater->privUpgradeDatabase();
    }

    #[Test]
    public function it_runs_upgrade_when_newer_plugin_version(): void
    {
        $this->pluginInfo
            ->method('getSlug')
            ->willReturn('my-plugin');
        $this->pluginInfo
            ->method('getVersion')
            ->willReturn('2.0.0');

        $this->pluginOptions
        ->method('getDatabaseVersion')
        ->willReturn('1.0.0');

        $this->dbUpdates
            ->method('getDatabaseUpgrades')
            ->willReturn([
                '1.1.0' => fn() => null,
                '2.0.0' => fn() => null,
            ]);

        // Expect logger calls for semaphore + upgrade steps
        $this->logger->expects(self::atLeastOnce())->method('info');
        $this->pluginOptions->expects(self::exactly(3))->method('setDatabaseVersion');
        $this->bgProcess->expects(self::once())->method('dispatch');

        // Create temp semaphore file expectation
        Functions\when('file_exists')->alias('file_exists');
        Functions\when('filemtime')->alias('filemtime');
        Functions\when('touch')->alias('touch');
        Functions\when('unlink')->alias('unlink');

        $updater = new DatabaseUpdater(
            $this->pluginInfo,
            $this->pluginOptions,
            $this->bgProcess,
            $this->dbUpdates,
            $this->logger
            );

        $updater->privUpgradeDatabase();
    }
}
