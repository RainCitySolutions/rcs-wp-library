<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

/**
 * A convienence class for looking up key/id pairs and other things related
 * to Formidable Forms tables.
 * 
 * While Formidable will cache database responses for get_id_by_key and 
 * get_key_by_id, we cache them ourselves to avoid the overhead of Formidable
 * working out if the value is in the database cache.
 * <p>
 * 20261103 - Changed class to use local, in-memory caching arrays for the
 * key to id mappings. After testing the version that relies on the database
 * caching within the Formidable code, and a version of the class that
 * persists the maps across requests, it was found that this local,
 * in-memory implementation provides the best performance.
 */
class Formidable
{
    private const FRM_CACHE_FLAG = 'prevent_caching';

    /** @var array<string, int> */
    private static array $formIdCache = [];
    /** @var array<string, int> */
    private static array $fieldIdCache = [];
    /** @var array<string, int> */
    private static array $viewIdCache = [];
    /** @var int[] */
    private static array $dbCacheState = [];

    /**
     * Returns the ID for a Formidable form given its key.
     *
     * @param string $key The key of a Formidable form.
     *
     * @return int|null The ID of the form or null if no form was found with
     *         the specified key.
     */
    public static function getFormId(string $key): ?int
    {
        return self::getId('\FrmForm', $key, self::$formIdCache);
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
        return self::getKey('\FrmForm', $id, self::$formIdCache);
    }


    /**
     * Returns the ID for a Formidable field given its key.
     *
     * @param string $key The key of a Formidable field.
     *
     * @return int|null The ID of the field or null if no field was found
     *         with the specified key.
     */
    public static function getFieldId(string $key): ?int
    {
        return self::getId('\FrmField', $key, self::$fieldIdCache);
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
        return self::getKey('\FrmField', $id, self::$fieldIdCache);
    }


    /**
     * Returns the ID for a Formidable view give its key.
     *
     * @param string $key The key of a Formidable view.
     *
     * @return int|null The ID of the view or 0 if no view was found with
     *         the specified key.
     */
    public static function getViewId(string $key): ?int
    {
        return self::getId('\FrmViewsDisplay', $key, self::$viewIdCache);
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
        return self::getKey('\FrmViewsDisplay', $id, self::$viewIdCache);
    }

    /**
     * Returns the ID for a Formidable class entry given its key.
     *
     * @param string $classname The name of the class to use in looking
     *             the key.
     * @param string $key The key of a Formidable entry.
     * @param array<string, int> &$cache A reference to an array to use for cacing the id.
     *
     * @return int|NULL The ID of the entry or null if no entry was found
     *         with the specified key.
     */
    private static function getId(string $classname, string $key, array &$cache): ?int
    {
        $id = null;

        // If the class we need is available, continue
        if (class_exists($classname)) {
            if (isset($cache[$key])) {
                $id = $cache[$key];
            }
            else {
                $dbId = intval($classname::get_id_by_key($key));
                if (0 !== $dbId) {
                    $cache[$key] = $dbId;
                    $id = $dbId;
                }
            }
        }

        return $id;
    }

    /**
     * Returns the key for a Formidable class entry given its ID.
     *
     * @param string $classname The name of the class to use in looking
     *             the key.
     * @param int $id The id of a Formidable entry.
     * @param array<string, int> &$cache A reference to an array to use for cacing the id.
     *
     * @return string|NULL The keyof the entry or null if no entry was found
     *         with the specified id.
     */
    private static function getKey(string $classname, int $id, array &$cache): ?string
    {
        $key = null;

        // If the class we need is available, continue
        if (class_exists($classname)) {
            $key = array_search($id, $cache);

            if (false === $key) {
                $key = $classname::get_key_by_id($id);
                if (!is_null($key) && !empty($key)) {
                    $cache[strval($key)] = $id;
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

        if (class_exists(\FrmField::class)) {
            $field = \FrmField::getOne($fieldId);

            if (isset($field) && isset($field->options)) {
                $options = $field->options;
            }
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
