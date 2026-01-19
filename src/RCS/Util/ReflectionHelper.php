<?php
declare(strict_types=1);
namespace RCS\Util;

class ReflectionHelper
{
    /**
     * Fetch the value of a class property.
     *
     * @param string $clazz The name of the class, typically provided as
     *      "Classname::class"
     * @param string $prop The name of the property to retrieve.
     *
     * @return mixed The value of the property. Note: If the value passed for
     *      $clazz is not actually a class then null is returned.
     */
    public static function getClassProperty(string $clazz, string $prop)
    {
        return self::getObjectProperty($clazz, $prop, null);
    }

    /**
     * Fetch the value of a object property.
     *
     * @param string $clazz The name of the class, typically provided as
     *      "Classname::class"
     * @param string $prop The name of the property to retrieve.
     * @param object|NULL $obj The object to retrieve the value from. If
     *      passed as null assumes the property is a class property.
     *
     * @return mixed The value of the property. Null is returned if value
     *      passed for $clazz is not actually a class or the property doesn't
     *      exist.
     */
    public static function getObjectProperty(string $clazz, string $prop, ?object $obj)
    {
        $result = null;

        if (class_exists($clazz)) {
            $reflectionClass = (new \ReflectionClass($clazz));

            if ($reflectionClass->hasProperty($prop)) {
                $reflectionProp = $reflectionClass->getProperty($prop);

                $result = $reflectionProp->getValue($obj);
            } else {
                $parentClass = $reflectionClass->getParentClass();
                if ($parentClass) {
                    $result = static::getObjectProperty($parentClass->getName(), $prop, $obj);
                }
            }
        }

        return $result;
    }

    /**
     * Set the value of a class property.
     *
     * @param string $clazz The name of the class, typically provided as
     *      "Classname::class"
     * @param string $prop The name of the property to set.
     * @param mixed $value The value to set for the property.
     */
    public static function setClassProperty(string $clazz, string $prop, $value): void
    {
        self::setObjectProperty($clazz, $prop, $value, null);
    }

    /**
     * Set the value of a object property.
     *
     * @param string $clazz The name of the class, typically provided as
     *      "Classname::class"
     * @param string $prop The name of the property to set.
     * @param mixed $value The value to set for the property.
     * @param object|NULL $obj The object to retrieve the value from. If
     *      passed as null assumes the property is a class property.
     */
    public static function setObjectProperty(string $clazz, string $prop, $value, ?object $obj): void
    {
        if (class_exists($clazz)) {
            $reflectionClass = (new \ReflectionClass($clazz));

            if ($reflectionClass->hasProperty($prop)) {
                $reflectionProp = $reflectionClass->getProperty($prop);

                $reflectionProp->setValue($obj, $value);
            } else {
                $parentClass = $reflectionClass->getParentClass();
                if ($parentClass) {
                    static::setObjectProperty($parentClass->getName(), $prop, $value, $obj);
                }
            }
        }
    }

    /**
     * Invoke an method on an object.
     *
     * @param string $clazz The name of the class, typically provided as
     *      "Classname::class"
     * @param object $obj The object to retrieve the value from, or null for static methods.
     * @param string $methodName The name of the method to invoke.
     * @param mixed ...$args Zero or more arguments to pass to the method.
     *
     * @return mixed The value returned by the method.
     */
    public static function invokeObjectMethod(string $clazz, ?object $obj, string $methodName, ...$args)
    {
        $result = null;

        if (class_exists($clazz)) {
            $reflection = new \ReflectionClass($clazz);

            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);

                if ($method->isPrivate() || $method->isProtected()) {
                    $method->setAccessible(true);
                    $result = $method->invokeArgs($obj, $args);
                    $method->setAccessible(false);
                } else {
                    $result = $method->invokeArgs($obj, $args);
                }
            } else {
                $parentClass = $reflection->getParentClass();
                if ($parentClass) {
                    $result = static::invokeObjectMethod($parentClass->getName(), $obj, $methodName, $args);
                }
            }
        }

        return $result;
    }

    /**
     * Fetch the name of the type for a property in a class.
     *
     * @param string $clazz The class containing the property
     * @param string $property The name of the property
     *
     * @return string|NULL The name of the type of the property, or null if
     *      the type cannot be determined or if the property doesn't exist in
     *      the class.
     */
    public static function getPropertyType(string $clazz, string $property): ?string
    {
        $result = null;

        if (class_exists($clazz)) {
            $reflectionClass = new \ReflectionClass($clazz);

            try {
                $reflectionProperty = $reflectionClass->getProperty($property);
                $reflectionType = $reflectionProperty->getType();

                $result = self::getNameForType($reflectionType);
            } catch (\ReflectionException $re) {
                // Property provided doesn't exist
            }
        }

        return $result;
    }

    /**
     * Determines the name of the type of the property.
     *
     * The method may be called recursively if the type is a union of types.
     * In this case, the first type in the union is returned. This may not
     * reflect the order the types appear in the code.
     *
     * @param \ReflectionType $reflectionType
     *
     * @return string|NULL The name for the type of the property or null if
     *      the type cannot be deteremined.
     *
     * @see https://www.php.net/manual/en/reflectionuniontype.gettypes.php
     */
    public static function getNameForType(?\ReflectionType $reflectionType): ?string
    {
        $propType = null;

        if ($reflectionType) {
            if ($reflectionType instanceof \ReflectionNamedType) {
                $propType = $reflectionType->getName();
            } elseif ($reflectionType instanceof \ReflectionUnionType) {
                foreach ($reflectionType->getTypes() as $type) {
                    $propType = self::getNameForType($type);

                    if ($propType) {
                        break;
                    }
                }
            } else {
                $propType = $reflectionType->__toString();
            }
        }

        return $propType;
    }
}
