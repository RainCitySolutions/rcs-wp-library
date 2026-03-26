<?php
declare(strict_types = 1);
namespace RCS\Csv;

use ReflectionProperty;

/**
 * A Trait work in conjuction with CsvBindByName annotation.
 * @phpstan-ignore trait.unused
 */
trait CsvBindByNameTrait
{
    /**
     * Generates an array of mappings between the value specified in the
     * CsvBindByName annotation and the property in the class.
     *
     * @return array<string, string> An associative array of header names in
     *      the CSV to class property names.
     */
    public static function getColumnPropertyMap(): array
    {
        $result = array();

        self::processAnnotations(
            $result,
            function (array &$result, CsvBindByName $attr, ReflectionProperty $property) {
                foreach ($attr->getColumns() as $column) {
                    $result[$column] = $property->name;
                }
            }
            );

        return $result;
    }

    /**
     * Fetch the list of CSV column names.
     *
     * @return string[] The array of column names.
     */
    public static function getColumnNames(): array
    {
        $result = array();

        self::processAnnotations(
            $result,
            function (array &$result, CsvBindByName $attr, ReflectionProperty $property) {  // NOSONAR - ignore unused parameter
                foreach ($attr->getColumns() as $column) {
                    array_push($result, $column);
                }
            }
            );

        return $result;
    }

    /**
     * Fetch a map of CSV column name to property value.
     *
     * @param object $obj
     *
     * @return array<string, mixed> An associative array of header names in
     *      the CSV to property values.
     */
    public static function getColumnValues(object $obj): array
    {
        assert(is_a($obj, get_called_class()));

        $result = array();

        self::processAnnotations(
            $result,
            function (array &$result, CsvBindByName $attr, ReflectionProperty $property) use ($obj) {
                $propValue = $property->isInitialized($obj) ? $property->getValue($obj) : '';

                foreach ($attr->getColumns() as $column) {
                    $result[$column] = $propValue;
                }
            }
            );

        return $result;
    }

    /**
     *
     * @param array<string, string> $result
     * @param callable $callback
     */
    private static function processAnnotations(array &$result, callable $callback): void
    {
        $reflectionClass = new \ReflectionClass(get_called_class());

        /** @var ReflectionProperty[] */
        $properties = $reflectionClass->getProperties();

        $properties = self::filterAndSort($properties);

        foreach ($properties as $property) {
            $attrs = $property->getAttributes(CsvBindByName::class);

            foreach ($attrs as $attr) {
                $callback(
                    $result,
                    $attr->newInstance(),
                    $property
                    );
            }
        }
    }

    /**
     * Filters a set of properties down to those with a CsvBindByName
     * attribute and sorts them by the desired order.
     *
     * @param ReflectionProperty[] $properties
     *
     * @return ReflectionProperty[]
     */
    private static function filterAndSort(array $properties): array
    {
        $filteredProps = array_filter(
            $properties,
            function(ReflectionProperty $property): bool {
                $attrs = $property->getAttributes(CsvBindByName::class);

                return !empty($attrs);
            }
        );

        usort($filteredProps, function(ReflectionProperty $a, ReflectionProperty $b) {
            $orderA = $a->getAttributes(CsvBindByName::class)[0]->newInstance()->getOrder();
            $orderB = $b->getAttributes(CsvBindByName::class)[0]->newInstance()->getOrder();

            return $orderA <=> $orderB;
        });

        return $filteredProps;
    }
}
