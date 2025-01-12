<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Utility;

/**
 * Html utilities
 */
class HtmlBuild
{
    /**
     * Omit these attributes if the value is an empty string
     *
     * We never include the attribute if the value is null (unless it's a data or boolean enum attribute)
     *
     * @var list<string>
     */
    public static $omitIfEmptyAttrib = ['class', 'style', 'title'];

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
        if ($value === '' && \in_array($name, self::$omitIfEmptyAttrib, true)) {
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
        switch (static::getAttribType($name, $val)) {
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
     * Currently this method is only used for the "style" attribute
     *
     * @param string   $name   attribute name ("style")
     * @param string[] $values key/value for style
     *
     * @return string|null
     */
    private static function buildAttribValArray($name, $values = array())
    {
        if ($name === 'style') {
            $keyValues = [];
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
     * @param mixed  $value Attribute value
     *
     * @return string|null
     */
    private static function buildAttribValBool($name, $value = true)
    {
        // opposite behavior of filter_var FILTER_VALIDATE_BOOLEAN
        //    treat as true unless explicitly falsy value
        $boolValue = !\in_array(\strtolower((string) $value), ['', 0, '0', 'false', 'no', 'off'], true);
        if (\in_array($name, Html::$htmlBoolAttrEnum, true) === false) {
            return $boolValue
                ? $name // even if not a recognized boolean attribute... we will output name="name"
                : null; // null = attribute won't be output
        }
        // non "true"/"false" bool attributes
        $enumValues = array(
            'autocapitalize' => ['off', 'on', true], // also takes other values ("sentences", "words", "characters")
            'autocomplete' => ['off', 'on', true], // other values also accepted
            'translate' => ['no', 'yes', false],
        );
        if (isset($enumValues[$name]) === false) {
            // "true" or "false"
            return \json_encode($boolValue);
        }
        $opts = $enumValues[$name];
        if ($opts[2] && \is_string($value)) {
            // attribute may be "arbitrary" value
            return $value;
        }
        return $opts[(int) $boolValue];
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
     * @return string 'array', 'bool', 'class', data', 'id', 'null', 'string'
     */
    private static function getAttribType($name, $val)
    {
        if (\substr($name, 0, 5) === 'data-') {
            return 'data';
        }
        if (self::isAttribBool($name, $val)) {
            return 'bool';
        }
        if ($val === null) {
            return 'null';
        }
        if (\in_array($name, ['class', 'id'], true)) {
            return $name;
        }
        if (\is_array($val)) {
            return 'array';
        }
        return 'string';
    }

    /**
     * Treat this attribute as boolean (incl boolean enum)?
     *
     * @param string $name Attribute name
     * @param mixed  $val  Attribute value
     *
     * @return bool
     */
    private static function isAttribBool($name, $val)
    {
        if (\is_bool($val)) {
            return true;
        }
        if (\in_array($name, Html::$htmlBoolAttr, true)) {
            return true;
        }
        return \in_array($name, Html::$htmlBoolAttrEnum, true);
    }
}
