<?php
declare(strict_types = 1);
namespace RCS\WP\Database;

use RCS\WP\PluginInfoInterface;
use RCS\WP\PluginOptionsInterface;
use RCS\WP\BgProcess\BgProcessInterface;
use Psr\Log\LoggerInterface;

/**
 * This class handles any updates and/or changes to the database that might
 * be necessary when updating from one version of the plugin to another.
 */
class DatabaseUpdater
{
    private string $dbVersion;
    private static string $dbUpgradeSemaphoreFile;

    public function __construct(
        private PluginInfoInterface $pluginInfo,
        private PluginOptionsInterface $pluginOptions,
        private BgProcessInterface $bgProcess,
        private DatabaseUpdatesInterface $dbUpdates,
        private LoggerInterface $logger
        )
    {
        if (!isset(self::$dbUpgradeSemaphoreFile)) {
            self::$dbUpgradeSemaphoreFile = get_temp_dir() . $pluginInfo->getSlug() . '_dbUpdateSemaphore.txt';
        }

        add_action('init', [$this, 'privUpgradeDatabase'], 0);
    }

    /**
     * Trigger any database upgrades. Hooked via the 'init' action.
     *
     * @throws \Exception
     */
    public function privUpgradeDatabase(): void
    {
        $this->dbVersion = $this->pluginOptions->getDatabaseVersion();

        /*
         * If the version is the default, assume this is the first time this
         * code has run since installation in which case the database would
         * be new and insync with the current plugin version.
         */
        if (empty($this->dbVersion)) {
            $this->pluginOptions->setDatabaseVersion($this->pluginInfo->getVersion());
        }
        else {
            // Check if the plugin version is later than the database version
            // Note: Just because the plugin has been updated, it doesn't
            //      mean there are upgrades to be performed.
            if ($this->isUpgradeNeeded($this->pluginInfo->getVersion()) &&
                $this->getDbUpgradeSemaphore())
            {
                $this->logger->info('Running db upgrades');

                try {
                    $this->doDbUpgrades($this->dbUpdates->getDatabaseUpgrades());

                    // Save that the database is up to date with the current plugin version
                    $this->pluginOptions->setDatabaseVersion($this->pluginInfo->getVersion());

                    $this->logger->info('Finished db upgrades');
                } finally {
                    $this->releaseDbUpgradeSemaphore();
                }

                // Dispatch any tasks which may have been created during the upgrades
                $this->logger->info('Dispatching background tasks');
                $this->bgProcess->dispatch();
            }
        }
    }

    /**
     * Checks the database upgrade active flag, blocking if it is set until
     * it is releaseed.
     *
     * It's possible for additional/concurrent requests (ajax) into WordPress.
     * To ensure the upgrade is only performed once we wrap it with a
     * transient flag.
     *
     * @return bool True if the process should continue with the upgrade, or
     *      false it the process was waiting on another upgrade to complete.
     */
    private function getDbUpgradeSemaphore(): bool
    {
        $continueWithUpgrade = true;

        if (!file_exists(self::$dbUpgradeSemaphoreFile) || (time() - 60) > filemtime(self::$dbUpgradeSemaphoreFile)) {
            touch(self::$dbUpgradeSemaphoreFile);

            $this->logger->info('Obtained dbUpgrade semaphore');
        } else {
            $this->logger->info('Waiting for dbUpgrade semaphore');

            $retryCnt = 30;

            // If there is an upgrade in progress, sleep until its done
            while (file_exists(self::$dbUpgradeSemaphoreFile) && $retryCnt > 0) { // @phpstan-ignore booleanAnd.leftAlwaysTrue
                sleep(2);
                $retryCnt--;
            }

            $this->logger->info('Finished waiting for dbUpgrade semaphore');

            $continueWithUpgrade = false;
        }

        return $continueWithUpgrade;
    }

    private function releaseDbUpgradeSemaphore(): void
    {
        unlink(self::$dbUpgradeSemaphoreFile);
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
                $this->pluginOptions->setDatabaseVersion($version);
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
