<?php
declare(strict_types=1);
namespace RCS\Json;

use JsonMapper\Handler\FactoryRegistry;

/**
 * A class to handle receiving and serializing JSON formated date/times.
 */
class JsonDateTime extends \DateTime implements \JsonSerializable
{
    /**
     * Serializes the underlying DateTime object in ISO8601 format.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(): mixed
    {
        return $this->format(\DateTimeInterface::ISO8601);
    }


    /**
     * Adds the factory method to the factory for creating instances of the class.
     *
     * @param FactoryRegistry $factory A JsonMapper factory
     */
    public static function addToFactory(FactoryRegistry $factory): void
    {
        $factory->addFactory(JsonDateTime::class, static function (string $value) {
            if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $value)) {
                return new JsonDateTime($value, new \DateTimeZone('America/Los_Angeles'));
            } else {
                return new JsonDateTime($value);
            }
        });
    }
}
