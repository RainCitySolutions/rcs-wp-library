<?php
declare(strict_types = 1);
namespace RCS\WP\Settings;

class FormFieldInfo
{
    public function __construct(
        public string $fieldId,             // The value for use as a field id
        public string $fieldName,           // '{optionName}[{key}]' for use as a field name
        public ?string $fieldValue = null   // The field value
        )
    {
    }
}
