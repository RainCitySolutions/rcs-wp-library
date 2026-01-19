<?php
declare(strict_types=1);
namespace RCS\WP\Validation;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for validator unit tests.
 */
abstract class ValidatorTestCase extends TestCase
{
    /** @var list<array{string, string, string}> */
    protected array $errors = [];

    /**
     * Creates a testable validator instance.
     */
    abstract protected function createValidator(
        string $setting,
        string $key,
        string $fieldName = ''
        ): BaseValidator;

    protected function assertSingleError(
        string $setting,
        string $key,
        string $message
        ): void {
            self::assertCount(1, $this->errors);
            self::assertSame([$setting, $key, $message], $this->errors[0]);
    }

    protected function assertNoErrors(): void
    {
        self::assertSame([], $this->errors);
    }
}
