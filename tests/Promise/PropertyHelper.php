<?php

namespace bdk\Test\Promise;

class PropertyHelper
{
    /**
     * @param object $object   classname or object
     * @param string $property property name
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function get($object, $property)
    {
        $refProperty = new \ReflectionProperty($object, $property);
        if (PHP_VERSION_ID < 80100) {
            $refProperty->setAccessible(true);
        }
        return $refProperty->getValue($object);
    }

    /**
     * @param string|object $object   classname or object
     * @param string        $property property name
     * @param mixed         $value    new value
     *
     * @return void
     *
     * @throws \ReflectionException
     */
    public static function set($object, $property, $value)
    {
        $refProperty = new \ReflectionProperty($object, $property);
        if (PHP_VERSION_ID < 80100) {
            $refProperty->setAccessible(true);
        }
        $refProperty->isStatic()
            ? $refProperty->setValue(null, $value)
            : $refProperty->setValue($object, $value);
    }
}
