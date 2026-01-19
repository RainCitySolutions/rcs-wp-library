<?php
declare(strict_types = 1);
namespace RCS\WP\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumberValidator::class)]
final class NumberValidatorTest extends TestCase
{
    private TestNumberValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TestNumberValidator(
            setting: 'my_setting',
            key: 'my_key',
            fieldName: 'Age'
            );
    }

    #[Test]
    public function it_accepts_valid_number_within_default_range(): void
    {
        self::assertTrue($this->validator->isValid('42'));
        self::assertCount(0, $this->validator->errors);
    }

    #[Test]
    public function it_rejects_empty_string(): void
    {
        self::assertFalse($this->validator->isValid('   '));

        self::assertCount(1, $this->validator->errors);
        self::assertSame(
            'Age: You must provide a value.',
            $this->validator->errors[0]['message']
            );
    }

    #[Test]
    public function it_rejects_non_numeric_value(): void
    {
        self::assertFalse($this->validator->isValid('abc'));

        self::assertCount(1, $this->validator->errors);
        self::assertSame(
            'Age: Not a number.',
            $this->validator->errors[0]['message']
            );
    }

    #[Test]
    public function it_rejects_number_below_range(): void
    {
        $this->validator->setRange(10, 20);

        self::assertFalse($this->validator->isValid('5'));

        self::assertSame(
            'Age: Value not within range (10-20).',
            $this->validator->errors[0]['message']
            );
    }

    #[Test]
    public function it_rejects_number_above_range(): void
    {
        $this->validator->setRange(10, 20);

        self::assertFalse($this->validator->isValid('25'));

        self::assertSame(
            'Age: Value not within range (10-20).',
            $this->validator->errors[0]['message']
            );
    }

    #[Test]
    public function it_accepts_number_within_custom_range(): void
    {
        $this->validator->setRange(10, 20);

        self::assertTrue($this->validator->isValid('15'));
        self::assertCount(0, $this->validator->errors);
    }
}

final class TestNumberValidator extends NumberValidator
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
