<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
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
        return \array_merge(self::$values, $values);
    }

    /**
     * Get class ValueStore obj
     *
     * @param object $obj    Object being abstracted
     * @param array  $values Instance values
     *
     * @return ValueStore
     */
    public function getAbstraction($obj, array $values)
    {
        $className = $values['className'];
        $reflector = $values['reflector'];
        $valueStoreKey = PHP_VERSION_ID >= 70000 && $reflector->isAnonymous()
            ? $className . '|' . \md5($reflector->getName())
            : $className;
        $dataPath = ['classDefinitions', $valueStoreKey];
        $valueStore = $this->debug->data->get($dataPath);
        if ($valueStore) {
            return $valueStore;
        }
        if (\array_filter([$values['isMaxDepth'], $values['isExcluded']])) {
            return $this->getValueStoreDefault();
        }
        $abs = new ObjectAbstraction($this->getValueStoreDefault(), $this->getInitValues($values));
        $abs->setSubject($obj);
        $this->debug->data->set($dataPath, $abs);
        $this->doAbstraction($abs);
        unset($abs['debugMethod']);
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
        $values = self::buildValues(array(
            'isExcluded' => false,
            'sectionOrder' => $this->object->getCfg('objectSectionOrder'),
            'sort' => $this->object->getCfg('objectSort'),
            'stringified' => null,
            'traverseValues' => array(),
            'viaDebugInfo' => false,
        ));
        $classValueStore = new ValueStore($values);
        $this->default = $classValueStore;
        $this->debug->data->set([
            'classDefinitions',
            $values['className'], // "\x00default\x00"
        ], $classValueStore);
        return $classValueStore;
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
     * Initialize class definition abstraction
     *
     * @param array $values values already collected
     *
     * @return Abstraction
     */
    protected function getInitValues(array $values)
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
