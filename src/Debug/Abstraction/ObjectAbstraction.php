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

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Utility\ArrayUtil;
use bdk\PubSub\ValueStore;
use Exception;

/**
 * Object Abstraction
 */
class ObjectAbstraction extends Abstraction
{
    const EDIT_CLASS = 'class';
    const EDIT_INSTANCE = 'instance';
    const EDIT_OFF = 'off';

    private $class;
    private $editMode = self::EDIT_INSTANCE;

    private $sortableValues = array('attributes', 'cases', 'constants', 'methods', 'properties');

    /**
     * Constructor
     *
     * @param array $values abtraction values
     */
    public function __construct($values = array())
    {
        parent::__construct(Abstracter::TYPE_OBJECT, $values);
    }

    /**
     * {@inheritDoc}
     */
    public function __serialize()
    {
        return $this->values + array('class' => $this->class);
    }

    /**
     * Return stringified value
     *
     * @return string
     */
    public function __toString()
    {
        $val = $this->values['className'];
        if ($this->values['stringified']) {
            $val = $this->values['stringified'];
        } elseif (isset($this->values['methods']['__toString']['returnValue'])) {
            $val = $this->values['methods']['__toString']['returnValue'];
        }
        return (string) $val;
    }

    /**
     * {@inheritDoc}
     */
    public function __unserialize($data)
    {
        $this->class = isset($data['class'])
            ? $data['class']
            : null;
        unset($data['class']);
        $this->values = $data;
    }

    /**
     * Implements JsonSerializable
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->values + array(
            'classDefinition' => $this->class['className'],
            'debug' => Abstracter::ABSTRACTION,
        );
    }


    /**
     * Set class value instance
     *
     * @param ValueStore $class ValueStore instance for storing/retrieving class values
     *
     * @return void
     */
    public function setClass(ValueStore $class)
    {
        $this->class = $class;
    }

    /**
     * Modify where setValues, setValue, offsetSet write values
     * Modify how offsetGet returns
     *
     * @param EDIT_x $mode One of the EDIT_x constants
     *
     * @return void
     *
     * @throws Exception
     */
    public function setEditMode($mode = self::EDIT_INSTANCE)
    {
        if ($mode === self::EDIT_CLASS && $this->class === null) {
            throw new Exception(\sprintf('setEditMode(%s) requires that setClass be called first'));
        }
        $this->editMode = $mode;
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        return \array_replace_recursive(
            $this->getClassValues(),
            $this->values
        );
    }

    /**
     * Get class related values
     *
     * @return array
     */
    public function getClassValues()
    {
        return $this->class
            ? $this->class->getValues()
            : array();
    }

    /**
     * Get instance related values
     *
     * @return array
     */
    public function getInstanceValues()
    {
        return $this->values;
    }

    /**
     * {@inheritDoc}
     */
    public function setValues(array $values = array())
    {
        if ($this->editMode === self::EDIT_CLASS) {
            $this->class->setValues($values);
            return $this;
        }
        $values = ArrayUtil::diffAssocRecursive(
            $values,
            $this->getClassValues()
        );
        return parent::setValues($values);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($key)
    {
        if (\array_key_exists($key, $this->values)) {
            return $this->values[$key] !== null;
        }
        return isset($this->class[$key]);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($key)
    {
        if ($this->editMode === self::EDIT_CLASS && $this->class->hasValue($key)) {
            $val =& $this->class[$key];
            return $val;
        }
        $value = $this->getCombinedValue($key);
        if (\in_array($key, $this->sortableValues, true)) {
            $this->sort($value, $this->values['sort']);
        }
        if ($this->editMode === self::EDIT_INSTANCE) {
            // update our local and return it as a reference
            $this->values[$key] = $value;
            return $this->values[$key];
        }
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($key, $value)
    {
        if ($this->editMode === self::EDIT_CLASS) {
            $this->class[$key] = $value;
            return;
        }
        parent::offsetSet($key, $value);
    }

    /**
     * Get merged class & instance value
     *
     * @param string $key Value key
     *
     * @return mixed
     */
    private function getCombinedValue($key)
    {
        $value = isset($this->values[$key])
            ? $this->values[$key]
            : null;
        $classVal = null;
        if (
            \in_array($key, $this->sortableValues, true)
            && ($this->values['isRecursion'] || $this->values['isExcluded'])
        ) {
            // don't inherit
            $classVal = array();
        } elseif (isset($this->class)) {
            $classVal = $this->class[$key];
        }
        if ($value !== null) {
            return \is_array($classVal)
                ? \array_replace_recursive($classVal, $value)
                : $value;
        }
        return $classVal;
    }

    /**
     * Sorts constant/property/method array by visibility or name
     *
     * @param array  $array array to sort
     * @param string $order ("visibility")|"name"
     *
     * @return void
     */
    private function sort(&$array, $order = 'visibility')
    {
        $sortVisOrder = array('public', 'magic', 'magic-read', 'magic-write', 'protected', 'private', 'debug');
        $sortData = array(
            'name' => array(),
            'vis' => array(),
        );
        foreach ($array as $name => $info) {
            if ($name === '__construct') {
                // always place __construct at the top
                $sortData['name'][$name] = 0;
                $sortData['vis'][$name] = 0;
                continue;
            }
            $vis = isset($info['visibility'])
                ? $info['visibility']
                : '?';
            if (\is_array($vis)) {
                // Sort the visiblity so we use the most significant vis
                ArrayUtil::sortWithOrder($vis, $sortVisOrder);
                $vis = $vis[0];
            }
            $sortData['name'][$name] = \strtolower($name);
            $sortData['vis'][$name] = \array_search($vis, $sortVisOrder, true);
        }
        if ($order === 'name') {
            \array_multisort($sortData['name'], $array);
        } elseif ($order === 'visibility') {
            \array_multisort($sortData['vis'], $sortData['name'], $array);
        }
    }
}
