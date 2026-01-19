<?php
declare(strict_types=1);
namespace RCS\Json;

/**
 * The FieldPropertyEntry class represents the mapping between a JSON field
 * and a class property.
 */
class FieldPropertyEntry
{
    /**
     * Construct an instance of FieldPropertyEntry
     *
     * @param string $field A JSON field name
     * @param string $property A class property name
     */
    public function __construct(
        private string $field,
        private string $property
        )
    {
    }

    /**
     * Fetch the field name
     *
     * @return string The name of the field
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Fetch the property name
     *
     * @return string The name of the property
     */
    public function getProperty(): string
    {
        return $this->property;
    }
}
