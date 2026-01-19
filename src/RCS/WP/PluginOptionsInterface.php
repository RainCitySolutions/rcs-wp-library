<?php
declare(strict_types = 1);
namespace RCS\WP;

interface PluginOptionsInterface
{
    public function getOptionName(): string;

    public function isValidKey(string $key): bool;

    public function setValue(string $key, string $value): void;
    public function getValue(string $key): ?string;

    /**
     *
     * @return array<string, string>
     */
    public function getValues(): array;


    public function getDatabaseVersion(): string;
    public function setDatabaseVersion(string $dbVersion): void;
}
