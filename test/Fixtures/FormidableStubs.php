<?php
declare(strict_types=1);

/**
 * Minimal stand-ins for the Formidable Forms classes used by
 * RCS\WP\Formidable\Formidable. Tests configure them through the public
 * static properties and can inspect how often the "database" was hit.
 */
trait FrmLookupStub
{
    /** @var array<string, int> */
    public static array $idsByKey = [];
    /** @var array<int, string> */
    public static array $keysById = [];
    public static int $idCalls = 0;
    public static int $keyCalls = 0;

    public static function get_id_by_key(string $key): int
    {
        self::$idCalls++;
        return self::$idsByKey[$key] ?? 0;
    }

    public static function get_key_by_id(int $id): ?string
    {
        self::$keyCalls++;
        return self::$keysById[$id] ?? null;
    }

    public static function reset(): void
    {
        self::$idsByKey = [];
        self::$keysById = [];
        self::$idCalls = 0;
        self::$keyCalls = 0;
    }
}

class FrmForm
{
    use FrmLookupStub;
}

class FrmViewsDisplay
{
    use FrmLookupStub;
}

class FrmField
{
    use FrmLookupStub;

    /** @var array<int, object> */
    public static array $fields = [];

    public static function getOne(int $id): ?object
    {
        return self::$fields[$id] ?? null;
    }
}
