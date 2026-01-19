<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes\Documentation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ShortcodeAttribute::class)]
final class ShortcodeAttributeTest extends TestCase
{
    #[Test]
    public function it_creates_a_required_attribute(): void
    {
        $attr = ShortcodeAttribute::required(
            name: 'id',
            description: 'Element ID'
            );

        self::assertSame('id', $attr->name);
        self::assertSame('Element ID', $attr->description);
        self::assertTrue($attr->required);
        self::assertNull($attr->getDefault());
    }

    #[Test]
    public function it_creates_an_optional_attribute(): void
    {
        $attr = ShortcodeAttribute::optional(
            name: 'class',
            description: 'CSS class',
        default: 'default-class'
            );

        self::assertSame('class', $attr->name);
        self::assertSame('CSS class', $attr->description);
        self::assertFalse($attr->required);
        self::assertSame('default-class', $attr->getDefault());
    }
}
