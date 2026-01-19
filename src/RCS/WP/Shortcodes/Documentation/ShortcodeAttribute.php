<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes\Documentation;

/**
 * Represents a single shortcode attribute.
 */
final class ShortcodeAttribute
{
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $required,
        public readonly ?string $default
        )
    {
    }

    /**
     * Factory for a required attribute.
     */
    public static function required(string $name, string $description): self
    {
        return new self($name, $description, true, null);
    }

    /**
     * Factory for an optional attribute with a default value.
     */
    public static function optional(string $name, string $description, string $default): self
    {
        return new self($name, $description, false, $default);
    }

    /**
     * Get the default value.
     *
     * @return string|null Returns null for required attributes
     */
    public function getDefault(): ?string
    {
        return $this->default;
    }
}
