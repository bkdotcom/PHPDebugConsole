<?php

namespace bdk\Test\Promise;

class PropertyHelper
{
    /**
     * @param object $object
     * @param string $property
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function get($object, $property)
    {
        $refProperty = new \ReflectionProperty($object, $property);
        $refProperty->setAccessible(true);
        return $refProperty->getValue($object);
    }

    /**
     * @param string|object $object   classname or object
     * @param string        $property
     * @param mixed         $value
     *
     * @return void
     *
     * @throws \ReflectionException
     */
    public static function set($object, $property, $value)
    {
        $refProperty = new \ReflectionProperty($object, $property);
        $refProperty->setAccessible(true);
        $refProperty->isStatic()
            ? $refProperty->setValue($value)
            : $refProperty->setValue($object, $value);
    }
}
