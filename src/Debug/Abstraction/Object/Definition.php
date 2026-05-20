<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.4
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Object\Constants;
use bdk\Debug\Abstraction\Object\Helper;
use bdk\Debug\Abstraction\Object\Methods;
use bdk\Debug\Abstraction\Object\Properties;
use bdk\PubSub\ValueStore;
use ReflectionClass;

/**
 * Abstracter: Gather class definition info common across all instances
 */
class Definition
{
    /** @var Constants */
    protected $constants;

    /** @var Debug */
    protected $debug;

    /** @var Helper */
    protected $helper;

    /** @var Methods */
    protected $methods;

    /** @var AbstractObject */
    protected $object;

    /** @var Properties */
    protected $properties;

    /** @var ValueStore|null base/default class values */
    protected $default;

    /**
     * @var array<string,mixed> Array of key/values
     */
    protected static $values = array(
        'attributes' => array(),
        'cases' => array(),
        'cfgFlags' => 0, // __constructor will set to everything sans "brief" and "propVirtualValueCollect"
                         // definition will collect with all options
        'className' => "\x00default\x00",
        'constants' => array(),
        'definition' => array(
            'extensionName' => false,
            'fileName' => false,
            'startLine' => false,
        ),
        'extends' => array(),
        'implements' => array(),
        'isAbstract' => false,
        'isAnonymous' => false,
        'isFinal' => false,
        'isInterface' => false,
        'isReadOnly' => false,
        'isTrait' => false,
        'methods' => array(),
        'methodsWithStaticVars' => array(),
        'phpDoc' => array(
            'desc' => '',
            'summary' => '',
        ),
        'properties' => array(),
    );

    /**
     * Constructor
     *
     * @param AbstractObject $abstractObject Object abstracter
     */
    public function __construct(AbstractObject $abstractObject)
    {
        $this->debug = $abstractObject->debug;
        $this->object = $abstractObject;
        $this->helper = $abstractObject->helper;
        $this->constants = $abstractObject->constants;
        $this->methods = $abstractObject->methods;
        $this->properties = $abstractObject->properties;

        $defaultValues = $abstractObject->buildValues();
        self::$values['cfgFlags'] = $defaultValues['cfgFlags'];
    }

    /**
     * "Build" object definition values
     *
     * @param array<string,mixed> $values values to apply
     *
     * @return array<string,mixed>
     */
    public static function buildValues(array $values = array())
    {
        $values = \array_merge(self::$values, $values);
        \ksort($values);
        return $values;
    }

    /**
     * Get class ValueStore obj
     *
     * @param object|class-string $obj    Object being abstracted
     * @param array               $values Instance values
     *
     * @return ValueStore
     */
    public function getAbstraction($obj, array $values = array())
    {
        $values = $this->getValuesBootstrap($obj, $values);
        $keys = $this->getCacheKeys($values);

        $valueStore = $this->getAbstractionFromDataCache($keys['dataPath'], $keys['cache']);
        if ($valueStore) {
            return $valueStore;
        }

        $valueStoreDefault = $this->getValueStoreDefault();
        if (\array_filter([$obj === self::$values['className'], $values['isMaxDepth'], $values['isExcluded']])) {
            return $valueStoreDefault;
        }

        $abs = new ObjectAbstraction($valueStoreDefault, $this->getValuesInit($values));
        $abs->setSubject($obj);
        $this->debug->data->set($keys['dataPath'], $abs); // set early to allow recursive references to work
        $this->doAbstraction($abs);

        if ($abs['isAnonymous'] === false) {
            $abs['caching'] = true; // affects Abstraction's __serialize behavior (__serialize removes this value)
            $this->debug->cache->set($keys['cache'], $abs);
        }
        return $abs;
    }

    /**
     * Get empty class definition
     *
     * @return ValueStore
     */
    public function getValueStoreDefault()
    {
        if ($this->default) {
            return $this->default;
        }
        $values = $this->object->buildValues(static::buildValues());
        $this->default = new ValueStore($values);
        $dataPath = ['classDefinitions', $values['className']];
        $this->debug->data->set($dataPath, $this->default);
        return $this->default;
    }

    /**
     * Mark a definition as used (referenced by a logged ObjectAbstraction)
     *
     * @param ValueStore $valueStore The definition ValueStore
     *
     * @return void
     */
    public function markAsUsed(ValueStore $valueStore)
    {
        if ($valueStore['__isUsed']) {
            return; // already marked
        }
        $valueStore['__isUsed'] = true;
        if ($valueStore['inherited']) {
            // also mark "parent" definition as used
            $this->markAsUsed($valueStore['inherited']);
        }
    }

    /**
     * Collect class attributes
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    protected function addAttributes(ValueStore $abs)
    {
        // perform cfgFlag check even though we've enabled all flags for definition
        if ($abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_COLLECT) {
            $reflector = $abs['reflector'];
            $abs['attributes'] = $this->helper->getAttributes($reflector);
        }
    }

    /**
     * Collect "definition" values
     *
     * extensionName, fileName, & startLine
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    protected function addDefinition(ValueStore $abs)
    {
        $reflector = $abs['reflector'];
        $abs['definition'] = array(
            // note that for a Closure object, this likely isn't the info we want...
            //   AbstractObjectProperties::addClosure will will set the instance definition info
            'extensionName' => $reflector->getExtensionName(),
            'fileName' => $reflector->getFileName(),
            'startLine' => $reflector->getStartLine(),
        );
    }

    /**
     * Collect classes this class extends
     *
     * If interface, collect ancestor interfaces as a tree.
     * ReflectionClass::getParentClass() doesn't work for interfaces
     * as interfaces can extend multiple interfaces
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    protected function addExtends(ValueStore $abs)
    {
        if ($abs['isInterface']) {
            // interfaces can EXTEND multiple interfaces
            $abs['extends'] = $this->getInterfaces($abs['reflector']);
            return;
        }
        $reflector = $abs['reflector'];
        $extends = array();
        while ($reflector = $reflector->getParentClass()) {
            $extends[] = $reflector->getName();
        }
        $abs['extends'] = $extends;
    }

    /**
     * Collect interfaces that class implements
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    protected function addImplements(ValueStore $abs)
    {
        $abs['implements'] = $abs['isInterface']
            ? array()
            : $this->getInterfaces($abs['reflector']);
    }

    /**
     * Collect phpDoc summary/description/params
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    protected function addPhpDoc(ValueStore $abs)
    {
        $reflector = $abs['reflector'];
        $fullyQualifyType = $abs['fullyQualifyPhpDocType'];
        $phpDoc = $this->helper->getPhpDoc($reflector, $fullyQualifyType);
        while (
            ($reflector = $reflector->getParentClass())
            && $phpDoc === array('desc' => '', 'summary' => '')
        ) {
            $phpDoc = $this->helper->getPhpDoc($reflector, $fullyQualifyType);
        }
        unset($phpDoc['method']);
        // magic properties removed via PropertiesPhpDoc::addViaPhpDocIter
        $abs['phpDoc'] = $phpDoc;
    }

    /**
     * Collect runtime info
     * attributes, constants, properties, methods, etc
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    protected function doAbstraction(ObjectAbstraction $abs)
    {
        $this->addAttributes($abs);
        $this->addDefinition($abs);
        $this->addExtends($abs);
        $this->addImplements($abs);
        $this->addPhpDoc($abs);
        $this->constants->add($abs);
        $this->constants->addCases($abs);
        $this->methods->add($abs);
        $this->properties->add($abs);

        if ($abs['className'] === 'Closure') {
            // __invoke is "unique" per instance
            $abs['methods']['__invoke'] = array();
        }

        $abs->clean();
    }

    /**
     * Check if definition already stored in data or cache
     *
     * @param array  $dataPath Path to definition in data
     * @param string $cacheKey Cache key for definition
     *
     * @return ObjectAbstraction|null
     */
    protected function getAbstractionFromDataCache(array $dataPath, $cacheKey)
    {
        $valueStore = $this->debug->data->get($dataPath);
        if ($valueStore) {
            return $valueStore;
        }
        $valueStore = $this->debug->cache->get($cacheKey);
        if ($valueStore) {
            // pulled from persistent cache
            // make sure we have the default valueStore in data
            if ($valueStore['inheritsFrom']) {
                $inherited = $this->getAbstraction($valueStore['inheritsFrom']);
                $valueStore->setInherited($inherited);
            }
            // store in data to keep track of which definitions are used in current request
            $this->debug->data->set($dataPath, $valueStore);
        }
        return $valueStore;
    }

    /**
     * Get data-path and cache key
     *
     * @param array $values Values to build keys from
     *
     * @return array<string,mixed>
     */
    private function getCacheKeys(array $values)
    {
        $className = $values['className'];
        $reflector = $values['reflector'];
        $valueStoreKey = PHP_VERSION_ID >= 70000 && $reflector && $reflector->isAnonymous()
            ? $className . '|' . \md5($reflector->getName())
            : $className;
        $cacheKey = \str_replace("\x00", 'x00', $valueStoreKey);
        $cacheKey = 'classDefinition_' . \preg_replace('/[{}()\\/\\\\@:]/', '_', $cacheKey);
        return array(
            'cache' => $cacheKey,
            'dataPath' => ['classDefinitions', $valueStoreKey],
        );
    }

    /**
     * Get a structured interface tree
     *
     * @param ReflectionClass $reflector ReflectionClass
     *
     * @return array
     */
    protected function getInterfaces(ReflectionClass $reflector)
    {
        $interfaces = array();
        $remove = [];
        foreach ($reflector->getInterfaces() as $classname => $refClass) {
            if (\in_array($classname, $remove, true)) {
                continue;
            }
            $extends = $refClass->getInterfaceNames();
            if ($extends) {
                $interfaces[$classname] = $this->getInterfaces($refClass);
                $remove = \array_merge($remove, $extends);
                continue;
            }
            $interfaces[] = $classname;
        }
        $remove = \array_unique($remove);
        $interfaces = \array_diff_key($interfaces, \array_flip($remove));
        return $this->debug->arrayUtil->diffStrict($interfaces, $remove);
    }

    /**
     * Get the values needed by getAbstraction()
     *
     * @param object|class-string $obj    Object or class being abstracted
     * @param array               $values Values passed to getAbstraction()
     *
     * @return array
     */
    private function getValuesBootstrap($obj, array $values)
    {
        $values = \array_merge(array(
            'className' => \is_object($obj)
                ? \get_class($obj)
                : (string) $obj,
            'debugMethod' => null,
            'fullyQualifyPhpDocType' => false,
            'isExcluded' => false,
            'isMaxDepth' => false,
            'reflector' => null,
        ), $values);
        if ($values['reflector'] === null && $values['className'] !== self::$values['className']) {
            $values['reflector'] = new ReflectionClass($values['className']);
        }
        return $values;
    }

    /**
     * Initialize class definition abstraction
     *
     * @param array $values values already collected
     *
     * @return Abstraction
     */
    private function getValuesInit(array $values)
    {
        $reflector = $values['reflector'];
        $isAnonymous = PHP_VERSION_ID >= 70000 && $reflector->isAnonymous();
        return self::buildValues(\array_merge(
            array(
                'cfgFlags' => self::$values['cfgFlags'],
                'className' => $isAnonymous
                    ? $values['className'] . '|' . \md5($reflector->getName())
                    : $values['className'],
                'isAbstract' => $reflector->isAbstract(),
                'isAnonymous' => $isAnonymous,
                'isFinal' => $reflector->isFinal(),
                'isInterface' => $reflector->isInterface(),
                'isReadOnly' => PHP_VERSION_ID >= 80200 && $reflector->isReadOnly(),
                'isTrait' => $reflector->isTrait(),
            ),
            array(
                // these are temporary values available during abstraction
                'debugMethod' => $values['debugMethod'],
                'fullyQualifyPhpDocType' => $values['fullyQualifyPhpDocType'],
                'hist' => array(),
                'reflector' => $values['reflector'],
            )
        ));
    }
}
