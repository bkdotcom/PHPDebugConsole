<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3 Split from Properties
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use Error;
use ReflectionClass;
use ReflectionProperty;

 /**
  * Get object property info
  */
class PropertiesInstance extends Properties
{
    /**
     * Add property instance info/values to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function add(Abstraction $abs)
    {
        if ($abs['isTraverseOnly']) {
            return;
        }
        $abs['isLazy']
            ? $this->addValuesLazy($abs)
            : $this->addValues($abs);
        $obj = $abs->getSubject();
        if (\is_object($obj)) {
            $this->addDebug($abs); // use __debugInfo() values if useDebugInfo' && method exists
        }
        $this->crate($abs);
    }

    /**
     * Add/Update properties with info from __debugInfo method
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addDebug(Abstraction $abs)
    {
        if (!$abs['collectPropertyValues']) {
            return;
        }
        if (!$abs['viaDebugInfo']) {
            // using __debugInfo is disabled, or object does not have __debugInfo method
            return;
        }
        $obj = $abs->getSubject();
        // temporarily store __debugInfo values in abstraction
        $abs['debugInfo'] = \call_user_func([$obj, '__debugInfo']);
        $properties = $this->addDebugWalk($abs);
        /*
            What remains in debugInfo are __debugInfo only values
        */
        foreach ($abs['debugInfo'] as $name => $value) {
            $properties[$name] = static::buildValues(array(
                'value' => $value,
                'valueFrom' => 'debugInfo',
                'visibility' => ['debug'],    // indicates this "property" is exclusive to debugInfo
            ));
        }
        $abs['properties'] = $properties;
        unset($abs['debugInfo']);
    }

    /**
     * Iterate over properties to set value & valueFrom
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return array
     */
    private function addDebugWalk(Abstraction $abs)
    {
        $debugInfo = $abs['debugInfo'];
        $keys = \array_keys($abs['properties']);
        $properties = \array_map(static function ($info, $name) use ($abs, &$debugInfo) {
            if (\array_key_exists($name, $abs['propertyOverrideValues'])) {
                // we used override value
                return $info;
            }
            if (\array_key_exists($name, $debugInfo)) {
                if ($debugInfo[$name] !== $info['value']) {
                    $info['value'] = $debugInfo[$name];
                    $info['valueFrom'] = 'debugInfo';
                }
                return $info;
            }
            $isInherited = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
            $isPrivateAncestor = \in_array('private', (array) $info['visibility'], true)
                && $isInherited;
            $info['debugInfoExcluded'] = $isPrivateAncestor === false;
            return $info;
        }, $abs['properties'], $keys);
        $properties = \array_combine($keys, $properties);
        $abs['debugInfo'] = \array_diff_key($debugInfo, $properties);
        return $properties;
    }

    /**
     * Add property values
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addValues(Abstraction $abs)
    {
        $properties = $abs['properties'];
        $valuedProps = array();
        $this->traverseAncestors($abs['reflector'], function (ReflectionClass $reflector) use ($abs, &$properties, &$valuedProps) {
            $className = $this->helper->getClassName($reflector);
            foreach ($reflector->getProperties() as $refProperty) {
                $name = $refProperty->getName();
                if (\in_array($name, $valuedProps, true)) {
                    continue;
                }
                $valuedProps[] = $name;
                $propInfo = isset($properties[$name])
                    ? $properties[$name]   // defined in class
                    : $this->buildViaRef($abs, $refProperty, true); // dynamic
                $properties[$name] = $this->processProperty($abs, $refProperty, $propInfo, $className);
            }
        });
        $abs['properties'] = $properties;
    }

    /**
     * Add property values for uninitialized (lazy) objects
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addValuesLazy(ObjectAbstraction $abs)
    {
        $properties = $abs['properties'];
        $reflector = $abs['reflector'];
        $classProperties = $abs->getInheritedValues()['properties'];
        $obj = $abs->getSubject();
        foreach ($classProperties as $name => $propInfo) {
            $isEager = false;
            if ($propInfo['declaredOrig']) {
                // declared property
                $refProperty = $reflector->getProperty($name);
                $isEager = $refProperty->isLazy($obj) === false;
            }
            $propInfo['isEager'] = $isEager;
            $propInfo['value'] = Abstracter::UNDEFINED;
            $properties[$name] = $isEager
                ? $this->addValue($propInfo, $abs, $refProperty)
                : $propInfo;
        }
        $abs['properties'] = $properties;
    }

    /**
     * Update property info with current value / declaration info
     *
     * @param Abstraction        $abs         Object Abstraction instance
     * @param ReflectionProperty $refProperty ReflectionProperty instance
     * @param array              $propInfo    Property info
     * @param string             $className   Current level className
     *
     * @return array updated property info
     */
    private function processProperty(Abstraction $abs, ReflectionProperty $refProperty, array $propInfo, $className = null)
    {
        if ($abs['isAnonymous'] && $refProperty->isDefault() && $className === $abs['className']) {
            // Necessary for anonymous classes
            $propInfo = $this->updateDeclarationVals(
                $propInfo,
                $this->helper->getClassName($refProperty->getDeclaringClass()),
                $className
            );
        }
        return $this->addValue($propInfo, $abs, $refProperty);
    }

    /**
     * Add 'value' and 'valueFrom' values to property info
     *
     * @param array              $propInfo    propInfo array
     * @param Abstraction        $abs         Object Abstraction instance
     * @param ReflectionProperty $refProperty ReflectionProperty
     *
     * @return array updated property info
     */
    private function addValue(array $propInfo, Abstraction $abs, ReflectionProperty $refProperty)
    {
        $obj = $abs->getSubject();
        $propName = $refProperty->getName();
        if (\array_key_exists($propName, $abs['propertyOverrideValues'])) {
            return $this->mergeOverrideValue($propInfo, $abs['propertyOverrideValues'][$propName]);
        }
        if ($abs['collectPropertyValues'] === false) {
            return $propInfo;
        }
        if (\is_object($obj) === false) {
            return $propInfo;
        }
        \set_error_handler(static function ($errType) use (&$propInfo) {
            // example: `DOMDocument::$actualEncoding` raises a deprecation notice when accessed
            if ($errType & (E_DEPRECATED | E_USER_DEPRECATED)) {
                $propInfo['isDeprecated'] = true;
            }
            return true;
        });
        try {
            $propInfo['value'] = $this->valueFromReflection($propInfo, $abs, $refProperty);
        } catch (Error $e) {
            // https://github.com/php/php-src/issues/15694
            // $refProperty->isInitialized() returns true if property has a get hook
            //   yet getRawValue() may throw "Typed property CLassName::$property must not be accessed before initialization"
        }
        \restore_error_handler();
        return $propInfo;
    }

    /**
     * Use propertyOverrideValue for value or propInfo
     *
     * @param array $propInfo      propInfo array
     * @param mixed $overrideValue override value (or propInfo array values)
     *
     * @return array
     */
    private function mergeOverrideValue(array $propInfo, $overrideValue)
    {
        $propInfo['valueFrom'] = 'debug';
        if (\is_array($overrideValue) && \array_intersect_key($overrideValue, static::$values)) {
            return \array_merge($propInfo, $overrideValue);
        }
        $propInfo['value'] = $overrideValue;
        return $propInfo;
    }

    /**
     * Obtain property value via `getRawValue` or `getValue`
     *
     * @param array              $propInfo    propInfo array
     * @param Abstraction        $abs         Object Abstraction instance
     * @param ReflectionProperty $refProperty ReflectionProperty
     *
     * @return mixed property value
     */
    private function valueFromReflection(array $propInfo, Abstraction $abs, ReflectionProperty $refProperty)
    {
        $refProperty->setAccessible(true); // only accessible via reflection
        $obj = $abs->getSubject();
        if ($propInfo['isVirtual']) {
            if (\in_array('get', $propInfo['hooks'], true) === false) {
                // virtual property with no getter = write-only
                return $propInfo['value']; // undefined
            } elseif ($abs['cfgFlags'] & AbstractObject::PROP_VIRTUAL_VALUE_COLLECT) {
                return $refProperty->getValue($obj);
            }
            return Abstracter::NOT_INSPECTED;
        }
        $isInitialized = PHP_VERSION_ID < 70400 || $refProperty->isInitialized($obj);
        if ($isInitialized === false) {
            return $propInfo['value']; // undefined
        }
        if (PHP_VERSION_ID >= 80400 && $propInfo['isStatic'] === false) {
            return $refProperty->getRawValue($obj);
        }
        return $refProperty->getValue($obj);
    }
}
