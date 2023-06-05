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

/**
 * Object Abstraction
 */
class ObjectAbstraction extends Abstraction
{
    private $class;

    private $sortableValues = array('attributes', 'cases', 'constants', 'methods', 'properties');

    /**
     * Constructor
     *
     * @param ValueStore $class  class values
     * @param array      $values abtraction values
     */
    public function __construct(ValueStore $class, $values = array())
    {
        $this->class = $class;
        parent::__construct(Abstracter::TYPE_OBJECT, $values);
    }

    /**
     * {@inheritDoc}
     */
    public function __serialize()
    {
        return $this->getInstanceValues() + array('class' => $this->class);
    }

    /**
     * Return stringified value
     *
     * @return string
     */
    public function __toString()
    {
        $val = $this->offsetGet('className');
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
        $this->class = $data['class'];
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
        return $this->getInstanceValues() + array(
            'classDefinition' => $this->class['className'],
            'debug' => Abstracter::ABSTRACTION,
        );
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
        return $this->class->getValues();
    }

    /**
     * Get instance related values
     *
     * @return array
     */
    public function getInstanceValues()
    {
        return ArrayUtil::diffAssocRecursive(
            $this->values,
            $this->getClassValues()
        );
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
        $value = $this->getCombinedValue($key);
        if (\in_array($key, $this->sortableValues, true)) {
            $this->sort($value, $this->values['sort']);
        }
        // update our local and return it as a reference
        $this->values[$key] = $value;
        return $this->values[$key];
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
        $classVal = \in_array($key, $this->sortableValues, true)
            && ($this->values['isRecursion'] || $this->values['isExcluded'])
                ? array() // don't inherit
                : $this->class[$key];
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
