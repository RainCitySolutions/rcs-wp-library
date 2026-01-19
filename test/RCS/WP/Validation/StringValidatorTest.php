<?php
declare(strict_types = 1);
namespace RCS\WP\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringValidator::class)]
final class StringValidatorTest extends TestCase
{
    private TestStringValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TestStringValidator(
            setting: 'my_setting',
            key: 'my_key',
            fieldName: 'Username'
            );
    }

    #[Test]
    public function it_accepts_non_empty_string(): void
    {
        self::assertTrue($this->validator->isValid('hello'));

        self::assertCount(0, $this->validator->errors);
    }

    #[Test]
    public function it_rejects_empty_string(): void
    {
        self::assertFalse($this->validator->isValid(''));

        self::assertCount(1, $this->validator->errors);
        self::assertSame(
            'Username: You must provide a value.',
            $this->validator->errors[0]['message']
            );
    }

    #[Test]
    public function it_rejects_whitespace_only_string(): void
    {
        self::assertFalse($this->validator->isValid('   '));

        self::assertCount(1, $this->validator->errors);
        self::assertSame(
            'Username: You must provide a value.',
            $this->validator->errors[0]['message']
            );
    }
}


final class TestStringValidator extends StringValidator
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
