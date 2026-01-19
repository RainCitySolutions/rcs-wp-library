<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ShortcodeImplTrait::class)]
final class ShortcodeImplTraitTest extends TestCase
{
    #[After]
    protected function resetGlobals(): void
    {
        unset($_GET['action'], $_REQUEST['action']);
    }

    #[Test]
    public function it_renders_empty_shortcode_by_default(): void
    {
        $sut = new ShortcodeImplTraitStub();

        self::assertSame('', $sut->renderShortcode());
    }

    #[Test]
    public function it_returns_empty_documentation(): void
    {
        $sut = new ShortcodeImplTraitStub();

        self::assertSame([], $sut->getDocumentation([]));
    }

    #[Test]
    public function it_returns_combined_attributes_unchanged(): void
    {
        $sut = new ShortcodeImplTraitStub();

        $combined = ['a' => '1', 'b' => '2'];

        $result = $sut->filterAttributes(
            $combined,
            ['a' => '0'],
            ['a' => '1'],
            'test'
            );

        self::assertSame($combined, $result);
    }

    #[Test]
    public function it_returns_no_scripts_and_styles_by_default(): void
    {
        $sut = new ShortcodeImplTraitStub();

        self::assertSame([], $sut->getScripts());
        self::assertSame([], $sut->getStyles());
    }

    #[Test]
    public function it_detects_edit_preview_mode_via_get(): void
    {
        $_GET['action'] = 'edit';

        $sut = new ShortcodeImplTraitStub();

        self::assertTrue($sut->isEditPreviewModePublic());
    }

    #[Test]
    public function it_detects_edit_preview_mode_via_request(): void
    {
        $_REQUEST['action'] = 'avia_ajax_text_to_preview';

        $sut = new ShortcodeImplTraitStub();

        self::assertTrue($sut->isEditPreviewModePublic());
    }

    #[Test]
    public function it_returns_false_when_not_in_edit_preview_mode(): void
    {
        $sut = new ShortcodeImplTraitStub();

        self::assertFalse($sut->isEditPreviewModePublic());
    }
}

final class ShortcodeImplTraitStub
{
    use ShortcodeImplTrait;

    // Expose protected method for testing
    public function isEditPreviewModePublic(): bool
    {
        return $this->isEditPreviewMode();
    }
}
