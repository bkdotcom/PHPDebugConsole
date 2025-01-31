<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
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
use Reflector;

/**
 * Get class definition property info
 */
class Properties extends AbstractInheritable
{
    /** @var array<string,mixed> */
    protected static $values = array(
        'attributes' => array(),
        'debugInfoExcluded' => false,   // true if not included in __debugInfo
        'declaredLast' => null,         // Class where property last declared
                                        //   null value implies property was dynamically added
        'declaredOrig' => null,         // Class where originally declared
        'declaredPrev' => null,         // Class where previously declared
                                        //   populated only if overridden
        'forceShow' => false,           // initially show the property/value (even if protected or private)
                                        //   if value is an array, expand it
        'hooks' => array(),             // PHP 8.4+
        'isDeprecated' => false,        // some internal php objects may raise a deprecation notice when accessing
                                        //    example: `DOMDocument::$actualEncoding`
                                        //    or may come from phpDoc tag
        'isFinal' => false,             // PHP 8.0+
        'isPromoted' => false,
        'isReadOnly' => false,
        'isStatic' => false,
        'isVirtual' => false,           // PHP 8.4+
        'phpDoc' => array(
            'desc' => '',
            'summary' => '',
        ),
        'type' => null,
        'value' => Abstracter::UNDEFINED,
        'valueFrom' => 'value',         // 'value' | 'debugInfo' | 'debug'
        'visibility' => ['public'],     // array
                                        //   public, private, private-set,
                                        //   protected, protected-set,
                                        //   magic, magic-read, magic-write,
                                        //   debug
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
                'visibility' => ['debug'],
            ));
            $properties['debug.line'] = static::buildValues(array(
                'type' => Type::TYPE_INT,
                'value' => (int) $abs['definition']['startLine'],
                'valueFrom' => 'debug',
                'visibility' => ['debug'],
            ));
        }

        $abs['properties'] = $properties;

        $this->crate($abs);
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
        // use ReflectionClass (not ReflectionObject) to get get definition info
        $reflectionClass = new ReflectionClass($abs['reflector']->getName());

        /*
            We trace our lineage to learn where properties are inherited from
        */
        $properties = $abs['properties'];
        $this->traverseAncestors($reflectionClass, function (ReflectionClass $reflector) use ($abs, &$properties) {
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
     * @param bool               $isDynamic   (false) Is property dynamic or defined in class
     *
     * @return array
     */
    protected function buildViaRef(Abstraction $abs, ReflectionProperty $refProperty, $isDynamic = false)
    {
        $phpDoc = $this->helper->getPhpDocVar($refProperty, $abs['fullyQualifyPhpDocType']);
        $refProperty->setAccessible(true); // only accessible via reflection
        return static::buildValues(array(
            'attributes' => $abs['cfgFlags'] & AbstractObject::PROP_ATTRIBUTE_COLLECT
                ? $this->helper->getAttributes($refProperty)
                : array(),
            'hooks' => PHP_VERSION_ID >= 80400 && $isDynamic === false // https://github.com/php/php-src/issues/15718
                ? \array_keys($refProperty->getHooks())
                : array(),
            'isDeprecated' => isset($phpDoc['deprecated']), // if inspecting an instance,
                                                            // we will also check if ReflectionProperty::getValue throws a deprecation notice
            'isFinal' => PHP_VERSION_ID >= 80400 && $refProperty->isFinal(),
            'isPromoted' =>  PHP_VERSION_ID >= 80000 && $refProperty->isPromoted(),
            'isReadOnly' => PHP_VERSION_ID >= 80100 && $refProperty->isReadOnly(),
            'isStatic' => $refProperty->isStatic(),
            'isVirtual' => PHP_VERSION_ID >= 80400 && $refProperty->isVirtual(), // at least one hook and none of the hooks reference the property
            'phpDoc' => $phpDoc,
            'type' => $this->helper->getType($phpDoc['type'], $refProperty),
            'visibility' => $this->getVisibility($refProperty),
        ));
    }

    /**
     * "Crate" property values
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    protected function crate(Abstraction $abs)
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
            unset($info['phpDoc']['type']);
            $properties[$name] = $info;
        }
        $abs['keys'] = $keys;
        $abs['properties'] = $properties;
    }

    /**
     * Get constant/method/property visibility
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return list<string>
     */
    protected static function getVisibility(Reflector $reflector)
    {
        $visibility = (array) parent::getVisibility($reflector);
        if (PHP_VERSION_ID >= 80400 && $reflector->isPrivateSet()) {
            $visibility[] = 'private-set';
        } elseif (PHP_VERSION_ID >= 80400 && $reflector->isProtectedSet()) {
            $visibility[] = 'protected-set';
        }
        return $visibility;
    }
}
