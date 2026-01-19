<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes\Documentation;

/**
 * Represents the documentation for a single shortcode.
 */
final class ShortcodeDocumentation
{
    /** @var list<ShortcodeAttribute> */
    private array $attributes;

    /**
     *
     * @param string $name
     * @param string $description
     * @param string $example
     * @param null|list<ShortcodeAttribute> $attributes
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description = '',
        private readonly string $example = '',
        ?array $attributes = null
        )
    {
        $this->attributes = $attributes ?? [];
    }

    /**
     * Returns a new instance with an additional attribute.
     *
     * @param ShortcodeAttribute $attribute
     *
     * @return self
     */
    public function withAttribute(ShortcodeAttribute $attribute): self
    {
        $new = clone $this;
        $new->attributes[] = $attribute;
        return $new;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getExample(): string
    {
        return $this->example;
    }

    /**
     * Returns all attributes.
     *
     * @return list<ShortcodeAttribute>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
