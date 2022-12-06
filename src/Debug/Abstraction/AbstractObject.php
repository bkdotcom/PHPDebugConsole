<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\AbstractObjectConstants;
use bdk\Debug\Abstraction\AbstractObjectHelper;
use bdk\Debug\Abstraction\AbstractObjectMethods;
use bdk\Debug\Abstraction\AbstractObjectProperties;
use bdk\Debug\Data;
use bdk\Debug\Utility\PhpDoc;
use Error;
use ReflectionEnum;
use RuntimeException;

/**
 * Abstracter:  Methods used to abstract objects
 */
class AbstractObject extends AbstractComponent
{
    // GENERAL
    const PHPDOC_COLLECT = 1; // 2^0
    const PHPDOC_OUTPUT = 2;  // 2^1
    const OBJ_ATTRIBUTE_COLLECT = 4;
    const OBJ_ATTRIBUTE_OUTPUT = 8;
    const TO_STRING_OUTPUT = 16; // 2^4
    const BRIEF = 4194304; // 2^22

    // CONSTANTS
    const CONST_COLLECT = 32;
    const CONST_OUTPUT = 64;
    const CONST_ATTRIBUTE_COLLECT = 128;
    const CONST_ATTRIBUTE_OUTPUT = 256; // 2^8

    // CASE
    const CASE_COLLECT = 512;
    const CASE_OUTPUT = 1024;
    const CASE_ATTRIBUTE_COLLECT = 2048;
    const CASE_ATTRIBUTE_OUTPUT = 4096; // 2^12

    // PROPERTIES
    const PROP_ATTRIBUTE_COLLECT = 8192;
    const PROP_ATTRIBUTE_OUTPUT = 16384; // 2^14

    // METHODS
    const METHOD_COLLECT = 32768;
    const METHOD_OUTPUT = 65536;
    const METHOD_ATTRIBUTE_COLLECT = 131072;
    const METHOD_ATTRIBUTE_OUTPUT = 262144;
    const METHOD_DESC_OUTPUT = 524288;
    const PARAM_ATTRIBUTE_COLLECT = 1048576;
    const PARAM_ATTRIBUTE_OUTPUT = 2097152; // 2^21

    public static $cfgFlags = array(
        // GENERAL
        'phpDocCollect' => self::PHPDOC_COLLECT,
        'phpDocOutput' => self::PHPDOC_OUTPUT,
        'objAttributeCollect' => self::OBJ_ATTRIBUTE_COLLECT,
        'objAttributeOutput' => self::OBJ_ATTRIBUTE_OUTPUT,
        'toStringOutput' => self::TO_STRING_OUTPUT,
        'brief' => self::BRIEF,

        // CONSTANTS
        'constCollect' => self::CONST_COLLECT,
        'constOutput' => self::CONST_OUTPUT,
        'constAttributeCollect' => self::CONST_ATTRIBUTE_COLLECT,
        'constAttributeOutput' => self::CONST_ATTRIBUTE_OUTPUT,

        // CASE
        'caseCollect' => self::CASE_COLLECT,
        'caseOutput' => self::CASE_OUTPUT,
        'caseAttributeCollect' => self::CASE_ATTRIBUTE_COLLECT,
        'caseAttributeOutput' => self::CASE_ATTRIBUTE_OUTPUT,

        // PROPERTIES
        'propAttributeCollect' => self::PROP_ATTRIBUTE_COLLECT,
        'propAttributeOutput' => self::PROP_ATTRIBUTE_OUTPUT,

        // METHODS
        'methodCollect' => self::METHOD_COLLECT,
        'methodOutput' => self::METHOD_OUTPUT,
        'methodAttributeCollect' => self::METHOD_ATTRIBUTE_COLLECT,
        'methodAttributeOutput' => self::METHOD_ATTRIBUTE_OUTPUT,
        'methodDescOutput' => self::METHOD_DESC_OUTPUT,
        'paramAttributeCollect' => self::PARAM_ATTRIBUTE_COLLECT,
        'paramAttributeOutput' => self::PARAM_ATTRIBUTE_OUTPUT,
    );

    public $helper;

    protected $abstracter;
    protected $debug;
    protected $constants;
    protected $methods;
    protected $properties;

    /**
     * Default object abstraction values
     *
     * @var array Array of key/values
     */
    protected static $values = array(
        'type' => Abstracter::TYPE_OBJECT,
        'attributes' => array(),
        'cfgFlags' => 0,
        'className' => '',
        'constants' => array(),
        'debugMethod' => '',
        'definition' => array(
            'fileName' => '',
            'startLine' => 1,
            'extensionName' => '',
        ),
        'extends' => array(),
        'implements' => array(),
        'isAnonymous' => false,
        'isExcluded' => false,  // don't exclude if we're debugging directly
        'isFinal' => false,
        'isRecursion' => false,
        'methods' => array(),  // if !methodCollect, may still get ['__toString']['returnValue']
        'phpDoc' => array(
            'desc' => null,
            'summary' => null,
            // additional tags
        ),
        'properties' => array(),
        'scopeClass' => '',
        'stringified' => null,
        'traverseValues' => array(),  // populated if method is table
        'viaDebugInfo' => false,
    );

    /**
     * Constructor
     *
     * @param Abstracter $abstracter abstracter instance
     */
    public function __construct(Abstracter $abstracter)
    {
        $this->abstracter = $abstracter;
        $this->debug = $abstracter->debug;
        $this->helper = new AbstractObjectHelper($this->debug->phpDoc);
        $this->constants = new AbstractObjectConstants($abstracter, $this->helper);
        $this->methods = new AbstractObjectMethods($abstracter, $this->helper);
        $this->properties = new AbstractObjectProperties($abstracter, $this->helper);
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
        $reflector = $this->debug->php->getReflector($obj);
        $abs = new Abstraction(Abstracter::TYPE_OBJECT, \array_merge(self::$values, array(
            'cfgFlags' => $this->getCfgFlags(),
            'className' => $reflector->getName(),
            'debugMethod' => $method,
            'isAnonymous' => PHP_VERSION_ID >= 70000 && $reflector->isAnonymous(),
            'isExcluded' => $hist && $this->isExcluded($obj),    // don't exclude if we're debugging directly
            'isFinal' => $reflector->isFinal(),
            'isRecursion' => \in_array($obj, $hist, true),
            'scopeClass' => $this->getScopeClass($hist),
            'viaDebugInfo' => $this->cfg['useDebugInfo'] && $reflector->hasMethod('__debugInfo'),
            // these are temporary values available during abstraction
            'collectPropertyValues' => true,
            'fullyQualifyPhpDocType' => $this->cfg['fullyQualifyPhpDocType'],
            'hist' => $hist,
            'isTraverseOnly' => false,
            'propertyOverrideValues' => array(),
            'reflector' => $reflector,
        )));
        $abs['hist'][] = $obj;
        $abs->setSubject($obj);
        $this->doAbstraction($abs);
        $this->absClean($abs);
        return $abs;
    }

    /**
     * Get the default object abstraction values
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildObjValues($values = array())
    {
        $cfgFlags = \array_reduce(self::$cfgFlags, static function ($carry, $val) {
            return $carry | $val;
        }, 0);
        $cfgFlags &= ~self::BRIEF;
        return \array_merge(
            self::$values,
            array(
                'cfgFlags' => $cfgFlags,
            ),
            $values
        );
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
            // check for both DateTime and DateTimeImmutable
            //   DateTimeInterface (and DateTimeImmutable) not available until Php 5.5
            $abs['isTraverseOnly'] = false;
            $abs['stringified'] = $obj->format(\DateTime::ISO8601);
        } elseif ($obj instanceof \mysqli) {
            $this->onStartMysqli($abs);
        } elseif ($obj instanceof Data) {
            $abs['propertyOverrideValues']['data'] = Abstracter::NOT_INSPECTED;
        } elseif ($obj instanceof PhpDoc) {
            $abs['propertyOverrideValues']['cache'] = Abstracter::NOT_INSPECTED;
        } elseif ($obj instanceof AbstractObjectMethods) {
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
        if ($obj instanceof \Exception && isset($abs['properties']['xdebug_message'])) {
            $abs['properties']['xdebug_message']['debugInfoExcluded'] = true;
        } elseif ($obj instanceof \mysqli && !$abs['collectPropertyValues']) {
            $this->onEndMysqli($abs);
        }
        $this->promoteParamDescs($abs);
    }

    /**
     * Sort things and remove temporary values
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function absClean(Abstraction $abs)
    {
        $keysTemp = array(
            'collectPropertyValues',
            'fullyQualifyPhpDocType',
            'hist',
            'isTraverseOnly',
            'propertyOverrideValues',
            'reflector',
        );
        $values = \array_diff_key($abs->getValues(), \array_flip($keysTemp));
        if (!($abs['cfgFlags'] & self::PHPDOC_COLLECT)) {
            $values['phpDoc']['desc'] = null;
            $values['phpDoc']['summary'] = null;
        }
        if (!$abs['isRecursion'] && !$abs['isExcluded']) {
            $this->helper->sort($values['constants'], $this->cfg['objectSort']);
            $this->helper->sort($values['properties'], $this->cfg['objectSort']);
            $this->helper->sort($values['methods'], $this->cfg['objectSort']);
        }
        $abs
            ->setSubject(null)
            ->setValues($values);
    }

    /**
     * Add enum's case's @var desc (if exists) to phpDoc
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addEnumCasePhpDoc($abs)
    {
        if (!($abs['cfgFlags'] & self::PHPDOC_COLLECT)) {
            return;
        }
        $reflector = $abs['reflector'];
        if (!($reflector instanceof ReflectionEnum)) {
            return;
        }
        $name = $reflector->getProperty('name')->getValue($abs->getSubject());
        $caseReflector = $reflector->getCase($name);
        $desc = $this->helper->getPhpDocVar($caseReflector)['desc'];
        if ($desc) {
            $abs['phpDoc'] = \array_merge($abs['phpDoc'], array(
                'desc' => \trim($abs['phpDoc']['summary'] . "\n" . $abs['phpDoc']['desc']),
                'summary' => $desc,
            ));
        }
    }

    /**
     * Populate definition, implements, & isTraverseOnly and Enum name
     *
     * Added before we check isExcluded
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addMisc1(Abstraction $abs)
    {
        $reflector = $abs['reflector'];
        $abs['definition'] = array(
            // note that for a Closure object, this likely isn't the info we want...
            //   will be populated with where the where the closure is defined
            //   from AbstractObjectProperties::addClosure
            'fileName' => $reflector->getFileName(),
            'startLine' => $reflector->getStartLine(),
            'extensionName' => $reflector->getExtensionName(),
        );

        $interfaceNames = $reflector->getInterfaceNames();
        \sort($interfaceNames);
        $abs['implements'] = $interfaceNames;

        if ($abs['isRecursion'] && \in_array('UnitEnum', $abs['implements'], true)) {
            $abs['properties']['name'] = array(
                'value' => $abs->getSubject()->name,
            );
        }
        $abs['isTraverseOnly'] = $this->isTraverseOnly($abs);
    }

    /**
     * Populate attributes, extends, phpDoc, & traverseValues
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addMisc2(Abstraction $abs)
    {
        $reflector = $abs['reflector'];
        $abs['phpDoc'] = $this->helper->getPhpDoc($reflector);
        $this->addEnumCasePhpDoc($abs);
        if ($abs['isTraverseOnly']) {
            \ksort($abs['phpDoc']);
            $this->addTraverseValues($abs);
            return;
        }
        if ($abs['cfgFlags'] & self::OBJ_ATTRIBUTE_COLLECT) {
            $abs['attributes'] = $this->helper->getAttributes($reflector);
        }
        while ($reflector = $reflector->getParentClass()) {
            if ($abs['phpDoc'] === array('summary' => null, 'desc' => null)) {
                $abs['phpDoc'] = $this->helper->getPhpDoc($reflector);
            }
            $abs['extends'][] = $reflector->getName();
        }
        \ksort($abs['phpDoc']);
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
     * Add attributes, constants, properties, methods, constants, etc
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function doAbstraction(Abstraction $abs)
    {
        $this->addMisc1($abs);
        if ($abs['isRecursion']) {
            return;
        }
        /*
            Debug::EVENT_OBJ_ABSTRACT_START subscriber may
            set isExcluded
            set collectPropertyValues (boolean)
            set cfgFlags (int / bitmask)
            set propertyOverrideValues
            set stringified
            set traverseValues
        */
        $this->debug->publishBubbleEvent(Debug::EVENT_OBJ_ABSTRACT_START, $abs, $this->debug);
        if ($abs['isExcluded']) {
            return;
        }
        $this->addMisc2($abs);
        $this->constants->add($abs);
        $this->constants->addCases($abs);
        $this->methods->add($abs);
        $this->properties->add($abs);
        /*
            Debug::EVENT_OBJ_ABSTRACT_END subscriber has free reign to modify abtraction array
        */
        $this->debug->publishBubbleEvent(Debug::EVENT_OBJ_ABSTRACT_END, $abs, $this->debug);
    }

    /**
     * Get configuration flags
     *
     * @return int bitmask
     */
    private function getCfgFlags()
    {
        $flagVals = \array_intersect_key(self::$cfgFlags, \array_filter($this->cfg));
        $bitmask = \array_reduce($flagVals, static function ($carry, $val) {
            return $carry | $val;
        }, 0);
        if ($bitmask & self::BRIEF) {
            $bitmask &= ~self::CASE_COLLECT;
            $bitmask &= ~self::CONST_COLLECT;
            $bitmask &= ~self::METHOD_COLLECT;
            $bitmask &= ~self::OBJ_ATTRIBUTE_COLLECT;
            $bitmask &= ~self::PROP_ATTRIBUTE_COLLECT;
            $bitmask &= ~self::TO_STRING_OUTPUT;
        }
        return $bitmask;
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
        for ($i = \count($hist) - 1; $i >= 0; $i--) {
            if (\is_object($hist[$i])) {
                return \get_class($hist[$i]);
            }
        }
        $callerInfo = $this->debug->backtrace->getCallerInfo();
        return $callerInfo['classContext'];
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
        $abs['className'] = $this->debug->php->friendlyClassName($abs['reflector']);
        $properties = $abs['properties'];
        $properties['debug.file'] = $this->properties->buildPropValues(array(
            'type' => Abstracter::TYPE_STRING,
            'value' => $abs['definition']['fileName'],
            'valueFrom' => 'debug',
            'visibility' => 'debug',
        ));
        $properties['debug.line'] = $this->properties->buildPropValues(array(
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
     * @param object $obj object (or classname) to test
     *
     * @return bool
     */
    private function isExcluded($obj)
    {
        return $this->cfg['objectsWhitelist'] !== null
            ? $this->isObjInList($obj, $this->cfg['objectsWhitelist']) === false
            : $this->isObjInList($obj, $this->cfg['objectsExclude']);
    }

    /**
     * Is object included in objectsWhitelist or objectsExclude  ??
     *
     * @param object $obj  object being tested
     * @param array  $list classname list (may include *)
     *
     * @return bool
     */
    private function isObjInList($obj, $list)
    {
        $classname = \get_class($obj);
        if (\array_intersect(array('*', $classname), $list)) {
            return true;
        }
        foreach ($list as $class) {
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
        if ($abs['debugMethod'] === 'table' && \count($abs['hist']) < 4) {
            $abs['cfgFlags'] &= ~self::CONST_COLLECT;  // set collect constants to "false"
            $abs['cfgFlags'] &= ~self::METHOD_COLLECT;  // set collect methods to "false"
            return true;
        }
        return false;
    }

    /**
     * Add mysqli property values
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function onEndMysqli(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        $propsAlwaysAvail = array(
            'client_info','client_version','connect_errno','connect_error','errno','error','stat'
        );
        \set_error_handler(static function () {
            // ignore error
        });
        $refObject = $abs['reflector'];
        foreach ($propsAlwaysAvail as $name) {
            if (!isset($abs['properties'][$name])) {
                // stat property may be missing in php 7.4??
                continue;
            }
            $abs['properties'][$name]['value'] = $refObject->getProperty($name)->getValue($obj);
        }
        \restore_error_handler();
    }

    /**
     * Test if we can collect mysqli property values
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function onStartMysqli(Abstraction $abs)
    {
        /*
            test if stat() throws an error (ie "Property access is not allowed yet")
            if so, don't collect property values
        */
        \set_error_handler(static function ($errno, $errstr) {
            throw new RuntimeException($errstr, $errno); // @codeCoverageIgnore
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
        if (isset($abs['methods']['__construct']) === false) {
            return;
        }
        foreach ($abs['methods']['__construct']['params'] as $info) {
            if ($info['isPromoted'] && $info['desc']) {
                $paramName = \substr($info['name'], 1); // toss the "$"
                $abs['properties'][$paramName]['desc'] = $info['desc'];
            }
        }
    }
}
