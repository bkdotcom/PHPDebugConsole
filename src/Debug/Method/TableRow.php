<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Method;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;

/**
 * Represent a table row
 */
class TableRow
{
    const SCALAR = "\x00scalar\x00";

    private $row = null;

    /**
     * Will be populated with object info
     *  if row is an object, $info will be populated with
     *      'class' & 'summary'
     *  if a value is an object being displayed as a string,
     *      $info['classes'][key] will be populated with className
     *
     * @var array
     */
    private $info = array(
        'class' => null,
        'classes' => array(), // key => classname (or false if not stringified class)
        'isScalar' => false,
        'summary' => null,
    );

    /**
     * Constructor
     *
     * @param mixed $row May be "scalar", array, abstraction (array, Traversable, object)
     */
    public function __construct($row)
    {
        if (\is_array($row)) {
            $this->row = $row;
            return;
        }
        if ($row instanceof Abstraction) {
            $this->row = $this->valuesAbs($row);
            return;
        }
        $this->info['isScalar'] = true;
        $this->row = array(
            self::SCALAR => $row,
        );
    }

    /**
     * Get the collected row information
     *
     * @return array
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * Get column value
     *
     * @param string|int $name        column name
     * @param bool       $stringified return "stringified" value?
     *
     * @return mixed
     */
    public function getValue($name, $stringified = true)
    {
        $value = \array_key_exists($name, $this->row)
            ? $this->row[$name]
            : Abstracter::UNDEFINED;
        if ($stringified && $value instanceof Abstraction) {
            // just return the stringified / __toString value in a table
            if (isset($value['stringified'])) {
                $this->info['classes'][$name] = $value['className'];
                $value = $value['stringified'];
            } elseif (isset($value['__toString']['returnValue'])) {
                $this->info['classes'][$name] = $value['className'];
                $value = $value['__toString']['returnValue'];
            }
        }
        return $value;
    }

    /**
     * Get the row's keys
     *
     * @return string[]
     */
    public function keys()
    {
        return \array_keys($this->row);
    }

    /**
     * Get values for passed keys
     *
     * @param array $keys column keys
     *
     * @return array key => value array
     */
    public function keyValues($keys)
    {
        $values = array();
        foreach ($keys as $key) {
            $this->info['classes'][$key] = false;
            $values[$key] = $this->getValue($key);
        }
        if (\array_keys($values) === array(self::SCALAR)) {
            $this->info['isScalar'] = true;
        }
        return $values;
    }

    /**
     * Get values from abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return array
     */
    private function valuesAbs(Abstraction $abs)
    {
        if ($abs['type'] !== Abstracter::TYPE_OBJECT) {
            // resource & callable
            $this->info['isScalar'] = true;
            return array(self::SCALAR => $abs);
        }
        if ($abs['className'] === 'Closure') {
            $this->info['isScalar'] = true;
            return array(self::SCALAR => $abs);
        }
        $this->info['class'] = $abs['className'];
        $this->info['summary'] = $abs['phpDoc']['summary'];
        $values = self::valuesAbsObj($abs);
        if (\is_array($values) === false) {
            // ie stringified value
            $this->info['class'] = null;
            $this->info['isScalar'] = true;
            $values = array(self::SCALAR => $values);
        }
        return $values;
    }

    /**
     * Get object abstraction's values
     * if, object has a stringified or __toString value, it will be returned
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return array|string
     */
    private static function valuesAbsObj(Abstraction $abs)
    {
        if ($abs['traverseValues']) {
            // probably Traversable
            return $abs['traverseValues'];
        }
        if ($abs['stringified']) {
            return $abs['stringified'];
        }
        if (isset($abs['methods']['__toString']['returnValue'])) {
            return $abs['methods']['__toString']['returnValue'];
        }
        $values = \array_map(
            static function ($info) {
                return $info['value'];
            },
            \array_filter($abs['properties'], static function ($prop) {
                return $prop['visibility'] === 'public';
            })
        );
        /*
            Reflection doesn't return properties in any given order
            so, we'll sort for consistency
        */
        \ksort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return $values;
    }
}
