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

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Object\Constants;
use bdk\Debug\Abstraction\Object\Definition;
use bdk\Debug\Abstraction\Object\Helper;
use bdk\Debug\Abstraction\Object\Methods;
use bdk\Debug\Abstraction\Object\Properties;
use bdk\Debug\Abstraction\Object\PropertiesInstance;
use bdk\Debug\Abstraction\Object\Subscriber;
use ReflectionClass;
use ReflectionEnumUnitCase;
use RuntimeException;

/**
 * Abstracter:  Methods used to abstract objects
 *
 * @property-read Abstracter $abstracter
 * @property-read Constants $constants
 * @property-read Debug $debug
 * @property-read Definition $definition
 * @property-read Helper $helper
 * @property-read Methods $methods
 * @property-read Properties $properties
 * @property-read PropertiesInstance $properties
 */
class AbstractObject extends AbstractComponent
{
    const BRIEF = 4194304; // 2^22

    // CASE (2^9 - 2^12)
    const CASE_COLLECT = 512;
    const CASE_OUTPUT = 1024;
    const CASE_ATTRIBUTE_COLLECT = 2048;
    const CASE_ATTRIBUTE_OUTPUT = 4096;

    // CONSTANTS (2^5 - 2^8)
    const CONST_COLLECT = 32;
    const CONST_OUTPUT = 64;
    const CONST_ATTRIBUTE_COLLECT = 128;
    const CONST_ATTRIBUTE_OUTPUT = 256;

    // METHODS (2^15 - 2^21, 2^23 - 2^24)
    const METHOD_COLLECT = 32768;
    const METHOD_OUTPUT = 65536;
    const METHOD_DESC_OUTPUT = 524288;
    const METHOD_ATTRIBUTE_COLLECT = 131072;
    const METHOD_ATTRIBUTE_OUTPUT = 262144;
    const METHOD_STATIC_VAR_COLLECT = 8388608; // 2^23
    const METHOD_STATIC_VAR_OUTPUT = 16777216; // 2^24

    const OBJ_ATTRIBUTE_COLLECT = 4;
    const OBJ_ATTRIBUTE_OUTPUT = 8;

    const PARAM_ATTRIBUTE_COLLECT = 1048576;
    const PARAM_ATTRIBUTE_OUTPUT = 2097152; // 2^21

    const PHPDOC_COLLECT = 1; // 2^0
    const PHPDOC_OUTPUT = 2;

    // PROPERTIES (2^13 - 2^14)
    const PROP_ATTRIBUTE_COLLECT = 8192; // 2^13
    const PROP_ATTRIBUTE_OUTPUT = 16384; // 2^14
    const PROP_VIRTUAL_VALUE_COLLECT = 33554432; // 2^25

    const TO_STRING_OUTPUT = 16; // 2^4

    /** @var array<string,self::*> */
    public static $cfgFlags = array(
        'brief' => self::BRIEF,

        // CASE
        'caseAttributeCollect' => self::CASE_ATTRIBUTE_COLLECT,
        'caseAttributeOutput' => self::CASE_ATTRIBUTE_OUTPUT,
        'caseCollect' => self::CASE_COLLECT,
        'caseOutput' => self::CASE_OUTPUT,

        // CONSTANTS
        'constAttributeCollect' => self::CONST_ATTRIBUTE_COLLECT,
        'constAttributeOutput' => self::CONST_ATTRIBUTE_OUTPUT,
        'constCollect' => self::CONST_COLLECT,
        'constOutput' => self::CONST_OUTPUT,

        // METHODS
        'methodAttributeCollect' => self::METHOD_ATTRIBUTE_COLLECT,
        'methodAttributeOutput' => self::METHOD_ATTRIBUTE_OUTPUT,
        'methodCollect' => self::METHOD_COLLECT,
        'methodDescOutput' => self::METHOD_DESC_OUTPUT,
        'methodOutput' => self::METHOD_OUTPUT,
        'methodStaticVarCollect' => self::METHOD_STATIC_VAR_COLLECT,
        'methodStaticVarOutput' => self::METHOD_STATIC_VAR_OUTPUT,

        'objAttributeCollect' => self::OBJ_ATTRIBUTE_COLLECT,
        'objAttributeOutput' => self::OBJ_ATTRIBUTE_OUTPUT,

        'paramAttributeCollect' => self::PARAM_ATTRIBUTE_COLLECT,
        'paramAttributeOutput' => self::PARAM_ATTRIBUTE_OUTPUT,

        'phpDocCollect' => self::PHPDOC_COLLECT,
        'phpDocOutput' => self::PHPDOC_OUTPUT,

        // PROPERTIES
        'propAttributeCollect' => self::PROP_ATTRIBUTE_COLLECT,
        'propAttributeOutput' => self::PROP_ATTRIBUTE_OUTPUT,
        'propVirtualValueCollect' => self::PROP_VIRTUAL_VALUE_COLLECT,

        'toStringOutput' => self::TO_STRING_OUTPUT,
    );

    /** @var Abstracter */
    protected $abstracter;
    /** @var Constants */
    protected $constants;
    /** @var Debug */
    protected $debug;
    /** @var Definition */
    protected $definition;
    /** @var Helper */
    protected $helper;
    /** @var Methods */
    protected $methods;
    /** @var Properties */
    protected $properties;
    /** @var PropertiesInstance */
    protected $propertiesInstance;

    /** @var list<string> */
    protected $readOnly = [
        'abstracter',
        'constants',
        'debug',
        'definition',
        'helper',
        'methods',
        'properties',
        'propertiesInstance',
    ];

    /**
     * Default object abstraction values
     *
     *    stored separately:
     *      attributes
     *      cases  (enum cases)
     *      constants
     *      definition
     *         extensionName
     *         fileName
     *         startLine
     *      extends
     *      implements
     *      isFinal
     *      methods
     *      phpDoc
     *    see also Definition::$values
     *
     * @var array<string,mixed> Array of key/values
     */
    protected static $values = array(
        'cfgFlags' => 0, // will default to everything sans "brief" & 'virtualValueCollect'
        'className' => '',
        'debugMethod' => '',
        'interfacesCollapse' => array(),  // cfg.interfacesCollapse
        'isExcluded' => false,  // don't exclude if we're debugging directly
        'isLazy' => false,
        'isMaxDepth' => false,
        'isRecursion' => false,
        'keys' => array(),
        // methods may be populated with __toString info, or methods with staticVars
        'properties' => array(),
        'scopeClass' => '',
        'sectionOrder' => array(),  // cfg.objectSectionOrder
        'sort' => '',  // cfg.objectSort
        'stringified' => null,
        'traverseValues' => array(),  // populated if method is table
        'viaDebugInfo' => false,
    );

    /**
     * Constructor
     *
     * @param Abstracter $abstracter Abstracter instance
     */
    public function __construct(Abstracter $abstracter)
    {
        $this->abstracter = $abstracter;
        $this->debug = $abstracter->debug;
        $this->helper = new Helper($this->debug->phpDoc);
        $this->constants = new Constants($this);
        $this->methods = new Methods($this);
        $this->properties = new Properties($this);
        $this->propertiesInstance = new PropertiesInstance($this);
        $this->definition = new Definition($this);
        if ($abstracter->debug->parentInstance === null) {
            // we only need to subscribe to these events from root channel
            $subscriber = new Subscriber($this);
            $abstracter->debug->eventManager->addSubscriberInterface($subscriber);
        }
    }

    /**
     * Returns information about an object
     *
     * @param object|string $obj    Object (or classname) to inspect
     * @param string        $method Method requesting abstraction
     * @param array         $hist   (@internal) array & object history
     *
     * @return ObjectAbstraction
     * @throws RuntimeException
     */
    public function getAbstraction($obj, $method = null, array $hist = array())
    {
        $reflector = $this->debug->reflection->getReflector($obj);
        if ($reflector instanceof ReflectionEnumUnitCase) {
            $reflector = $reflector->getEnum();
        }
        $values = $this->getAbstractionValues($reflector, $obj, $method, $hist);
        $definitionValueStore = $this->definition->getAbstraction($obj, $values);
        $abs = new ObjectAbstraction($definitionValueStore, $values);
        $abs->setSubject($obj);
        $abs['hist'][] = $obj;
        $this->doAbstraction($abs);
        $abs->clean();
        return $abs;
    }

    /**
     * "Build" object abstraction values
     *
     * @param array<string,mixed> $values values to apply
     *
     * @return array<string,mixed>
     */
    public static function buildValues(array $values = array())
    {
        if (self::$values['cfgFlags'] === 0) {
            // calculate default cfgFlags (everything except for "brief" & virtualValueCollect)
            self::$values['cfgFlags'] = \array_reduce(self::$cfgFlags, static function ($carry, $val) {
                return $carry | $val;
            }, 0) & ~self::BRIEF & ~self::PROP_VIRTUAL_VALUE_COLLECT;
        }
        return \array_merge(self::$values, $values);
    }

    /**
     * Populate rows or columns (traverseValues) if we're outputting as a table
     *
     * @param ObjectAbstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addTraverseValues(ObjectAbstraction $abs)
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
     * Collect instance info
     * Property values & static method variables
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function doAbstraction(ObjectAbstraction $abs)
    {
        if ($abs['isMaxDepth'] || $abs['isRecursion']) {
            return;
        }
        $abs['isTraverseOnly'] = $this->helper->isTraverseOnly($abs);
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
        if ($abs['isTraverseOnly']) {
            $this->addTraverseValues($abs);
        }
        $this->methods->addInstance($abs);  // method static variables
        $this->propertiesInstance->add($abs);
        /*
            Debug::EVENT_OBJ_ABSTRACT_END subscriber has free reign to modify abstraction values
        */
        $this->debug->publishBubbleEvent(Debug::EVENT_OBJ_ABSTRACT_END, $abs, $this->debug);
    }

    /**
     * Get initial "top-level" values.
     *
     * @param ReflectionClass $reflector Reflection instance
     * @param object|string   $obj       Object (or classname) to inspect
     * @param string          $method    Method requesting abstraction
     * @param array           $hist      (@internal) array & object history
     *
     * @return array{reflector:ReflectionClass,...<string,mixed>}
     * @throws RuntimeException
     */
    protected function getAbstractionValues(ReflectionClass $reflector, $obj, $method = null, array $hist = array())
    {
        return \array_merge(
            self::$values,
            array(
                'cfgFlags' => $this->getCfgFlags(),
                'className' => $this->helper->getClassName($reflector),
                'debugMethod' => $method,
                'interfacesCollapse' => \array_values(\array_intersect($reflector->getInterfaceNames(), $this->cfg['interfacesCollapse'])),
                'isExcluded' => $hist && $this->isExcluded($obj),    // don't exclude if we're debugging directly
                'isLazy' => PHP_VERSION_ID >= 80400 && \is_object($obj) ? $reflector->isUninitializedLazyObject($obj) : false,
                'isMaxDepth' => $this->cfg['maxDepth'] && \count($hist) === $this->cfg['maxDepth'],
                'isRecursion' => \in_array($obj, $hist, true),
                'scopeClass' => $this->getScopeClass($hist),
                'sectionOrder' => $this->cfg['objectSectionOrder'],
                'sort' => $this->cfg['objectSort'],
                'viaDebugInfo' => $this->cfg['useDebugInfo'] && $reflector->hasMethod('__debugInfo'),
            ),
            array(
                // these are temporary values available during abstraction
                'collectPropertyValues' => true,
                'fullyQualifyPhpDocType' => $this->cfg['fullyQualifyPhpDocType'],
                'hist' => $hist,
                'isTraverseOnly' => false,
                'propertyOverrideValues' => array(),
                'reflector' => $reflector,
            )
        );
    }

    /**
     * Get configuration flags
     *
     * @return int bitmask
     */
    protected function getCfgFlags()
    {
        $flagVals = \array_intersect_key(self::$cfgFlags, \array_filter($this->cfg));
        // see Abstracter::__construct which sets initial/default cfgFlags cfg vales
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
    private function getScopeClass(array &$hist)
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
    private function isObjInList($obj, array $list)
    {
        $classname = \get_class($obj);
        if (\array_intersect(['*', $classname], $list)) {
            return true;
        }
        foreach ($list as $class) {
            if (\is_subclass_of($obj, $class)) {
                return true;
            }
        }
        return false;
    }
}
