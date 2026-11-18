<?php

declare(strict_types=1);

namespace RCS\WP;

/**
 * This class is the basis for classes storing a set of options.
 *
 */
abstract class WordPressOptions
{
    /** @var array<string, string> */
    private array $values = array();

    /**
     * 
     * @param string $optionName 
     * @param string[] $validKeys 
     */
    protected function __construct(
        private readonly string $optionName,
        private readonly array $validKeys
    ) {
        $stored = get_option($optionName, []);
        $stored = is_array($stored) ? $stored : [];

        // Build values in $validKeys order: fills missing keys, prunes stale ones,
        // and coerces anything non-scalar to ''.
        $values = [];
        foreach ($validKeys as $key) {
            $v = $stored[$key] ?? '';
            $values[$key] = is_scalar($v) ? (string) $v : '';
        }

        $this->values = $values;

        if ($values !== $stored) {
            update_option($optionName, $values, false);
        }
    }

    /**
     * Retrieve the name used for storing the options.
     *
     * @return string 
     */
    public function getOptionName(): string
    {
        return $this->optionName;
    }

    /**
     * Retrieve the set of options/values
     *
     * @return array<string,string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Stores a new value for an option.
     * 
     * @param string $key The key for the option
     * @param string $value The new value for the option
     *
     * @return bool Returns the true on success, or false if the key
     *      provided is not a valid.
     */
    public function setValue(string $key, string $value): bool
    {
        $result = false;

        if ($this->isValidKey($key)) {
            $this->values[$key] = $value;

            $result = true;
        }

        return $result;
    }

    /**
     * Retrieve the value for a key.
     * 
     * @param string $key The option key
     * 
     * @return null|string The value of the option, or null if the key is
     *      invalid.
     */
    public function getValue(string $key): ?string
    {
        return $this->isValidKey($key) ? $this->values[$key] : null;
    }

    /**
     * Save the current set of options.
     * 
     */
    public function save(): void
    {
        update_option($this->optionName, $this->values, false);
    }

    /**
     * Check if a key is valid
     * 
     * @param string $key An option key
     * 
     * @return bool Returns true if the key is valid, otherwise false.
     */
    public function isValidKey(string $key): bool
    {
        return in_array($key, $this->validKeys, true);
    }
}
