<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\PropertiesPhpDoc;
use ReflectionClass;
use ReflectionProperty;

/**
 * Get object property info
 */
class Properties extends Inheritable
{
    private static $basePropInfo = array(
        'attributes' => array(),
        'debugInfoExcluded' => false,   // true if not included in __debugInfo
        'declaredLast' => null,         // Class where property last declared
                                        //   null value implies property was dynamically added
        'declaredOrig' => null,         // Class where originally declared
        'declaredPrev' => null,         // Class where previously declared
                                        //   populated only if overridden
        'desc' => null,                 // from phpDoc
        'forceShow' => false,           // initially show the property/value (even if protected or private)
                                        //   if value is an array, expand it
        'isPromoted' => false,
        'isReadOnly' => false,
        'isStatic' => false,
        'type' => null,
        'value' => Abstracter::UNDEFINED,
        'valueFrom' => 'value',         // 'value' | 'debugInfo' | 'debug'
        'visibility' => 'public',       // public, private, protected, magic, magic-read, magic-write, debug
                                        //   may also be an array (ie: ['private', 'magic-read'])
    );

    private $phpDoc;

    /**
     * Constructor
     *
     * @param AbstractObject $abstractObject Object abstracter
     */
    public function __construct(AbstractObject $abstractObject)
    {
        parent::__construct($abstractObject);
        $this->phpDoc = new PropertiesPhpDoc($abstractObject->helper);
    }

    /**
     * Add declared property info
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function add(Abstraction $abs)
    {
        $this->addViaRef($abs);
        $this->phpDoc->addViaPhpDoc($abs); // magic properties documented via phpDoc

        $properties = $abs['properties'];

        // note: for user-defined classes getDefaultProperties
        //   will return the current value for static properties
        $defaultValues = $abs['reflector']->getDefaultProperties();
        foreach ($defaultValues as $name => $value) {
            $properties[$name]['value'] = $value;
        }

        if ($abs['isAnonymous']) {
            $properties['debug.file'] = static::buildPropValues(array(
                'type' => Abstracter::TYPE_STRING,
                'value' => $abs['definition']['fileName'],
                'valueFrom' => 'debug',
                'visibility' => 'debug',
            ));
            $properties['debug.line'] = static::buildPropValues(array(
                'type' => Abstracter::TYPE_INT,
                'value' => (int) $abs['definition']['startLine'],
                'valueFrom' => 'debug',
                'visibility' => 'debug',
            ));
        }

        $abs['properties'] = $properties;

        $this->crate($abs);
    }

    /**
     * Add property instance info/values to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function addInstance(Abstraction $abs)
    {
        if ($abs['isTraverseOnly']) {
            return;
        }
        $this->addValues($abs);
        $obj = $abs->getSubject();
        if (\is_object($obj)) {
            $this->addDebug($abs); // use __debugInfo() values if useDebugInfo' && method exists
        }
        $this->crate($abs);
    }

    /**
     * Build property info buy passing values
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildPropValues($values = array())
    {
        return \array_merge(static::$basePropInfo, $values);
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
        $abs['debugInfo'] = \call_user_func(array($obj, '__debugInfo'));
        $properties = $this->addDebugWalk($abs);
        /*
            What remains in debugInfo are __debugInfo only values
        */
        foreach ($abs['debugInfo'] as $name => $value) {
            $properties[$name] = static::buildPropValues(array(
                'value' => $value,
                'valueFrom' => 'debugInfo',
                'visibility' => 'debug',    // indicates this "property" is exclusive to debugInfo
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
        $properties = $abs['properties'];
        foreach ($properties as $name => $info) {
            if (\array_key_exists($name, $abs['propertyOverrideValues'])) {
                // we're using override value
                unset($debugInfo[$name]);
                continue;
            }
            if (\array_key_exists($name, $debugInfo)) {
                if ($debugInfo[$name] !== $info['value']) {
                    $properties[$name]['value'] = $debugInfo[$name];
                    $properties[$name]['valueFrom'] = 'debugInfo';
                }
                unset($debugInfo[$name]);
                continue;
            }
            $isInherited = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
            $isPrivateAncestor = \in_array('private', (array) $info['visibility'], true)
                && $isInherited;
            $properties[$name]['debugInfoExcluded'] = $isPrivateAncestor === false;
        }
        $abs['debugInfo'] = $debugInfo;
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
            $refProperties = $reflector->getProperties();
            while ($refProperties) {
                $refProperty = \array_pop($refProperties);
                $name = $refProperty->getName();
                if (\in_array($name, $valuedProps, true)) {
                    continue;
                }
                $valuedProps[] = $name;
                $propInfo = isset($properties[$name])
                    ? $properties[$name]   // defined in class
                    : $this->buildViaRef($abs, $refProperty);
                if ($abs['isAnonymous'] && $refProperty->isDefault() && $className === $abs['className']) {
                    // Necessary for anonymous classes
                    $propInfo = $this->updateDeclarationVals(
                        $propInfo,
                        $this->helper->getClassName($refProperty->getDeclaringClass()),
                        $className
                    );
                }
                if ($abs['collectPropertyValues']) {
                    $propInfo = $this->addValue($propInfo, $abs, $refProperty);
                }
                $properties[$name] = $propInfo;
            }
        });
        $abs['properties'] = $properties;
    }

    /**
     * Add 'value' and 'valueFrom' values to property info
     *
     * @param array              $propInfo    propInfo array
     * @param Abstraction        $abs         Object Abstraction instance
     * @param ReflectionProperty $refProperty ReflectionProperty
     *
     * @return array updated propInfo
     */
    private function addValue($propInfo, Abstraction $abs, ReflectionProperty $refProperty)
    {
        $propName = $refProperty->getName();
        if (\array_key_exists($propName, $abs['propertyOverrideValues'])) {
            $propInfo['valueFrom'] = 'debug';
            $value = $abs['propertyOverrideValues'][$propName];
            if (\is_array($value) && \array_intersect_key($value, static::$basePropInfo)) {
                return \array_merge($propInfo, $value);
            }
            $propInfo['value'] = $value;
            return $propInfo;
        }
        $obj = $abs->getSubject();
        $isInstance = \is_object($obj);
        if ($isInstance) {
            $refProperty->setAccessible(true); // only accessible via reflection
            $isInitialized = PHP_VERSION_ID < 70400 || $refProperty->isInitialized($obj);
            $propInfo['value'] = $isInitialized
                ? $refProperty->getValue($obj)
                : Abstracter::UNDEFINED;  // value won't be displayed
        }
        return $propInfo;
    }

    /**
     * Adds properties to abstraction via reflection
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addViaRef(Abstraction $abs)
    {
        /*
            We trace our lineage to learn where properties are inherited from
        */
        $properties = $abs['properties'];
        $this->traverseAncestors($abs['reflector'], function (ReflectionClass $reflector) use ($abs, &$properties) {
            $className = $this->helper->getClassName($reflector);
            $refProperties = $reflector->getProperties();
            while ($refProperties) {
                $refProperty = \array_pop($refProperties);
                if ($refProperty->isDefault() === false) {
                    continue;
                }
                $name = $refProperty->getName();
                $info = isset($properties[$name])
                    ? $properties[$name]
                    : $this->buildViaRef($abs, $refProperty);
                $info = $this->updateDeclarationVals(
                    $info,
                    $this->helper->getClassName($refProperty->getDeclaringClass()),
                    $className
                );
                $properties[$name] = $info;
            }
        });
        \ksort($properties);
        $abs['properties'] = $properties;
    }

    /**
     * Build property info via reflection
     *
     * @param Abstraction        $abs         Object Abstraction instance
     * @param ReflectionProperty $refProperty ReflectionProperty instance
     *
     * @return array
     */
    private function buildViaRef(Abstraction $abs, ReflectionProperty $refProperty)
    {
        $phpDoc = $this->helper->getPhpDocVar($refProperty, $abs['fullyQualifyPhpDocType']);
        $refProperty->setAccessible(true); // only accessible via reflection
        return static::buildPropValues(array(
            'attributes' => $abs['cfgFlags'] & AbstractObject::PROP_ATTRIBUTE_COLLECT
                ? $this->helper->getAttributes($refProperty)
                : array(),
            'desc' => $abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT
                ? $phpDoc['desc']
                : null,
            'isPromoted' =>  PHP_VERSION_ID >= 80000
                ? $refProperty->isPromoted()
                : false,
            'isReadOnly' => PHP_VERSION_ID >= 80100
                ? $refProperty->isReadOnly()
                : false,
            'isStatic' => $refProperty->isStatic(),
            'type' => $this->getPropType($phpDoc['type'], $refProperty),
            'visibility' => $this->helper->getVisibility($refProperty),
        ));
    }

    /**
     * "Crate" property values
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function crate(Abstraction $abs)
    {
        $properties = $abs['properties'];
        foreach ($properties as $name => $info) {
            $info['value'] = $this->abstracter->crate($info['value'], $abs['debugMethod'], $abs['hist']);
            $properties[$name] = $info;
        }
        $abs['properties'] = $properties;
    }

    /**
     * Get Property's type
     * Priority given to phpDoc type, followed by declared type (PHP 7.4)
     *
     * @param string             $phpDocType  Type specified in phpDoc block
     * @param ReflectionProperty $refProperty ReflectionProperty instance
     *
     * @return string|null
     */
    private function getPropType($phpDocType, ReflectionProperty $refProperty)
    {
        if ($phpDocType !== null) {
            return $phpDocType;
        }
        return PHP_VERSION_ID >= 70400
            ? $this->helper->getTypeString($refProperty->getType())
            : null;
    }
}
