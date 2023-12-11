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
use bdk\Debug\Abstraction\Object\Subscriber;
use bdk\Debug\Utility\Reflection;
use ReflectionClass;
use ReflectionEnumUnitCase;
use RuntimeException;

/**
 * Abstracter:  Methods used to abstract objects
 */
class AbstractObject extends AbstractComponent
{
    // GENERAL
    const BRIEF = 4194304; // 2^22
    const PHPDOC_COLLECT = 1; // 2^0
    const PHPDOC_OUTPUT = 2;
    const OBJ_ATTRIBUTE_COLLECT = 4;
    const OBJ_ATTRIBUTE_OUTPUT = 8;
    const TO_STRING_OUTPUT = 16; // 2^4

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
    const METHOD_ATTRIBUTE_COLLECT = 131072;
    const METHOD_ATTRIBUTE_OUTPUT = 262144;
    const METHOD_DESC_OUTPUT = 524288;
    const METHOD_STATIC_VAR_COLLECT = 8388608; // 2^23
    const METHOD_STATIC_VAR_OUTPUT = 16777216; // 2^24
    const PARAM_ATTRIBUTE_COLLECT = 1048576;
    const PARAM_ATTRIBUTE_OUTPUT = 2097152;

    // PROPERTIES (2^13 - 2^14)
    const PROP_ATTRIBUTE_COLLECT = 8192; // 2^13
    const PROP_ATTRIBUTE_OUTPUT = 16384; // 2^14

    public static $cfgFlags = array(  // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        // GENERAL
        'brief' => self::BRIEF,
        'objAttributeCollect' => self::OBJ_ATTRIBUTE_COLLECT,
        'objAttributeOutput' => self::OBJ_ATTRIBUTE_OUTPUT,
        'phpDocCollect' => self::PHPDOC_COLLECT,
        'phpDocOutput' => self::PHPDOC_OUTPUT,
        'toStringOutput' => self::TO_STRING_OUTPUT,

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
        'paramAttributeCollect' => self::PARAM_ATTRIBUTE_COLLECT,
        'paramAttributeOutput' => self::PARAM_ATTRIBUTE_OUTPUT,

        // PROPERTIES
        'propAttributeCollect' => self::PROP_ATTRIBUTE_COLLECT,
        'propAttributeOutput' => self::PROP_ATTRIBUTE_OUTPUT,
    );

    protected $abstracter;
    protected $constants;
    protected $debug;
    protected $definition;
    protected $helper;
    protected $methods;
    protected $properties;
    protected $readOnly = array(
        'abstracter',
        'constants',
        'debug',
        'definition',
        'helper',
        'methods',
        'properties',
    );

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
     *
     * @var array Array of key/values
     */
    protected static $values = array(  // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        'type' => Abstracter::TYPE_OBJECT,
        'cfgFlags' => 0,
        'className' => '',
        'debugMethod' => '',
        'interfacesCollapse' => array(),  // cfg.interfacesCollapse
        'isExcluded' => false,  // don't exclude if we're debugging directly
        'isMaxDepth' => false,
        'isRecursion' => false,
        'sectionOrder' => array(),  // cfg.objectSectionOrder
        // methods may be populated with __toString info, or methods with staticVars
        'properties' => array(),
        'scopeClass' => '',
        'sort' => '',  // cfg.objectSort
        'stringified' => null,
        'traverseValues' => array(),  // populated if method is table
        'viaDebugInfo' => false,
    );

    protected static $keysTemp = array(
        'collectPropertyValues',
        'fullyQualifyPhpDocType',
        'hist',
        'isTraverseOnly',
        'propertyOverrideValues',
        'reflector',
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
        $this->definition = new Definition($this);
        if ($abstracter->debug->parentInstance) {
            // we only need to subscribe to these events from root channel
            return;
        }
        $subscriber = new Subscriber($this);
        $abstracter->debug->eventManager->addSubscriberInterface($subscriber);
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
        $reflector = Reflection::getReflector($obj);
        if ($reflector instanceof ReflectionEnumUnitCase) {
            $reflector = $reflector->getEnum();
        }
        $values = $this->getAbstractionValues($reflector, $obj, $method, $hist);
        $valueStore = $this->definition->getValueStore($obj, $values);
        $abs = new ObjectAbstraction($valueStore, $values);
        $abs->setSubject($obj);
        $abs['hist'][] = $obj;
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
    public static function buildObjValues(array $values = array())
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
     * Sort things and remove temporary values
     *
     * @param ObjectAbstraction $abs Abstraction instance
     *
     * @return void
     */
    private function absClean(ObjectAbstraction $abs)
    {
        $values = \array_diff_key($abs->getInstanceValues(), \array_flip(self::$keysTemp));
        $abs
            ->setSubject(null)
            ->setValues($values);
    }

    /**
     * Populate rows or columns (traverseValues) if we're outputing as a table
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
     * Add attributes, constants, properties, methods, constants, etc
     *
     * @param ObjectAbstraction $abs Abstraction instance
     *
     * @return void
     */
    private function doAbstraction(ObjectAbstraction $abs)
    {
        if ($abs['isMaxDepth']) {
            return;
        }
        if ($abs['isRecursion']) {
            return;
        }
        $abs['isTraverseOnly'] = $this->isTraverseOnly($abs);
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
        $this->properties->addInstance($abs);
        /*
            Debug::EVENT_OBJ_ABSTRACT_END subscriber has free reign to modify abtraction values
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
     * @return ObjectAbstraction
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
     * @param ObjectAbstraction $abs Abstraction instance
     *
     * @return bool
     */
    private function isTraverseOnly(ObjectAbstraction $abs)
    {
        if ($abs['debugMethod'] === 'table' && \count($abs['hist']) < 4) {
            $abs['cfgFlags'] &= ~self::CONST_COLLECT;  // set collect constants to "false"
            $abs['cfgFlags'] &= ~self::METHOD_COLLECT;  // set collect methods to "false"
            return true;
        }
        return false;
    }
}
