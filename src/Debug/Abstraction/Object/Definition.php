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
use bdk\PubSub\ValueStore;
use ReflectionClass;

/**
 * Abstracter: Gather class definition info common across all instances
 */
class Definition
{
    protected $constants;
    protected $debug;
    protected $helper;
    protected $methods;
    protected $properties;

    /** @var ValueStore|null base/default class values */
    protected static $default;

    /**
     * @var array Array of key/values
     */
    protected static $values = array(
        'attributes' => array(),
        'cases' => array(),
        'cfgFlags' => 0,
        'className' => "\x00default\x00",
        'constants' => array(),
        'definition' => array(
            'extensionName' => '',
            'fileName' => '',
            'startLine' => 1,
        ),
        'extends' => array(),
        'implements' => array(),
        'isAnonymous' => false,
        'isFinal' => false,
        'isReadOnly' => false,
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
        $this->helper = $abstractObject->helper;
        $this->constants = $abstractObject->constants;
        $this->methods = $abstractObject->methods;
        $this->properties = $abstractObject->properties;
    }

    /**
     * Get class abstraction
     *
     * @param object $obj  Object being abstracted
     * @param array  $info values already collected
     *
     * @return Abstraction
     */
    public function getAbstraction($obj, array $info = array())
    {
        $abs = new Abstraction(Abstracter::TYPE_OBJECT, $this->getInitValues($info));
        $abs->setSubject($obj);
        $abs['fullyQualifyPhpDocType'] = $info['fullyQualifyPhpDocType'];
        $abs['reflector'] = $info['reflector'];

        $this->addAttributes($abs);
        $this->addDefinition($abs);
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

        unset($abs['fullyQualifyPhpDocType']);
        unset($abs['reflector']);
        return $abs;
    }

    /**
     * Get class ValueStore obj
     *
     * @param object $obj  Object being abstracted
     * @param array  $info Instance abstraction info
     *
     * @return ValueStore
     */
    public function getValueStore($obj, array $info)
    {
        $className = $info['className'];
        $reflector = $info['reflector'];
        $valueStoreKey = PHP_VERSION_ID >= 70000 && $reflector->isAnonymous()
            ? $className . '|' . \md5($reflector->getName())
            : $className;
        $dataPath = array('classDefinitions', $valueStoreKey);
        $valueStore = $this->debug->data->get($dataPath);
        if ($valueStore) {
            return $valueStore;
        }
        if (\array_filter(array($info['isMaxDepth'], $info['isExcluded']))) {
            return $this->getValueStoreDefault();
        }
        $valueStore = new ValueStore();
        $this->debug->data->set($dataPath, $valueStore);
        $classAbs = $this->getAbstraction($obj, $info);
        $valueStore->setValues($classAbs->getValues());
        unset($valueStore['type']);
        return $valueStore;
    }

    /**
     * Get empty class definition
     *
     * @return ValueStore
     */
    public function getValueStoreDefault()
    {
        if (self::$default) {
            return self::$default;
        }
        $key = self::$values['className'];
        $classValueStore = new ValueStore(self::$values);
        self::$default = $classValueStore;
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
    public function addAttributes(ValueStore $abs)
    {
        if ($abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_COLLECT) {
            $reflector = $abs['reflector'];
            $abs['attributes'] = $this->helper->getAttributes($reflector);
        }
    }

    /**
     * Collect "definition" values
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    public function addDefinition(ValueStore $abs)
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
     * Collect interfaces that object implements
     *
     * @param ValueStore $abs ValueStore instance
     *
     * @return void
     */
    public function addImplements(ValueStore $abs)
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
    public function addPhpDoc(ValueStore $abs)
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
     * @param array $info values already collected
     *
     * @return Absttraction
     */
    protected function getInitValues(array $info)
    {
        $reflector = $info['reflector'];
        $isAnonymous = PHP_VERSION_ID >= 70000 && $reflector->isAnonymous();
        $values = \array_merge(
            self::$values,
            array(
                'cfgFlags' => $info['cfgFlags'],
                'className' => $isAnonymous
                    ? $info['className'] . '|' . \md5($reflector->getName())
                    : $info['className'],
                'isAnonymous' => $isAnonymous,
                'isFinal' => $reflector->isFinal(),
                'isReadOnly' => PHP_VERSION_ID >= 80200 && $reflector->isReadOnly(),
            )
        );
        while ($reflector = $reflector->getParentClass()) {
            $values['extends'][] = $reflector->getName();
        }
        return $values;
    }
}
