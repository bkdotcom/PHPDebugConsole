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
        'isFinal' => false,
        'isReadOnly' => false,
        'methods' => array(),
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
        $abs = $this->getAbstractionInit($info);
        $abs->setSubject($obj);
        $abs['fullyQualifyPhpDocType'] = $info['fullyQualifyPhpDocType'];
        $abs['reflector'] = $info['reflector'];

        $this->addDefinition($abs);
        $this->addAttributes($abs);
        $this->constants->add($abs);
        $this->constants->addCases($abs);
        $this->methods->add($abs);
        $this->properties->addClass($abs);
        if ($abs['className'] === 'Closure') {
            // __incoke is "unique" per instance
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
        if ($info['isAnonymous']) {
            if ($reflector->getParentClass() === false) {
                return $this->getValueStoreDefault();
            }
            $reflector = $reflector->getParentClass();
            $className = $reflector->getName();
            $info['reflector'] = $reflector;
        }
        $dataPath = array('classDefinitions', $className);
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
     * @param ValueStore $abs Object abstraction instance
     *
     * @return void
     */
    public function addAttributes(ValueStore $abs)
    {
        $reflector = $abs['reflector'];
        $abs['attributes'] = $abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_COLLECT
            ? $this->helper->getAttributes($reflector)
            : array();
    }

    /**
     * Collect "definition" values
     *
     * @param ValueStore $abs Object abstraction instance
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
     * Initialize class definition abstraction
     *
     * @param array $info values already collected
     *
     * @return Absttraction
     */
    protected function getAbstractionInit(array $info)
    {
        $reflector = $info['reflector'];
        $interfaceNames = $reflector->getInterfaceNames();
        \sort($interfaceNames);
        $values = \array_merge(
            self::$values,
            array(
                'cfgFlags' => $info['cfgFlags'],
                'className' => $reflector->getName(),
                'implements' => $interfaceNames,
                'isFinal' => $reflector->isFinal(),
                'isReadOnly' => PHP_VERSION_ID >= 80200 && $reflector->isReadOnly(),
                'phpDoc' => $this->helper->getPhpDoc($reflector, $info['fullyQualifyPhpDocType']),
            )
        );
        while ($reflector = $reflector->getParentClass()) {
            if ($values['phpDoc'] === array('desc' => null, 'summary' => null)) {
                $values['phpDoc'] = $this->helper->getPhpDoc($reflector, $info['fullyQualifyPhpDocType']);
            }
            $values['extends'][] = $reflector->getName();
        }
        unset($values['phpDoc']['method']);
        // magic properties removed via PropertiesPhpDoc::addViaPhpDocIter
        return new Abstraction(Abstracter::TYPE_OBJECT, $values);
    }
}