<?php

declare(strict_types=1);

namespace RCS\WP\Database;

use RCS\WP\PluginInfoInterface;
use RCS\WP\BgProcess\BgProcessInterface;
use Psr\Log\LoggerInterface;
use RCS\WP\WordPressOptions;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;

/**
 * This class handles any updates and/or changes to the database that might
 * be necessary when updating from one version of the plugin to another.
 */
class DatabaseUpdater
{
    public const DATABASE_VERSION = 'dbVersion';

    private string $dbVersion;

    public function __construct(
        private PluginInfoInterface $pluginInfo,
        private WordPressOptions $options,
        private BgProcessInterface $bgProcess,
        private DatabaseUpdatesInterface $dbUpdates,
        private LockFactory $lockFactory,
        private LoggerInterface $logger
    ) {
        if (!$options->isValidKey(self::DATABASE_VERSION)) {
            throw new \InvalidArgumentException('Options class missing ' . self::DATABASE_VERSION . ' option');
        }
    }

    /**
     * Trigger any database upgrades. Hooked via the 'init' action.
     *
     * @throws \Exception
     */
    public function upgradeDatabase(): void
    {
        $this->dbVersion = $this->options->getValue(self::DATABASE_VERSION);

        /*
         * If the version is the default, assume this is the first time this
         * code has run since installation in which case the database would
         * be new and insync with the current plugin version.
         */
        if (empty($this->dbVersion)) {
            $this->options->setValue(self::DATABASE_VERSION, $this->pluginInfo->getVersion());
        } else {
            $lock = $this->lockFactory->createLock(DatabaseUpdater::class, 60.0);

            try {
                $lock->acquire(true);

                try {
                    // Check if the plugin version is later than the database version
                    // Note: Just because the plugin has been updated, it doesn't
                    //      mean there are upgrades to be performed.
                    if ($this->isUpgradeNeeded($this->pluginInfo->getVersion())) {
                        $this->logger->info('Running db upgrades');

                        $this->doDbUpgrades($this->dbUpdates->getDatabaseUpgrades());

                        // Save that the database is up to date with the current plugin version
                        $this->options->setValue(self::DATABASE_VERSION, $this->pluginInfo->getVersion());

                        $this->logger->info('Finished db upgrades');

                        // Dispatch any tasks which may have been created during the upgrades
                        $this->logger->info('Dispatching background tasks');
                        $this->bgProcess->dispatch();
                    }
                } finally {
                    $lock->release();
                }
            } catch (LockConflictedException $lce) {
                $this->logger->critical('Unable to obtain lock in DatabaseUpdater: {msg}', ['msg' => $lce->getMessage()]);
            } catch (LockAcquiringException $lae) {
                $this->logger->critical('Unable to obtain lock in DatabaseUpdater: {msg}', ['msg' => $lae->getMessage()]);
            }
        }
    }

    /**
     * Loop through the upgrades that are available, invoking any that have
     * not yet performed.
     *
     * @param array<string, callable> $upgrades
     *
     * @return bool
     */
    private function doDbUpgrades(array $upgrades): bool
    {
        $dbUpgraded = false;

        uksort($upgrades, 'version_compare');   // Make sure the upgrades are in order

        foreach ($upgrades as $version => $upgradeFunc) {
            // Check if the upgrade needs to be performed
            if ($this->isUpgradeNeeded($version)) {
                $logCtx = [
                    'ver' => $version,
                ];

                $this->logger->info('Upgrading database to version {ver}.', $logCtx);

                call_user_func($upgradeFunc);

                // As we've done an upgrade, note it
                $dbUpgraded = true;
                $this->dbVersion = $version;

                $this->logger->info('Finished upgrading database to version {ver}.', $logCtx);

                // Save that update to this version is complete
                $this->options->setValue(self::DATABASE_VERSION, $version);
            }
        }

        return $dbUpgraded;
    }

    /**
     * Check if the database needs to be upgraded to the specified version.
     *
     * @param string $version The version number to check
     *
     * @return bool Returns true if the database upgrade should be performed.
     *      Otherwise returns false.
     */
    private function isUpgradeNeeded(string $version): bool
    {
        return version_compare($this->dbVersion, $version) == -1;
    }
}
