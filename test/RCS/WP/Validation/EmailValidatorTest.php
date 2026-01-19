<?php
declare(strict_types = 1);
namespace RCS\WP\Validation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EmailValidator::class)]
final class EmailValidatorTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_single_email(): void
    {
        $validator = new TestEmailValidator('setting', 'key', 'Email');

        $result = $validator->isValid('user@example.com');

        self::assertTrue($result);
        self::assertSame([], $validator->errors);
    }

    #[Test]
    public function it_rejects_invalid_single_email(): void
    {
        $validator = new TestEmailValidator('setting', 'key', 'Email');

        $result = $validator->isValid('invalid-email');

        self::assertFalse($result);
        self::assertCount(1, $validator->errors);
        self::assertSame(
            ['setting', 'key', 'Email: Invalid email address.'],
            $validator->errors[0]
            );
    }

    #[Test]
    public function it_accepts_multiple_valid_emails(): void
    {
        $validator = new TestEmailValidator('setting', 'key');

        $result = $validator->isValid([
            'a@example.com',
            'b@example.org',
        ]);

        self::assertTrue($result);
        self::assertSame([], $validator->errors);
    }

    #[Test]
    public function it_rejects_if_any_email_is_invalid(): void
    {
        $validator = new TestEmailValidator('setting', 'key');

        $result = $validator->isValid([
            'a@example.com',
            'bad-email',
            'b@example.org',
        ]);

        self::assertFalse($result);
        self::assertCount(1, $validator->errors);
        self::assertSame(
            ['setting', 'key', 'Invalid email address.'],
            $validator->errors[0]
            );
    }

    #[Test]
    public function it_trims_whitespace_before_validation(): void
    {
        $validator = new TestEmailValidator('setting', 'key');

        $result = $validator->isValid('  user@example.com  ');

        self::assertTrue($result);
        self::assertSame([], $validator->errors);
    }
}

final class TestEmailValidator extends EmailValidator
{
    /** @var list<array{string, string, string}> */
    public array $errors = [];

    protected function addSettingsError(
        string $setting,
        string $key,
        string $message
        ): void {
            $this->errors[] = [$setting, $key, $message];
    }
}
