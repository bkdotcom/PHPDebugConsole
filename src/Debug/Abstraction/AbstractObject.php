<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObjectMethods;
use bdk\Debug\Abstraction\AbstractObjectProperties;
use bdk\Debug\Component;
use bdk\Debug\Utility\PhpDoc;
use Error;
use ReflectionClass;
use ReflectionObject;
use RuntimeException;

/**
 * Abstracter:  Methods used to abstract objects
 */
class AbstractObject extends Component
{

    const COLLECT_CONSTANTS = 1;
    const COLLECT_METHODS = 2;
    const OUTPUT_CONSTANTS = 4;
    const OUTPUT_METHODS = 8;
    const OUTPUT_METHOD_DESC = 16;
    const COLLECT_ATTRIBUTES_OBJ = 32;
    const OUTPUT_ATTRIBUTES_OBJ = 64;
    const COLLECT_ATTRIBUTES_CONST = 128;
    const OUTPUT_ATTRIBUTES_CONST = 256;
    const COLLECT_ATTRIBUTES_PROP = 512;
    const OUTPUT_ATTRIBUTES_PROP = 1024;
    const COLLECT_ATTRIBUTES_METHOD = 2048;
    const OUTPUT_ATTRIBUTES_METHOD = 4096;
    const COLLECT_ATTRIBUTES_PARAM = 8192;
    const OUTPUT_ATTRIBUTES_PARAM = 16384;

	protected $abstracter;
    protected $debug;
    protected $methods;
    protected $properties;
	protected $phpDoc;

    /**
     * Constructor
     *
     * @param Abstracter $abstracter abstracter instance
     * @param PhpDoc     $phpDoc     phpDoc instance
     */
    public function __construct(Abstracter $abstracter, PhpDoc $phpDoc)
    {
        $this->abstracter = $abstracter;
        $this->phpDoc = $phpDoc;
        $this->debug = $abstracter->debug;
        $this->cfg = $abstracter->getCfg();
        $this->methods = new AbstractObjectMethods($abstracter, $phpDoc);
        $this->properties = new AbstractObjectProperties($abstracter, $phpDoc);
        if ($abstracter->debug->parentInstance) {
            // we only need to subscribe to these events from root channel
            return;
        }
        $abstracter->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, array($this, 'onStart'));
        $abstracter->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_END, array($this, 'onEnd'));
    }

    /**
     * returns information about an object
     *
     * @param object|string $obj    Object (or classname) to inspect
     * @param string        $method Method requesting abstraction
     * @param array         $hist   (@internal) array & object history
     *
     * @return Abstraction
     * @throws RuntimeException
     */
    public function getAbstraction($obj, $method = null, $hist = array())
    {
        $reflector = $this->getReflector($obj);
        $className = $reflector->getName();
        $interfaceNames = $reflector->getInterfaceNames();
        \sort($interfaceNames);
        $abs = new Abstraction(Abstracter::TYPE_OBJECT, array(
            'attributes' => array(),
            'className' => $className,
            'constants' => array(),
            'debugMethod' => $method,
            'definition' => array(
                'fileName' => $reflector->getFileName(),
                'startLine' => $reflector->getStartLine(),
                'extensionName' => $reflector->getExtensionName(),
            ),
            'extends' => array(),
            'flags' => $this->getFlags(),
            'implements' => $interfaceNames,
            'isAnonymous' => PHP_VERSION_ID >= 70000 && $reflector->isAnonymous(),
            'isExcluded' => $hist && $this->isExcluded($obj),    // don't exclude if we're debugging directly
            'isFinal' => $reflector->isFinal(),
            'isRecursion' => \in_array($obj, $hist, true),
            'methods' => array(),   // if !collectMethods, may still get ['__toString']['returnValue']
            'phpDoc' => array(
                'summary' => null,
                'desc' => null,
                // additional tags
            ),
            'properties' => array(),
            'scopeClass' => $this->getScopeClass($hist),
            'stringified' => null,
            'traverseValues' => array(),    // populated if method is table && traversable
            'viaDebugInfo' => $this->cfg['useDebugInfo'] && $reflector->hasMethod('__debugInfo'),
            // these are temporary values available during abstraction
            'collectPropertyValues' => true,
            'fullyQualifyPhpDocType' => $this->cfg['fullyQualifyPhpDocType'],
            'hist' => $hist,
            'isTraverseOnly' => false,
            'propertyOverrideValues' => array(),
            'reflector' => $reflector,
        ));
        if ($abs['isRecursion']) {
            return $this->absClean($abs);
        }
        $abs->setSubject($obj);
        $abs['isTraverseOnly'] = $this->isTraverseOnly($abs);
        /*
            Debug::EVENT_OBJ_ABSTRACT_START subscriber may
            set isExcluded
            set collectPropertyValues (boolean)
            set flags (int / bitmask)
            set propertyOverrideValues
            set stringified
            set traverseValues
        */
        $this->debug->publishBubbleEvent(Debug::EVENT_OBJ_ABSTRACT_START, $abs, $this->debug);
        if ($abs['isExcluded']) {
            return $this->absClean($abs);
        }
        $this->addMisc($abs);
        $this->methods->add($abs);
        $this->properties->add($abs);
        /*
            Debug::EVENT_OBJ_ABSTRACT_END subscriber has free reign to modify abtraction array
        */
        $this->debug->publishBubbleEvent(Debug::EVENT_OBJ_ABSTRACT_END, $abs, $this->debug);
        return $this->absClean($abs);
    }

    /**
     * Debug::EVENT_OBJ_ABSTRACT_START event subscriber
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onStart(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        if ($obj instanceof \DateTime || $obj instanceof \DateTimeImmutable) {
            $abs['stringified'] = $obj->format(\DateTime::ISO8601);
        } elseif ($obj instanceof \mysqli) {
            $this->onStartMysqli($abs);
        } elseif ($obj instanceof Debug) {
            $abs['propertyOverrideValues']['data'] = Abstracter::NOT_INSPECTED;
        } elseif ($obj instanceof PhpDoc) {
            $abs['propertyOverrideValues']['cache'] = Abstracter::NOT_INSPECTED;
        } elseif ($obj instanceof self) {
            $abs['propertyOverrideValues']['methodCache'] = Abstracter::NOT_INSPECTED;
        } elseif ($abs['isAnonymous']) {
            $this->handleAnonymous($abs);
        }
    }

    /**
     * Debug::EVENT_OBJ_ABSTRACT_END event subscriber
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onEnd(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        if ($obj instanceof \Exception) {
            if (isset($abs['properties']['xdebug_message'])) {
                $abs['properties']['xdebug_message']['debugInfoExcluded'] = true;
            }
        } elseif ($obj instanceof \mysqli && !$abs['collectPropertyValues']) {
            $propsAlwaysAvail = array(
                'client_info','client_version','connect_errno','connect_error','errno','error','stat'
            );
            $reflectionObject = $abs['reflector'];
            foreach ($propsAlwaysAvail as $name) {
                if (!isset($abs['properties'][$name])) {
                    // stat property may be missing in php 7.4??
                    continue;
                }
                $abs['properties'][$name]['value'] = $reflectionObject->getProperty($name)->getValue($obj);
            }
        }
        $this->promoteParamDescs($abs);
    }

    /**
     * Sort things and remove temporary values
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return Abstraction
     */
    private function absClean(Abstraction $abs)
    {
        $keysTemp = array(
            'collectPropertyValues',
            'fullyQualifyPhpDocType',
            'hist',
            'isAnonymous',
            'isTraverseOnly',
            'propertyOverrideValues',
            'reflector',
        );
        $values = \array_diff_key($abs->getValues(), \array_flip($keysTemp));
        if (!$abs['isRecursion'] && !$abs['isExcluded']) {
            $this->sort($values['constants']);
            $this->sort($values['properties']);
            $this->sort($values['methods']);
        }
        return $abs
            ->setSubject(null)
            ->setValues($values);
    }

    /**
     * Add object's constants to abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addConstants(Abstraction $abs)
    {
        if (!($abs['flags'] & self::COLLECT_CONSTANTS)) {
            return;
        }
        $inclAttributes = $abs['flags'] & self::COLLECT_ATTRIBUTES_CONST === self::COLLECT_ATTRIBUTES_CONST;
        $abs['constants'] = PHP_VERSION_ID >= 70100
            ? $this->getConstantsReflection($abs['reflector'], $inclAttributes)
            : $this->getConstants($abs['reflector']);
    }

    /**
     * Get constant arrays via `getReflectionConstants` (php 7.1)
     * This gets us visibility and access to phpDoc
     *
     * @param ReflectionClass $reflector      ReflectionClass or ReflectionObject
     * @param bool            $inclAttributes Whether to include attributes
     *
     * @return array name => array
     */
    private function getConstantsReflection(ReflectionClass $reflector, $inclAttributes = true)
    {
        $constants = array();
        while ($reflector) {
            foreach ($reflector->getReflectionConstants() as $const) {
                $name = $const->getName();
                if (isset($constants[$name])) {
                    continue;
                }
                $phpDoc = $this->properties->getVarPhpDoc($const);
                $vis = 'public';
                if ($const->isPrivate()) {
                    $vis = 'private';
                } elseif ($const->isProtected()) {
                    $vis = 'protected';
                }
                $constants[$name] = array(
                    'attributes' => $inclAttributes
                        ? $this->properties->getAttributes($const)
                        : array(),
                    'desc' => $phpDoc['desc'],
                    'value' => $const->getValue(),
                    'visibility' => $vis,
                );
            }
            $reflector = $reflector->getParentClass();
        }
        return $constants;
    }

    /**
     * Get constant arrays
     *
     * @param ReflectionClass $reflector ReflectionClass or ReflectionObject
     *
     * @return array name => array
     */
    private function getConstants(ReflectionClass $reflector)
    {
        $constants = array();
        while ($reflector) {
            foreach ($reflector->getConstants() as $name => $value) {
                if (isset($constants[$name])) {
                    continue;
                }
                $constants[$name] = array(
                    'attributes' => array(),
                    'desc' => null,
                    'value' => $value,
                    'visibility' => 'public',
                );
            }
            $reflector = $reflector->getParentClass();
        }
        return $constants;
    }

    /**
     * Populate constants, extends, phpDoc, & traverseValues
     *
     * methods & properties added separately
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addMisc(Abstraction $abs)
    {
        $reflector = $abs['reflector'];
        $abs['phpDoc'] = $this->phpDoc->getParsed($reflector);
        if ($abs['isTraverseOnly']) {
            $this->addTraverseValues($abs);
            return;
        }
        if ($abs['flags'] & self::COLLECT_ATTRIBUTES_OBJ) {
            $abs['attributes'] = $this->properties->getAttributes($reflector);
        }
        $this->addConstants($abs);
        while ($reflector = $reflector->getParentClass()) {
            if ($abs['phpDoc'] === array('summary' => null, 'desc' => null)) {
                $abs['phpDoc'] = $this->phpDoc->getParsed($reflector);
            }
            $abs['extends'][] = $reflector->getName();
        }
    }

    /**
     * Populate rows or columns (traverseValues) if we're outputing as a table
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addTraverseValues(Abstraction $abs)
    {
        if ($abs['traverseValues']) {
            return;
        }
        $obj = $abs->getSubject();
        $abs['hist'][] = $obj;
        foreach ($obj as $k => $v) {
            $abs['traverseValues'][$k] = $this->abstracter->crate($v, $abs['debugMethod'], $abs['hist']);
        }
    }

    /**
     * Get configuration flags
     *
     * @return int bitmask
     */
    private function getFlags()
    {
        $flags = array(
            'collectAttributesConst' => self::COLLECT_ATTRIBUTES_CONST,
            'collectAttributesMethod' => self::COLLECT_ATTRIBUTES_METHOD,
            'collectAttributesObj' => self::COLLECT_ATTRIBUTES_OBJ,
            'collectAttributesParam' => self::COLLECT_ATTRIBUTES_PARAM,
            'collectAttributesProp' => self::COLLECT_ATTRIBUTES_PROP,
            'collectConstants' => self::COLLECT_CONSTANTS,
            'collectMethods' => self::COLLECT_METHODS,
            'outputAttributesConst' => self::OUTPUT_ATTRIBUTES_CONST,
            'outputAttributesMethod' => self::OUTPUT_ATTRIBUTES_METHOD,
            'outputAttributesObj' => self::OUTPUT_ATTRIBUTES_OBJ,
            'outputAttributesParam' => self::OUTPUT_ATTRIBUTES_PARAM,
            'outputAttributesProp' => self::OUTPUT_ATTRIBUTES_PROP,
            'outputConstants' => self::OUTPUT_CONSTANTS,
            'outputMethodDesc' => self::OUTPUT_METHOD_DESC,
            'outputMethods' => self::OUTPUT_METHODS,
        );
        $flagVals = \array_intersect_key($flags, \array_filter($this->cfg));
        return \array_reduce($flagVals, function ($carry, $val) {
            return $carry | $val;
        }, 0);
    }

    /**
     * Get ReflectionObject or ReflectionClass instance
     *
     * @param object|string $obj Object (or classname)
     *
     * @return ReflectionObject|ReflectionClass
     * @throws RuntimeException
     */
    private function getReflector($obj)
    {
        if (\is_object($obj)) {
            return new ReflectionObject($obj);
        }
        if (\is_string($obj) && (\class_exists($obj) || \interface_exists($obj))) {
            return new ReflectionClass($obj);
        }
        throw new RuntimeException(__METHOD__ . ' expects and object, className, or interfaceName');
    }

    /**
     * for nested objects (ie object is a property of an object), returns the parent class
     *
     * @param array $hist Array & object history
     *
     * @return null|string
     */
    private function getScopeClass(&$hist)
    {
        $className = null;
        for ($i = \count($hist) - 1; $i >= 0; $i--) {
            if (\is_object($hist[$i])) {
                $className = \get_class($hist[$i]);
                break;
            }
        }
        if ($i < 0) {
            $backtrace = $this->debug->backtrace->get();
            foreach ($backtrace as $i => $frame) {
                if (!isset($frame['class']) || \strpos($frame['class'], __NAMESPACE__) !== 0) {
                    break;
                }
            }
            $className = isset($backtrace[$i]['class'])
                ? $backtrace[$i]['class']
                : null;
        }
        return $className;
    }

    /**
     * Make anonymous class more user friendly
     *
     *  * adjust classname
     *  * add file & line debug properties
     *
     * Where is this anonymous class notation documented?
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function handleAnonymous(Abstraction $abs)
    {
        $abs['className'] = $this->debug->utility->friendlyClassName($abs['reflector']);
        $properties = $abs['properties'];
        $properties['debug.file'] = $this->properties->buildPropInfo(array(
            'type' => Abstracter::TYPE_STRING,
            'value' => $abs['definition']['fileName'],
            'valueFrom' => 'debug',
            'visibility' => 'debug',
        ));
        $properties['debug.line'] = $this->properties->buildPropInfo(array(
            'type' => Abstracter::TYPE_INT,
            'value' => (int) $abs['definition']['startLine'],
            'valueFrom' => 'debug',
            'visibility' => 'debug',
        ));
        $abs['properties'] = $properties;
    }

    /**
     * Is the passed object excluded from debugging?
     *
     * @param object|string $obj object (or classname) to test
     *
     * @return bool
     */
    private function isExcluded($obj)
    {
        $classname = \is_object($obj)
            ? \get_class($obj)
            : $obj;
        $whitelist = $this->cfg['objectsWhitelist'];
        if ($whitelist !== null) {
            // wildcard in whitelist?  we'll allow it
            if (\array_intersect(array('*', $classname), $whitelist)) {
                return false;
            }
            foreach ($whitelist as $class) {
                if (\is_subclass_of($obj, $class)) {
                    return false;
                }
            }
            return true;
        }
        $blacklist = $this->cfg['objectsExclude'];
        if (\array_intersect(array('*', $classname), $blacklist)) {
            return true;
        }
        // now test "instanceof"
        foreach ($blacklist as $class) {
            if (\is_subclass_of($obj, $class)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Test if only need to populate traverseValues
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return bool
     */
    private function isTraverseOnly(Abstraction $abs)
    {
        if ($abs['debugMethod'] === 'table' && \count($abs['hist']) < 2) {
            $obj = $abs->getSubject();
            if ($obj instanceof \Traversable) {
                $abs['flags'] &= ~self::COLLECT_METHODS;  // set collect methods to "false"
                return true;
            }
        }
        return false;
    }

    /**
     * Test if we can collect mysqli property values
     *
     * @param Abstraction $abs [description]
     *
     * @return void
     */
    private function onStartMysqli(Abstraction $abs)
    {
        /*
            test if stat() throws an error (ie "Property access is not allowed yet")
            if so, don't collect property values
        */
        \set_error_handler(function ($errno, $errstr) {
            throw new RuntimeException($errstr, $errno);
        }, E_ALL);
        try {
            $mysqli = $abs->getSubject();
            $mysqli->stat();
        } catch (Error $e) {
            $abs['collectPropertyValues'] = false;
        } catch (RuntimeException $e) {
            $abs['collectPropertyValues'] = false;
        }
        \restore_error_handler();
    }

    /**
     * Reuse the phpDoc description from promoted __construct params
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function promoteParamDescs(Abstraction $abs)
    {
        if (isset($abs['methods']['__construct'])) {
            foreach ($abs['methods']['__construct']['params'] as $info) {
                if ($info['isPromoted'] && $info['desc']) {
                    $paramName = \substr($info['name'], 1); // toss the "$"
                    $abs['properties'][$paramName]['desc'] = $info['desc'];
                }
            }
        }
    }

    /**
     * Sorts constant/property/method array by visibility or name
     *
     * @param array $array array to sort
     *
     * @return void
     */
    protected function sort(&$array)
    {
        if (!$array) {
            return;
        }
        $sort = $this->cfg['objectSort'];
        if ($sort === 'name') {
            // rather than a simple key sort, use array_multisort so that __construct is always first
            $sortData = array();
            foreach (\array_keys($array) as $name) {
                $sortData[$name] = $name === '__construct'
                    ? '0'
                    : $name;
            }
            \array_multisort($sortData, $array);
        } elseif ($sort === 'visibility') {
            $sortVisOrder = array('public', 'protected', 'private', 'magic', 'magic-read', 'magic-write', 'debug');
            $sortData = array();
            foreach ($array as $name => $info) {
                $sortData['name'][$name] = $name === '__construct'
                    ? '0'     // always place __construct at the top
                    : $name;
                $vis = \is_array($info['visibility'])
                    ? $info['visibility'][0]
                    : $info['visibility'];
                $sortData['vis'][$name] = \array_search($vis, $sortVisOrder);
            }
            \array_multisort($sortData['vis'], $sortData['name'], $array);
        }
    }
}
