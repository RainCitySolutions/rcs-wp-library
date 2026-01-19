<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes\Documentation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ShortcodeDocumentation::class)]
#[UsesClass(ShortcodeAttribute::class)]
final class ShortcodeDocumentationTest extends TestCase
{
    #[Test]
    public function it_sets_name_description_and_example(): void
    {
        $doc = new ShortcodeDocumentation(
            name: 'my_shortcode',
            description: 'Does something',
            example: '[my_shortcode foo="bar"]'
            );

        self::assertSame('my_shortcode', $doc->getName());
        self::assertSame('Does something', $doc->getDescription());
        self::assertSame('[my_shortcode foo="bar"]', $doc->getExample());
    }

    #[Test]
    public function attributes_are_empty_by_default(): void
    {
        $doc = new ShortcodeDocumentation('empty_shortcode');

        self::assertSame([], $doc->getAttributes());
    }

    #[Test]
    public function it_returns_new_instance_when_adding_attribute(): void
    {
        $doc = new ShortcodeDocumentation('shortcode');

        $attr = ShortcodeAttribute::required('id', 'Element ID');

        $newDoc = $doc->withAttribute($attr);

        // Original instance unchanged
        self::assertSame([], $doc->getAttributes());

        // New instance contains the attribute
        self::assertCount(1, $newDoc->getAttributes());
        self::assertSame($attr, $newDoc->getAttributes()[0]);
    }

    #[Test]
    public function it_allows_chaining_attributes(): void
    {
        $doc = new ShortcodeDocumentation('shortcode');

        $attr1 = ShortcodeAttribute::required('id', 'ID');
        $attr2 = ShortcodeAttribute::optional('class', 'CSS class', 'default');

        $newDoc = $doc->withAttribute($attr1)
        ->withAttribute($attr2);

        $attrs = $newDoc->getAttributes();
        self::assertCount(2, $attrs);
        self::assertSame($attr1, $attrs[0]);
        self::assertSame($attr2, $attrs[1]);
    }
}
