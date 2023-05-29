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

use bdk\Debug\Abstraction\ObjectAbstraction;
use bdk\PubSub\ValueStore;

/**
 * Abstracter: Gather class definition info common across all instances
 */
class AbstractObjectClass
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
     * Collect class info
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    public function add(ObjectAbstraction $abs)
    {
        $className = $abs['className'];
        if ($abs['isAnonymous']) {
            if ($abs['reflector']->getParentClass() === false) {
                $abs->setClass($this->getDefault());
                return;
            }
            $className = $abs['reflector']->getParentClass()->getName();
        }
        $dataPath = array(
            'classDefinitions',
            $className,
        );
        $classValueStore = $this->debug->data->get($dataPath);
        if ($classValueStore) {
            $abs->setClass($classValueStore);
            return;
        }
        if ($abs['isMaxDepth'] || $abs['isExcluded']) {
            $abs->setClass($this->getDefault());
            return;
        }
        $classValueStore = $this->doClass($abs);
        $this->debug->data->set($dataPath, $classValueStore);
    }

    /**
     * Collect class attributes
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    public function addAttributes(ObjectAbstraction $abs)
    {
        $reflector = $abs['reflector'];
        $abs['attributes'] = $abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_COLLECT
            ? $this->helper->getAttributes($reflector)
            : array();
    }

    /**
     * Collect "definition" info
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    public function addDefinition(ObjectAbstraction $abs)
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
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return ValueStore
     */
    private function doClass(ObjectAbstraction $abs)
    {
        $reflectorOrig = $abs['reflector'];
        if ($abs['isAnonymous']) {
            $abs['reflector'] = $abs['reflector']->getParentClass();
        }
        $initValues = $this->doClassInitValues($abs);
        $classValueStore = new ValueStore($initValues);
        $abs->setClass($classValueStore);
        $abs->setEditMode(ObjectAbstraction::EDIT_CLASS);
        $this->addDefinition($abs);
        $this->addAttributes($abs);
        $this->constants->addCases($abs);
        $this->constants->add($abs);
        $this->methods->add($abs);
        $this->properties->addClass($abs);
        if ($abs['className'] === 'Closure') {
            // __incoke us "unique" per instance
            $abs['methods']['__invoke'] = array();
        }
        $abs->setEditMode(ObjectAbstraction::EDIT_INSTANCE);
        $abs['reflector'] = $reflectorOrig;
        return $classValueStore;
    }

    /**
     * Get initial class values
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return array
     */
    private function doClassInitValues(ObjectAbstraction $abs)
    {
        $reflector = $abs['reflector'];
        $interfaceNames = $reflector->getInterfaceNames();
        \sort($interfaceNames);
        $values = \array_merge(self::$values, array(
            'className' => $reflector->getName(),
            'implements' => $interfaceNames,
            'isFinal' => $reflector->isFinal(),
            'phpDoc' => $this->helper->getPhpDoc($reflector),
        ));
        while ($reflector = $reflector->getParentClass()) {
            if ($values['phpDoc'] === array('desc' => null, 'summary' => null)) {
                $values['phpDoc'] = $this->helper->getPhpDoc($reflector);
            }
            $values['extends'][] = $reflector->getName();
        }
        unset($values['phpDoc']['method']);
        // magic properties removed via AbstractObjectProperties::addViaPhpDocIter
        return $values;
    }

    /**
     * Get empty class definition
     *
     * @return ValueStore
     */
    private function getDefault()
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
}
