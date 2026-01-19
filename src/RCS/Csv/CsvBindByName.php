<?php
declare(strict_types = 1);
namespace RCS\Csv;

use Attribute;

/**
 * Attribute for binding a name or names to a method or property in a class
 * to aid in loading CSV files (through LeagueCsv though may be useable for
 * other packages).
 *
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
class CsvBindByName
{
    private const INVALID_ARG_MSG = '\'column\' property can only be a string, or an array of strings';

    /**
     * Used to specify the column name(s) in a CSV to be bound to the method or
     * property.
     *
     * @Required
     * @var string[]
     */
    private array $columns;

    /**
     * Used to specify the output for values tagged with the attribute.
     *
     * @Optional
     * @var string
     */
    private string $outputFormat;

    /**
     *
     * @param string|string[] $column
     */
    public function __construct(string|array $column, string $outputFormat = "%s")
    {
        if (empty($column)) {
            throw new \InvalidArgumentException(self::INVALID_ARG_MSG);
        }

        if (is_string($column)) {
            $column = array($column);
        }

        if (!empty(array_filter($column, fn(string $entry) => empty(trim($entry))))) {
            throw new \InvalidArgumentException(self::INVALID_ARG_MSG);
        }

        $this->columns = array_map(fn(string $entry) => trim($entry), $column);
        $this->outputFormat = $outputFormat;
    }

    /**
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getOutputFormat(): string
    {
        return $this->outputFormat;
    }
}
