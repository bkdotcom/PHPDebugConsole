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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction as BaseAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Utility\ArrayUtil;
use bdk\PubSub\ValueStore;

/**
 * Object Abstraction
 */
class Abstraction extends BaseAbstraction
{
    /** @var list<string> */
    protected static $keysTemp = [
        'collectPropertyValues',
        'fullyQualifyPhpDocType',
        'hist',
        'isTraverseOnly',
        'propertyOverrideValues',
        'reflector',
    ];

    /** @var ValueStore */
    private $inherited;

    /** @var list<string> */
    private $sortableValues = ['attributes', 'cases', 'constants', 'methods', 'properties'];

    /**
     * Constructor
     *
     * @param ValueStore $inherited Inherited values
     * @param array      $values    Abstraction values
     */
    public function __construct(ValueStore $inherited, $values = array())
    {
        $this->inherited = $inherited;
        parent::__construct(Type::TYPE_OBJECT, $values);
    }

    /**
     * {@inheritDoc}
     */
    public function __serialize()
    {
        return $this->getInstanceValues() + array('inherited' => $this->inherited);
    }

    /**
     * Return stringified value
     *
     * @return string
     */
    public function __toString()
    {
        if (!empty($this->values['stringified'])) {
            return (string) $this->values['stringified'];
        }
        if (isset($this->values['methods']['__toString']['returnValue'])) {
            return (string) $this->values['methods']['__toString']['returnValue'];
        }
        return (string) $this->offsetGet('className');
    }

    /**
     * {@inheritDoc}
     */
    public function __unserialize(array $data)
    {
        $this->inherited = isset($data['inherited'])
            ? $data['inherited']
            : new ValueStore();
        unset($data['inherited']);
        $this->values = $data;
    }

    /**
     * Remove temporary values
     *
     * @return void
     */
    public function clean()
    {
        $this->values = \array_diff_key($this->values, \array_flip(self::$keysTemp));
        $this->setSubject(null);
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
            'debug' => Abstracter::ABSTRACTION,
            'inheritsFrom' => $this->inherited['className'],
            'type' => Type::TYPE_OBJECT,
        );
    }

    /**
     * Get class related values
     *
     * @return array
     */
    public function getInheritedValues()
    {
        $values = $this->inherited->getValues();
        unset($values['cfgFlags']);
        return $values;
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
            $this->getInheritedValues()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        $return = \array_replace_recursive(
            $this->getInheritedValues(),
            $this->values
        );
        \ksort($return, SORT_NATURAL);
        return $return;
    }

    /**
     * Sorts constant/property/method array by visibility or name
     *
     * @param array  $array array to sort
     * @param string $order ("visibility")|"name"
     *
     * @return array
     */
    public function sort(array $array, $order = 'visibility, name')
    {
        $order = \preg_split('/[,\s]+/', (string) $order);
        $aliases = array(
            'name' => 'name',
            'vis' => 'vis',
            'visibility' => 'vis',
        );
        foreach ($order as $i => $what) {
            if (isset($aliases[$what]) === false) {
                unset($order[$i]);
                continue;
            }
            $order[$i] = $aliases[$what];
        }
        if (empty($order)) {
            return $array;
        }
        $multiSortArgs = array();
        $sortData = $this->sortData($array);
        foreach ($order as $what) {
            $multiSortArgs[] = $sortData[$what];
        }
        $multiSortArgs[] = &$array;
        \call_user_func_array('array_multisort', $multiSortArgs);
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
        return isset($this->inherited[$key]);
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
        if (\in_array($key, self::$keysTemp, true)) {
            return $value;
        }
        $classVal = $this->inheritValue($key)
            ? $this->inherited[$key]
            : array();
        if (\is_array($value) && $classVal) {
            $combined = \array_replace_recursive($classVal, $value);
            \ksort($combined);
            return $combined;
        }
        return $value !== null
            ? $value
            : $classVal;
    }

    /**
     * Should we inherit the value from the definition
     *
     * @param string $key Value key
     *
     * @return bool
     */
    private function inheritValue($key)
    {
        if (\in_array($key, $this->sortableValues, true)) {
            $combined = \array_merge(array(
                'isExcluded' => $this->inherited['isExcluded'],
                'isRecursion' => $this->inherited['isRecursion'],
            ), $this->values);
            if ($combined['isExcluded'] || $combined['isRecursion']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Collect sort data to be used by `array_multisort`
     *
     * @param array $array The array of methods or properties to be sorted
     *
     * @return array
     */
    protected function sortData(array $array)
    {
        $sortVisOrder = ['public', 'magic', 'magic-read', 'magic-write', 'protected', 'private', 'debug'];
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
                // Sort the visibility so we use the most significant vis
                ArrayUtil::sortWithOrder($vis, $sortVisOrder);
                $vis = $vis[0];
            }
            $sortData['name'][$name] = \strtolower($name);
            $sortData['vis'][$name] = \array_search($vis, $sortVisOrder, true);
        }
        return $sortData;
    }
}
