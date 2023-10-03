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
use bdk\Debug\Abstraction\Abstraction as BaseAbstraction;
use bdk\Debug\Utility\ArrayUtil;
use bdk\PubSub\ValueStore;

/**
 * Object Abstraction
 */
class Abstraction extends BaseAbstraction
{
    private $definition;

    private $sortableValues = array('attributes', 'cases', 'constants', 'methods', 'properties');

    /**
     * Constructor
     *
     * @param ValueStore $definition class definition values
     * @param array      $values     abtraction values
     */
    public function __construct(ValueStore $definition, $values = array())
    {
        $this->definition = $definition;
        parent::__construct(Abstracter::TYPE_OBJECT, $values);
    }

    /**
     * {@inheritDoc}
     */
    public function __serialize()
    {
        return $this->getInstanceValues() + array('classDefinition' => $this->definition);
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
        $this->definition = isset($data['classDefinition'])
            ? $data['classDefinition']
            : new ValueStore();
        unset($data['classDefinition']);
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
            'classDefinition' => $this->definition['className'],
            'debug' => Abstracter::ABSTRACTION,
        );
    }

    /**
     * Get class related values
     *
     * @return array
     */
    public function getDefinitionValues()
    {
        return $this->definition->getValues();
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        return \array_replace_recursive(
            $this->getDefinitionValues(),
            $this->values
        );
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
            $this->getDefinitionValues()
        );
    }

    /**
     * Sorts constant/property/method array by visibility or name
     *
     * @param array  $array array to sort
     * @param string $order ("visibility")|"name"
     *
     * @return array
     */
    public function sort(array $array, $order = 'visibility')
    {
        $sortVisOrder = array('public', 'magic', 'magic-read', 'magic-write', 'protected', 'private', 'debug');
        $sortData = array(
            'name' => array(),
            'vis' => array(),
        );
        foreach ($array as $name => $info) {
            if ($name === '__construct') {
                // always place __construct at the top
                $sortData['name'][$name] = -1;
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
        return $array;
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
        return isset($this->definition[$key]);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($key)
    {
        // update our local and return it as a reference
        $this->values[$key] = $this->getCombinedValue($key);
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
                : $this->definition[$key];
        if ($value !== null) {
            return \is_array($classVal)
                ? \array_replace_recursive($classVal, $value)
                : $value;
        }
        return $classVal;
    }
}
