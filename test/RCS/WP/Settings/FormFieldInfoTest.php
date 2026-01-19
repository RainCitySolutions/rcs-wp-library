<?php
declare(strict_types=1);
namespace RCS\WP\Settings;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormFieldInfo::class)]
final class FormFieldInfoTest extends TestCase
{
    #[Test]
    public function it_initializes_properties_correctly(): void
    {
        $field = new FormFieldInfo(
            fieldId: 'user_email',
            fieldName: 'settings[user_email]',
            fieldValue: 'admin@example.com'
            );

        self::assertSame('user_email', $field->fieldId);
        self::assertSame('settings[user_email]', $field->fieldName);
        self::assertSame('admin@example.com', $field->fieldValue);
    }

    #[Test]
    public function it_allows_nullable_field_value(): void
    {
        $field = new FormFieldInfo(
            fieldId: 'user_name',
            fieldName: 'settings[user_name]',
            fieldValue: null
            );

        self::assertSame('user_name', $field->fieldId);
        self::assertSame('settings[user_name]', $field->fieldName);
        self::assertNull($field->fieldValue);
    }
}
