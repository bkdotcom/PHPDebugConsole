<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
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
use ReflectionEnum;

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
        'cfgFlags' => 0, // __constructor will set to everything sans "brief"
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
            'desc' => null,
            'summary' => null,
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

        $defaultValues = $abstractObject->buildObjValues();
        self::$values['cfgFlags'] = $defaultValues['cfgFlags'];
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
        $dataPath = array('classDefinitions', $valueStoreKey);
        $valueStore = $this->debug->data->get($dataPath);
        if ($valueStore) {
            return $valueStore;
        }
        if (\array_filter(array($values['isMaxDepth'], $values['isExcluded']))) {
            return $this->getValueStoreDefault();
        }
        $abs = new ObjectAbstraction($this->getValueStoreDefault(), $this->getInitValues($values));
        $abs->setSubject($obj);
        $this->doAbstraction($abs);
        $this->debug->data->set($dataPath, $abs);
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
        $key = self::$values['className'];
        $classValueStore = new ValueStore(\array_merge(
            self::$values,
            array(
                'isExcluded' => false,
                'sectionOrder' => $this->object->getCfg('objectSectionOrder'),
                'sort' => $this->object->getCfg('objectSort'),
                'stringified' => null,
                'traverseValues' => array(),
                'viaDebugInfo' => false,
            )
        ));
        $this->default = $classValueStore;
        $this->debug->data->set(array(
            'classDefinitions',
            $key,
        ), $classValueStore);
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
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    protected function addExtends(ValueStore $abs)
    {
        $reflector = $abs['reflector'];
        $extends = array();
        while ($reflector = $reflector->getParentClass()) {
            $extends[] = $reflector->getName();
        }
        $abs['extends'] = $extends;
    }

    /**
     * Collect interfaces that object implements
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    protected function addImplements(ValueStore $abs)
    {
        $abs['implements'] = $this->getInterfaces($abs['reflector']);
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
            && $phpDoc === array('desc' => null, 'summary' => null)
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
        $remove = array();
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
        // remove values... array_diff complains about array to string conversion
        foreach ($remove as $classname) {
            $key = \array_search($classname, $interfaces, true);
            if ($key !== false) {
                unset($interfaces[$key]);
            }
        }
        return $interfaces;
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
        return \array_merge(
            self::$values,
            array(
                'cfgFlags' => $reflector instanceof ReflectionEnum
                    ? $values['cfgFlags'] // avoid enum value recursion (cfgFlags based on current config)
                    : self::$values['cfgFlags'], // all options / collect everything
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
                'fullyQualifyPhpDocType' => $values['fullyQualifyPhpDocType'],
                'hist' => array(),
                'reflector' => $values['reflector'],
            )
        );
    }
}
