<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\PropertiesPhpDoc;
use bdk\Debug\Abstraction\Type;
use ReflectionClass;
use ReflectionProperty;

/**
 * Get object property info
 */
class Properties extends AbstractInheritable
{
    /** @var array<string,mixed> */
    private static $basePropInfo = array(
        'attributes' => array(),
        'debugInfoExcluded' => false,   // true if not included in __debugInfo
        'declaredLast' => null,         // Class where property last declared
                                        //   null value implies property was dynamically added
        'declaredOrig' => null,         // Class where originally declared
        'declaredPrev' => null,         // Class where previously declared
                                        //   populated only if overridden
        'forceShow' => false,           // initially show the property/value (even if protected or private)
                                        //   if value is an array, expand it
        'isDeprecated' => false,        // some internal php objects may raise a deprecation notice when accessing
                                        //    example: `DOMDocument::$actualEncoding`
                                        //    or may come from phpDoc tag
        'isPromoted' => false,
        'isReadOnly' => false,
        'isStatic' => false,
        'phpDoc' => array(
            'desc' => '',
            'summary' => '',
        ),
        'type' => null,
        'value' => Abstracter::UNDEFINED,
        'valueFrom' => 'value',         // 'value' | 'debugInfo' | 'debug'
        'visibility' => 'public',       // public, private, protected, magic, magic-read, magic-write, debug
                                        //   may also be an array (ie: ['private', 'magic-read'])
    );

    /** @var PropertiesPhpDoc */
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
            $properties['debug.file'] = static::buildValues(array(
                'type' => Type::TYPE_STRING,
                'value' => $abs['definition']['fileName'],
                'valueFrom' => 'debug',
                'visibility' => 'debug',
            ));
            $properties['debug.line'] = static::buildValues(array(
                'type' => Type::TYPE_INT,
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
     * Build property info by passing values
     *
     * @param array $values Values to apply
     *
     * @return array
     */
    public static function buildValues($values = array())
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
            $properties[$name] = static::buildValues(array(
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
        $keys = \array_keys($abs['properties']);
        $properties = \array_map(static function ($info, $name) use ($abs, &$debugInfo) {
            if (\array_key_exists($name, $abs['propertyOverrideValues'])) {
                // we're using override value
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
                    : $this->buildViaRef($abs, $refProperty); // dynamic
                $properties[$name] = $this->addValuesPropInfo($abs, $refProperty, $propInfo, $className);
            }
        });
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
    private function addValuesPropInfo(Abstraction $abs, ReflectionProperty $refProperty, array $propInfo, $className)
    {
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
        return $propInfo;
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
    private function addValue(array $propInfo, Abstraction $abs, ReflectionProperty $refProperty)
    {
        $obj = $abs->getSubject();
        $propName = $refProperty->getName();
        if (\array_key_exists($propName, $abs['propertyOverrideValues'])) {
            $propInfo['valueFrom'] = 'debug';
            $value = $abs['propertyOverrideValues'][$propName];
            if (\is_array($value) && \array_intersect_key($value, static::$basePropInfo)) {
                return \array_merge($propInfo, $value);
            }
            $propInfo['value'] = $value;
        } elseif (\is_object($obj)) {
            $propInfo = $this->addValueInstance($propInfo, $abs, $refProperty);
        }
        return $propInfo;
    }

    /**
     * Obtain property value from instance
     *
     * @param array              $propInfo    propInfo array
     * @param Abstraction        $abs         Object Abstraction instance
     * @param ReflectionProperty $refProperty ReflectionProperty
     *
     * @return array updated propInfo
     */
    private function addValueInstance(array $propInfo, Abstraction $abs, ReflectionProperty $refProperty)
    {
        $obj = $abs->getSubject();
        $refProperty->setAccessible(true); // only accessible via reflection
        $isInitialized = PHP_VERSION_ID < 70400 || $refProperty->isInitialized($obj);
        \set_error_handler(static function ($errType) use (&$propInfo) {
            // example: DOMDocument::$actualEncoding  raises a deprecation notice when accessed
            if ($errType & (E_DEPRECATED | E_USER_DEPRECATED)) {
                $propInfo['isDeprecated'] = true;
            }
        });
        $propInfo['value'] = $isInitialized
            ? $refProperty->getValue($obj)
            : Abstracter::UNDEFINED;  // value won't be displayed
        \restore_error_handler();
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
            foreach ($reflector->getProperties() as $refProperty) {
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
        $type = $this->helper->getType($phpDoc['type'], $refProperty);
        unset($phpDoc['type']);
        return static::buildValues(array(
            'attributes' => $abs['cfgFlags'] & AbstractObject::PROP_ATTRIBUTE_COLLECT
                ? $this->helper->getAttributes($refProperty)
                : array(),
            'isDeprecated' => isset($phpDoc['deprecated']), // if inspecting an instance,
                                                            // we will also check if ReflectionProperty::getValue throws a deprecation notice
            'isPromoted' =>  PHP_VERSION_ID >= 80000
                ? $refProperty->isPromoted()
                : false,
            'isReadOnly' => PHP_VERSION_ID >= 80100
                ? $refProperty->isReadOnly()
                : false,
            'isStatic' => $refProperty->isStatic(),
            'phpDoc' => $phpDoc,
            'type' => $type,
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
        $keys = array();
        $properties = $abs['properties'];
        $utf8 = $this->abstracter->debug->utf8;
        foreach ($properties as $name => $info) {
            if (\is_string($name) && $utf8->isUtf8($name) === false) {
                unset($properties[$name]);
                $md5 = \md5($name);
                $keys[$md5] = $this->abstracter->crate($name);
                $name = $md5;
            }
            $info['value'] = $this->abstracter->crate($info['value'], $abs['debugMethod'], $abs['hist']);
            $properties[$name] = $info;
        }
        $abs['keys'] = $keys;
        $abs['properties'] = $properties;
    }
}
