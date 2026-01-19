<?php
declare(strict_types = 1);
namespace RCS\WP\Validation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BaseValidator::class)]
final class BaseValidatorTest extends TestCase
{
    #[Test]
    public function it_adds_error_without_field_name(): void
    {
        $validator = new TestValidator(
            setting: 'my_setting',
            key: 'my_key'
            );

        $validator->isValid('bad');

        self::assertCount(1, $validator->errors);
        self::assertSame(
            ['my_setting', 'my_key', 'Invalid value'],
            $validator->errors[0]
            );
    }

    #[Test]
    public function it_prefixes_error_with_field_name(): void
    {
        $validator = new TestValidator(
            setting: 'my_setting',
            key: 'my_key',
            fieldName: 'Email'
            );

        $validator->isValid('bad');

        self::assertCount(1, $validator->errors);
        self::assertSame(
            ['my_setting', 'my_key', 'Email: Invalid value'],
            $validator->errors[0]
            );
    }
}


final class TestValidator extends BaseValidator
{
    /** @var list<array{string, string, string}> */
    public array $errors = [];

    public function isValid(string $input): bool
    {
        $this->addError('Invalid value');
        return false;
    }

    protected function addSettingsError(
        string $setting,
        string $key,
        string $message
        ): void {
            $this->errors[] = [$setting, $key, $message];
    }
}
