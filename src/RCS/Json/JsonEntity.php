<?php
declare(strict_types=1);
namespace RCS\Json;

use JsonMapper\Middleware\Rename\Rename;

/**
 * Base class for classes representing JSON objects.
 */
abstract class JsonEntity
{
    /**
     * Mapping of JSON field names to class properties.
     *
     * Used to generate the field names to fetch and the Rename object for
     * mapping the array indexes to class properties.
     *
     * When an API returns JSON Lists instead of objects the fieldMap must
     * contain entries for all of the fields in the list in the appropriate
     * order. Additionally, the byIndex property must be set to 'true'.
     *
     * @return array<FieldPropertyEntry> An array of FieldPropertyEntry objects
     *      defining the mapping between JSON field and object property.
     */
    protected static function getFieldPropertyMap(): array
    {
        return [];
    }

    /**
     * Indicator as to whether mapping of fields to properties should be be
     * done by the index into the fieldMap or by the field name.
     *
     * Some REST APIs return lists and not objects in which case it is
     * necessary to have setup te fieldMap with all of the fields in the list
     * and then map them by index.
     *
     * @return bool True if the fields should be mapped by index, false if they
     *      should be mapped by name.
     */
    protected static function isMapByIndex(): bool
    {
        return false;
    }

    /**
     * Fetch the JSON field names defined in the fieldMap.
     *
     * @return string[]
     */
    public static function getJsonFields(): array
    {
        $fields = [];
        $mappedProperties = [];

        foreach(static::getFieldPropertyMap() as $entry) {
            $fields[] = $entry->getField();
            $mappedProperties[] = $entry->getProperty();
        }

        $refClass = new \ReflectionClass(static::class);

        foreach ($refClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!in_array($property->getName(), $mappedProperties)) {
                $fields[] = $property->getName();
            }
        }

        return $fields;
    }

    /**
     * Fetch the Rename map of array index to class properties.
     *
     * @return Rename
     */
    public static function getRenameMapping(): Rename
    {
        /** @var Rename */
        $renameObj = new Rename();
        $mappedProperties = [];

        foreach(static::getFieldPropertyMap() as $key => $entry) {
            $renameObj->addMapping(
                static::class,
                static::isMapByIndex() ? strval($key) : $entry->getField(),
                $entry->getProperty()
                );
            $mappedProperties[] = $entry->getProperty();
        }

        /*
         * If class uses Map By Index, then add any public properties that
         * would have been added by getJsonFields(). If the object doesn't
         * use Map By Index then the properties would have been added using
         * their actual names and we can let the PropertyMapper handle them.
         */
        if (static::isMapByIndex()) {
            $refClass = new \ReflectionClass(static::class);
            $ndx = count($mappedProperties);

            foreach ($refClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if (!in_array($property->getName(), $mappedProperties)) {
                    $renameObj->addMapping(
                        static::class,
                        strval($ndx),
                        $property->getName()
                        );
                    $ndx++;
                }
            }
        }

        return $renameObj;
    }
}
