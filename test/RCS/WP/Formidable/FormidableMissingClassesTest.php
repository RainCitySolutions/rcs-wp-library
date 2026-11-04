<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

// Deliberately does NOT load the Formidable stubs: this covers the case where
// the Formidable Forms plugin is not active. Each test runs in its own process
// so stubs loaded by other test files can't leak in.

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RCS\WP\Formidable\Formidable;

#[PreserveGlobalState(false)]
#[CoversClass(Formidable::class)]
final class FormidableMissingClassesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testIdLookupsReturnNullWhenFormidableIsNotInstalled(): void
    {
        $this->assertNull(Formidable::getFormId('k'));
        $this->assertNull(Formidable::getFieldId('k'));
        $this->assertNull(Formidable::getViewId('k'));
    }

    #[RunInSeparateProcess]
    public function testKeyLookupsReturnNullWhenFormidableIsNotInstalled(): void
    {
        $this->assertNull(Formidable::getFormKey(1));
        $this->assertNull(Formidable::getFieldKey(1));
        $this->assertNull(Formidable::getViewKey(1));
    }
}
