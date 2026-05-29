<?php
declare(strict_types = 1);
namespace RCS\WP\Ajax;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(AjaxHandlerImplTrait::class)]
#[UsesClass(AjaxHandlerResponse::class)]
final class AjaxHandlerImplTraitTest extends TestCase
{
    #[After]
    protected function resetGlobals(): void
    {
        unset($_GET['action'], $_REQUEST['action']);
    }

    #[Test]
    public function it_default_private_handler(): void
    {
        $sut = new AjaxHandlerImplTraitStub();

        $result = $sut->handlePrivateAjaxRequest('');

        self::assertSame('0', $result->getMessage());
        self::assertSame(400, $result->getTitle());
    }

    #[Test]
    public function it_default_public_handler(): void
    {
        $sut = new AjaxHandlerImplTraitStub();

        $result = $sut->handlePublicAjaxRequest('');

        self::assertSame('0', $result->getMessage());
        self::assertSame(400, $result->getTitle());
    }
}

final class AjaxHandlerImplTraitStub implements AjaxHandlerImplInf
{
    public static function getAjaxActions(): array
    {
        return [];
    }

    use AjaxHandlerImplTrait;
}
