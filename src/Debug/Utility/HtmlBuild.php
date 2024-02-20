<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.3
 */

namespace bdk\Debug\Utility;

/**
 * Html utilities
 */
class HtmlBuild
{
    /**
     * Build name="value"
     *
     * @param string $name  Attribute name
     * @param mixed  $value Attribute value
     *
     * @return string
     */
    public static function buildAttribNameValue(&$name, $value)
    {
        if (\is_int($name)) {
            $name = (string) $value;
            $value = true;
        }
        $name = \strtolower($name);
        $value = self::buildAttribVal($name, $value);
        if ($value === null) {
            return '';
        }
        if ($value === '' && \in_array($name, array('class', 'style'), true)) {
            return '';
        }
        return $name . '="' . \htmlspecialchars($value) . '"';
    }

    /**
     * Converts attribute value to string
     *
     * @param string $name Attribute name
     * @param mixed  $val  Attribute value
     *
     * @return string|null
     */
    private static function buildAttribVal($name, $val)
    {
        switch (static::getType($name, $val)) {
            case 'array':
                /** @var string[] $val */
                return static::buildAttribValArray($name, $val);
            case 'bool':
                /** @var bool $val */
                return static::buildAttribValBool($name, $val);
            case 'class':
                /** @var string|string[] $val */
                return static::buildAttribValClass($val);
            case 'data':
                return \is_string($val)
                    ? $val
                    : \json_encode($val);
            case 'id':
                return Html::sanitizeId($val);
            case 'null':
                return null;
            default:
                return \trim($val);
        }
    }

    /**
     * Convert array attribute value to string
     *
     * This function is not meant for data attributs
     *
     * @param string   $name   attribute name ("style")
     * @param string[] $values key/value for style
     *
     * @return string|null
     */
    private static function buildAttribValArray($name, $values = array())
    {
        if ($name === 'style') {
            $keyValues = array();
            foreach ($values as $key => $val) {
                $keyValues[] = $key . ':' . $val . ';';
            }
            \sort($keyValues);
            return \implode('', $keyValues);
        }
        return null;
    }

    /**
     * Convert boolean attribute value to string
     *
     * @param string $name  Attribute name
     * @param bool   $value true|false
     *
     * @return string|null
     */
    private static function buildAttribValBool($name, $value = true)
    {
        $enumValues = array(
            'autocapitalize' => array('on','off'), // also takes other values (sentences, words, characters)
            'autocomplete' => array('on','off'), // other values also accepted
            'translate' => array('yes','no'),
        );
        if (isset($enumValues[$name])) {
            return $value
                ? $enumValues[$name][0]
                : $enumValues[$name][1];
        }
        if (\in_array($name, Html::$htmlBoolAttrEnum, true)) {
            // "true" or "false"
            return \json_encode($value);
        }
        return $value
            ? $name // even if not a recognized boolean attribute
            : null; // null = attribute won't be output
    }

    /**
     * Build class attribute value
     * May pass
     *   string:  'foo bar'
     *   array:  [
     *      'foo',
     *      'bar' => true,
     *      'notIncl' => false,
     *      'key' => 'classValue',
     *   ]
     *
     * @param string|string[] $values Class values.  May be array or space-separated string
     *
     * @return string
     */
    private static function buildAttribValClass($values)
    {
        if (\is_array($values) === false) {
            $values = \explode(' ', $values);
        }
        $values = \array_map(static function ($key, $val) {
            return \is_bool($val)
                ? ($val ?  $key : null)
                : $val;
        }, \array_keys($values), $values);
        // only interested in unique, non-empty values
        $values = \array_filter(\array_unique($values));
        \sort($values);
        return \implode(' ', $values);
    }

    /**
     * Determine the type of attribute being updated
     *
     * @param string $name Attribute name
     * @param mixed  $val  Attribute value
     *
     * @return string 'class', data', 'array', 'bool', 'string', or 'null'
     */
    private static function getType($name, $val)
    {
        if (\substr($name, 0, 5) === 'data-') {
            return 'data';
        }
        if ($val === null) {
            return 'null';
        }
        if (\in_array($name, array('class', 'id'), true)) {
            return $name;
        }
        if (\is_bool($val)) {
            return 'bool';
        }
        if (\is_array($val)) {
            return 'array';
        }
        return 'string';
    }
}
