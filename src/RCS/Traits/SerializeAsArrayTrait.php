<?php
declare(strict_types=1);
namespace RCS\Traits;

/**
 * @phpstan-ignore trait.unused
 */
trait SerializeAsArrayTrait
{
    /**
     * Method to be overridden by classes utilizing the trait should they
     * want to manipulate the array before it is serialized.
     *
     * @param array<string, mixed> &$vars A reference to the array of values to be serialized.
     */
    protected function preSerialize(array &$vars): void // NOSONAR - unused parameter
    {
        // Default implementation
    }

    /**
     * Method to be overridden by classes utilizing the trait should they
     * want to manipulate the array before it is unserialized.
     *
     * An example might be when the class has changed and a previously
     * serialized value needs to be converted before being loaded into the
     * class.
     *
     * @param array<string, mixed> &$vars A reference to the array of values to be unserialized.
     */
    protected function preUnserialize(array &$vars): void   // NOSONAR - unused parameter
    {
        // Default implementation
    }

    /**
     * Method to be overridden by classes utilizing the trait should they
     * want to perform any work after the instance has been unserialized.
     */
    protected function postUnserialize(): void
    {
        // Default implementation
    }

    /**
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array    // NOSONAR - unrecognized magic method
    {
        $vars = get_object_vars($this);

        $this->preSerialize($vars);

        return $vars;
    }

    /**
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void    // NOSONAR - unrecognized magic method
    {
        $this->preUnserialize ($data);

        foreach ($data as $var => $value) {
            /**
             * Only set values for properties of the object.
             *
             * Generally this will be the case but this accounts for the
             * possiblity that a field may be removed from the class in the
             * future.
             */
            if (property_exists($this, $var)) {
                $this->$var = $value;
            }
        }

        $this->postUnserialize();
    }

    /**
     * Implementation of the \Serializable::serialize() method
     *
     * @return string|NULL A string representation of the object or null if
     *      it cannot be serialized.
     */
    public function serialize(): ?string
    {
        return \serialize($this->__serialize());
    }

    /**
     * Implementation of the \Serializable::unserialize() method
     *
     * @param string $serialized A string representation of the object
     */
    public function unserialize($serialized): void
    {
        $this->__unserialize(\unserialize($serialized));
    }
}
