<?php
declare(strict_types = 1);
namespace RCS\WP\Validation;

/**
 */
abstract class BaseValidator
{
    /**
     * Creates an instance of the class and associates the specified setting
     * and key with the property of this class.
     *
     * @param    string    $setting    The title of the setting we're validating.
     * @param    string    $key        The key for the field we're validating.
     * @param    string    $fieldName  The name of the field we're validating. Defaults to ''.
     */
    public function __construct(
        protected readonly string $setting,
        protected readonly string $key,
        protected readonly string $fieldName= ''
        )
    {
    }

    /**
     * Determines if the specified input is valid.
     *
     * @param    string    $input    The string
     * @return   bool                True if the input is valid; otherwise, false
     */
    abstract public function isValid(string $input): bool;

    /**
     * Adds an error message to the WordPress settings error collection to be displayed in the dashboard.
     *
     * @access   public
     *
     * @param    string    $message    The message to display in the dashboard
     */
    final public function addError(string $message): void
    {
        if ($this->fieldName !== '') {
            $message = "{$this->fieldName}: {$message}";
        }

        $this->addSettingsError($this->setting, $this->key, $message);
    }

    /**
     * WordPress boundary — extracted for testability.
     */
    protected function addSettingsError(
        string $setting,
        string $key,
        string $message
        ): void
    {
        \add_settings_error($setting, $key, $message, 'error');
    }
}
