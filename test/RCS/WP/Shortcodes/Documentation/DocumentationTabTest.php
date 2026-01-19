<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes\Documentation;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RCS\WP\PluginOptions;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DocumentationTab::class)]
final class DocumentationTabTest extends TestCase
{
    #[Before]
    protected function setUpBrainMonkey(): void
    {
        Monkey\setUp();
    }

    #[After]
    protected function tearDownBrainMonkey(): void
    {
        Monkey\tearDown();
    }

    #[Test]
    public function it_registers_the_shortcodes_settings_section(): void
    {
        $pageSlug = 'test-page';

        Functions\expect('add_settings_section')
        ->once()
        ->with(
            DocumentationTab::OPTIONS_SECTION_SHORTCODES_ID,
            DocumentationTab::OPTIONS_SECTION_SHORTCODES_TITLE,
            \Mockery::type('callable'),
            $pageSlug
            );

        $options = $this->createMock(PluginOptions::class);
        $logger  = $this->createMock(LoggerInterface::class);

        $tab = new DocumentationTab($options, $logger);

        $tab->addSettings($pageSlug);

        self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    #[Test]
    public function sanitize_returns_input_unchanged(): void
    {
        $options = $this->createMock(PluginOptions::class);
        $logger  = $this->createMock(LoggerInterface::class);

        $tab = new DocumentationTab($options, $logger);

        $input = ['foo' => 'bar'];

        self::assertSame(
            $input,
            $tab->sanitize('page', $input)
            );
    }

    #[Test]
    public function sanitize_allows_null_input(): void
    {
        $options = $this->createMock(PluginOptions::class);
        $logger  = $this->createMock(LoggerInterface::class);

        $tab = new DocumentationTab($options, $logger);

        self::assertNull(
            $tab->sanitize('page', null)
            );
    }
}
