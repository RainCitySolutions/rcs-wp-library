<?php

declare(strict_types=1);

namespace RCS\WP\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RCS\WP\BgProcess\BgProcessInterface;
use RCS\WP\PluginInfoInterface;
use RCS\WP\WordPressOptions;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

#[CoversClass(DatabaseUpdater::class)]
final class DatabaseUpdaterTest extends TestCase
{
    private PluginInfoInterface&MockObject $pluginInfo;
    private WordPressOptions&MockObject $options;
    private BgProcessInterface&MockObject $bgProcess;
    private DatabaseUpdatesInterface&MockObject $dbUpdates;
    private LockFactory&MockObject $lockFactory;
    private SharedLockInterface&MockObject $lock;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->pluginInfo  = $this->createMock(PluginInfoInterface::class);
        $this->options     = $this->createMock(WordPressOptions::class);
        $this->bgProcess   = $this->createMock(BgProcessInterface::class);
        $this->dbUpdates   = $this->createMock(DatabaseUpdatesInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lock        = $this->createMock(SharedLockInterface::class);
        $this->logger      = $this->createMock(LoggerInterface::class);

        $this->options
            ->method('isValidKey')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn(true);
    }

    private function createUpdater(): DatabaseUpdater
    {
        return new DatabaseUpdater(
            $this->pluginInfo,
            $this->options,
            $this->bgProcess,
            $this->dbUpdates,
            $this->lockFactory,
            $this->logger
        );
    }

    /**
     * Configure the lock factory to hand back the mock lock, and the lock
     * to be successfully acquired and then released once.
     */
    private function expectLock(): void
    {
        $this->expectLockCreated();

        $this->lock
            ->expects(self::once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);
        $this->lock
            ->expects(self::once())
            ->method('release');
    }

    /**
     * Configure the lock factory to hand back the mock lock, whose
     * acquire() call throws the given exception. The lock must not be
     * released as it was never obtained.
     */
    private function expectLockAcquireFailure(\Throwable $e): void
    {
        $this->expectLockCreated();

        $this->lock
            ->expects(self::once())
            ->method('acquire')
            ->with(true)
            ->willThrowException($e);
        $this->lock
            ->expects(self::never())
            ->method('release');
    }

    private function expectLockCreated(): void
    {
        $this->lockFactory
            ->expects(self::once())
            ->method('createLock')
            ->with(DatabaseUpdater::class, 60.0)
            ->willReturn($this->lock);
    }

    /**
     * @return array<string, array{0: \Throwable}>
     */
    public static function lockFailureProvider(): array
    {
        return [
            'conflicted' => [new LockConflictedException('Lock is held elsewhere')],
            'acquiring'  => [new LockAcquiringException('Lock store unavailable')],
        ];
    }

    #[Test]
    public function it_throws_if_options_missing_database_version_key(): void
    {
        $options = $this->createMock(WordPressOptions::class);
        $options
            ->method('isValidKey')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);

        new DatabaseUpdater(
            $this->pluginInfo,
            $options,
            $this->bgProcess,
            $this->dbUpdates,
            $this->lockFactory,
            $this->logger
        );
    }

    #[Test]
    public function it_sets_database_version_if_empty(): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('2.0.0');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('');

        $this->options
            ->expects(self::once())
            ->method('setValue')
            ->with(DatabaseUpdater::DATABASE_VERSION, '2.0.0');

        // No lock is needed for a fresh install
        $this->lockFactory->expects(self::never())->method('createLock');
        $this->dbUpdates->expects(self::never())->method('getDatabaseUpgrades');
        $this->bgProcess->expects(self::never())->method('dispatch');

        $this->createUpdater()->upgradeDatabase();
    }

    #[Test]
    public function it_runs_upgrade_when_newer_plugin_version(): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('2.0.0');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('1.0.0');

        $this->dbUpdates
            ->method('getDatabaseUpgrades')
            ->willReturn([
                '1.1.0' => fn() => null,
                '2.0.0' => fn() => null,
            ]);

        $this->expectLock();

        $this->logger->expects(self::atLeastOnce())->method('info');

        // Once per upgrade step, then once for the final plugin version
        $saved = [];
        $this->options
            ->expects(self::exactly(3))
            ->method('setValue')
            ->willReturnCallback(function (string $key, string $value) use (&$saved): bool {
                $this->assertSame(DatabaseUpdater::DATABASE_VERSION, $key);
                $saved[] = $value;

                return true;
            });

        $this->bgProcess->expects(self::once())->method('dispatch');

        $this->createUpdater()->upgradeDatabase();

        self::assertSame(['1.1.0', '2.0.0', '2.0.0'], $saved);
    }

    #[Test]
    public function it_runs_upgrades_in_version_order(): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('1.0.5');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('1.0.0');

        $calls = [];
        $this->dbUpdates
            ->method('getDatabaseUpgrades')
            ->willReturn([
                '1.0.5' => function () use (&$calls): void {
                    $calls[] = '1.0.5';
                },
                '1.0.1' => function () use (&$calls): void {
                    $calls[] = '1.0.1';
                },
                '1.0.3' => function () use (&$calls): void {
                    $calls[] = '1.0.3';
                },
            ]);

        $this->expectLock();

        $this->createUpdater()->upgradeDatabase();

        self::assertSame(['1.0.1', '1.0.3', '1.0.5'], $calls);
    }

    #[Test]
    public function it_skips_upgrades_already_applied(): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('2.0.0');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('1.1.0');

        $calls = [];
        $this->dbUpdates
            ->method('getDatabaseUpgrades')
            ->willReturn([
                '1.0.0' => function () use (&$calls): void {
                    $calls[] = '1.0.0';
                },
                '1.1.0' => function () use (&$calls): void {
                    $calls[] = '1.1.0';
                },
                '2.0.0' => function () use (&$calls): void {
                    $calls[] = '2.0.0';
                },
            ]);

        $this->expectLock();

        $this->createUpdater()->upgradeDatabase();

        self::assertSame(['2.0.0'], $calls);
    }

    #[Test]
    public function it_updates_version_when_plugin_newer_but_no_upgrades_defined(): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('2.0.0');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('1.0.0');

        $this->dbUpdates->method('getDatabaseUpgrades')->willReturn([]);

        $this->expectLock();

        $this->options
            ->expects(self::once())
            ->method('setValue')
            ->with(DatabaseUpdater::DATABASE_VERSION, '2.0.0');

        $this->bgProcess->expects(self::once())->method('dispatch');

        $this->createUpdater()->upgradeDatabase();
    }

    #[Test]
    public function it_does_nothing_when_database_is_current(): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('2.0.0');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('2.0.0');

        $this->expectLock();

        $this->dbUpdates->expects(self::never())->method('getDatabaseUpgrades');
        $this->options->expects(self::never())->method('setValue');
        $this->bgProcess->expects(self::never())->method('dispatch');

        $this->createUpdater()->upgradeDatabase();
    }

    #[Test]
    #[DataProvider('lockFailureProvider')]
    public function it_logs_critical_and_skips_upgrades_if_lock_not_acquired(\Throwable $exception): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('2.0.0');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('1.0.0');

        $this->expectLockAcquireFailure($exception);

        $this->logger
            ->expects(self::once())
            ->method('critical')
            ->with(
                'Unable to obtain lock in DatabaseUpdater: {msg}',
                ['msg' => $exception->getMessage()]
            );

        $this->dbUpdates->expects(self::never())->method('getDatabaseUpgrades');
        $this->options->expects(self::never())->method('setValue');
        $this->bgProcess->expects(self::never())->method('dispatch');

        $this->createUpdater()->upgradeDatabase();
    }

    #[Test]
    public function it_releases_lock_and_propagates_exception_if_upgrade_throws(): void
    {
        $this->pluginInfo->method('getVersion')->willReturn('2.0.0');

        $this->options
            ->method('getValue')
            ->with(DatabaseUpdater::DATABASE_VERSION)
            ->willReturn('1.0.0');

        $this->dbUpdates
            ->method('getDatabaseUpgrades')
            ->willReturn([
                '2.0.0' => function (): void {
                    throw new \RuntimeException('Upgrade failed');
                },
            ]);

        // expectLock() asserts release() is called exactly once, even
        // though the upgrade throws.
        $this->expectLock();

        $this->options->expects(self::never())->method('setValue');
        $this->bgProcess->expects(self::never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Upgrade failed');

        $this->createUpdater()->upgradeDatabase();
    }
}
