<?php
declare(strict_types = 1);
namespace RCS\WP\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlValidator::class)]
final class UrlValidatorTest extends TestCase
{
    private TestUrlValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TestUrlValidator(
            setting: 'my_setting',
            key: 'my_key',
            fieldName: 'Website'
            );
    }

    #[Test]
    public function it_accepts_full_url(): void
    {
        self::assertTrue($this->validator->isValid('https://example.com'));
        self::assertCount(0, $this->validator->errors);
    }

    #[Test]
    public function it_accepts_host_name_only(): void
    {
        self::assertTrue($this->validator->isValid('example.com'));
        self::assertCount(0, $this->validator->errors);
    }

    #[Test]
    public function it_accepts_relative_path(): void
    {
        self::assertTrue($this->validator->isValid('/about'));
        self::assertCount(0, $this->validator->errors);
    }

    #[Test]
    public function it_rejects_invalid_url(): void
    {
        self::assertFalse($this->validator->isValid('not a url'));

        // UrlValidator itself does not add an error message
        // it only returns false if invalid
        self::assertCount(0, $this->validator->errors);
    }

    #[Test]
    public function it_rejects_empty_string_before_url_validation(): void
    {
        self::assertFalse($this->validator->isValid(''));

        self::assertCount(1, $this->validator->errors);
        self::assertSame(
            'Website: You must provide a value.',
            $this->validator->errors[0]['message']
            );
    }

    #[Test]
    public function it_rejects_whitespace_only_string(): void
    {
        self::assertFalse($this->validator->isValid('   '));

        self::assertCount(1, $this->validator->errors);
        self::assertSame(
            'Website: You must provide a value.',
            $this->validator->errors[0]['message']
            );
    }
}

final class TestUrlValidator extends UrlValidator
{
    /** @var list<array{setting:string,key:string,message:string}> */
    public array $errors = [];

    protected function addSettingsError(
        string $setting,
        string $key,
        string $message
        ): void {
            $this->errors[] = [
                'setting' => $setting,
                'key'     => $key,
                'message' => $message,
            ];
    }
}
