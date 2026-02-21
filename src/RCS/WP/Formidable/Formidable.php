<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

use Psr\SimpleCache\CacheInterface;
use RCS\Cache\DataCache;
use RCS\Cache\StringIntBiDiMap;

/**
 * A convienence class for looking up cached key/id pairs and other things
 * related to Formidable Forms tables.
 */
class Formidable
{
    private const FRM_CACHE_FLAG = 'prevent_caching';

    private static CacheInterface $cache;

    /** @var StringIntBiDiMap[] */
    private static array $maps = [];

    /** @var int[] */
    private static array $dbCacheState = [];

    private static function getCache(): CacheInterface
    {
        if (!isset(self::$cache)) {
            self::$cache = DataCache::instance();
        }

        return self::$cache;
    }

    /**
     * Fetch the map object for the associated enum.
     *
     * @param FormidableClassEnum $mapKey
     *
     * @return StringIntBiDiMap
     */
    private static function getMap(FormidableClassEnum $mapKey): StringIntBiDiMap
    {
        if (!isset(self::$maps[$mapKey->value])) {
            self::$maps[$mapKey->value] = new StringIntBiDiMap(self::getCache(), $mapKey->value);
        }

        return self::$maps[$mapKey->value];
    }

    /**
     * Returns the ID for a Formidable form given its key.
     *
     * @param string $key The key of a Formidable form.
     *
     * @return int|NULL The ID of the form or null if no form was found with
     *         the specified key.
     */
    public static function getFormId(string $key): ?int
    {
        return self::getId($key, FormidableClassEnum::Form);
    }


    /**
     * Returns the Key for a Formidable form given its id.
     *
     * @param int $id The id of a Formidable form.
     *
     * @return string|NULL The Key for the form or null if no form was found
     *          with the specified id.
     */
    public static function getFormKey(int $id): ?string
    {
        return self::getKey($id, FormidableClassEnum::Form);
    }


    /**
     * Returns the ID for a Formidable field given its key.
     *
     * @param string $key The key of a Formidable field.
     *
     * @return int|NULL The ID of the field or null if no field was found
     *         with the specified key.
     */
    public static function getFieldId(string $key): ?int
    {
        return self::getId($key, FormidableClassEnum::Field);
    }

    /**
     * Returns the Key for a Formidable field given its id.
     *
     * @param int $id The id of a Formidable field.
     *
     * @return string|NULL The Key for the field or null if no field was
     *      found with the specified id.
     */
    public static function getFieldKey(int $id): ?string
    {
        return self::getKey($id, FormidableClassEnum::Field);
    }


    /**
     * Returns the ID for a Formidable view give its key.
     *
     * @param string $key The key of a Formidable view.
     *
     * @return int|NULL The ID of the view or null if no view was found with
     *         the specified key.
     */
    public static function getViewId(string $key): ?int
    {
        return self::getId($key, FormidableClassEnum::View);
    }


    /**
     * Returns the Key for a Formidable view given its id.
     *
     * @param int $id The id of a Formidable view.
     *
     * @return string|NULL The Key for the viewd or null if no view was
     *      found with the specified id.
     */
    public static function getViewKey(int $id): ?string
    {
        return self::getKey($id, FormidableClassEnum::View);
    }


    /**
     * Returns the ID for a Formidable class entry given its key.
     *
     * @param string $key The key of a Formidable entry.
     * @param FormidableClassEnum $mapKey The map key for caching key/id mappings.
     *
     * @return int|NULL The ID of the entry or null if no entry was found
     *         with the specified key.
     */
    private static function getId(string $key, FormidableClassEnum $mapKey): ?int
    {
        $id = null;

        $classname = '\\' . $mapKey->value;

        // If the class we need is available, continue
        if (class_exists($classname)) {
            $map = self::getMap($mapKey);

            $id = $map->getInt($key);

            if (is_null($id)) {
                $dbId = intval($classname::get_id_by_key($key));

                if (0 !== $dbId) {
                    $map->set($key, $dbId);
                    $id = $dbId;
                }
            }
        }

        return $id;
    }

    /**
     * Returns the KEY for a Formidable class entry given its it.
     *
     * @param int $id The id of a Formidable entry.
     * @param FormidableClassEnum $mapKey The map key for caching key/id mappings.
     *
     * @return string|NULL The Key of the entry or null if no entry was found
     *         with the specified key.
     */
    private static function getKey(int $id, FormidableClassEnum $mapKey): ?string
    {
        $key = null;

        $classname = '\\' . $mapKey->value;

        // If the class we need is available, continue
        if (class_exists($classname)) {
            $map = self::getMap($mapKey);

            $key = $map->getString($id);

            if (is_null($key)) {
                $dbKey = $classname::get_key_by_id($id);

                if (null !== $dbKey) {
                    $map->set($dbKey, $id);
                    $key = $dbKey;
                }
            }
        }

        return $key;
    }

    /**
     * Disable Formidable from caching results from database queries.
     *
     * Calls to disableDbCache() should be paired with calls to
     * restoreDbCache().
     */
    public static function disableDbCache(): void
    {
        global $frm_vars;

        if (isset($frm_vars[self::FRM_CACHE_FLAG])) {
            array_push(self::$dbCacheState, $frm_vars[self::FRM_CACHE_FLAG]);
        }
        else {
            // In the event that the flag gets removed elsewhere (e.g. by
            // Formidable) ensure that our state is starting from scratch.
            self::$dbCacheState = array(-1);
        }

        $frm_vars[self::FRM_CACHE_FLAG] = true;
    }


    /**
     * Restore Formidable caching state to the previous value.
     *
     * Calls to restoreDbCache() should be paired with calls to
     * disableDbCache().
     */
    public static function restoreDbCache(): void
    {
        global $frm_vars;

        if (!empty(self::$dbCacheState)) {
            $prevValue = array_pop(self::$dbCacheState);

            if (-1 === $prevValue) {
                unset($frm_vars[self::FRM_CACHE_FLAG]);
            }
            else {
                $frm_vars[self::FRM_CACHE_FLAG] = $prevValue;
            }
        }
    }


    /**
     * Retrieve the array of options for a field.
     *
     * @param int $fieldId A field identier
     *
     * @return array<array<string, mixed>> The array of options for a field, or an empty
     *      array if the field doesn't exist, or is not an options field.
     */
    public static function getFieldOptions(int $fieldId): array
    {
        $options = [];

        $field = \FrmField::getOne($fieldId);

        if (isset($field) && isset($field->options)) {
            $options = $field->options;
        }

        return $options;
    }


    /**
     * Retrieve the label for the specified option value on a field.
     *
     * @param int $fieldId A field identier
     * @param string|int $optionValue The value for a field option
     *
     * @return string The label associated with the field value, or an empty
     *      string if the field doesn't exist, is not an options field, or
     *      the provided value is not valid for the field.
     */
    public static function getFieldOptionLabel(int $fieldId, string|int $optionValue): string
    {
        $result = '';

        foreach (self::getFieldOptions($fieldId) as $option) {
            if ($option['value'] == $optionValue) {
                $result = $option['label'];
                break;
            }
        }

        return $result;
    }

    /**
     * Retrieve the value for the specified option label on a field.
     *
     * @param int $fieldId A field identier
     * @param string $optionLabel The label for a field option
     *
     * @return string|int The value associated with the field label, or an empty
     *      string if the field doesn't exist, is not an options field, or
     *      the provided label is not valid for the field.
     */
    public static function getFieldOptionValue(int $fieldId, string $optionLabel): string|int
    {
        $result = '';

        foreach (self::getFieldOptions($fieldId) as $option) {
            if ($option['label'] == $optionLabel) {
                $result = $option['value'];
                break;
            }
        }

        return $result;
    }
}
