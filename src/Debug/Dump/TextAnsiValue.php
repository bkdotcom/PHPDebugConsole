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

namespace bdk\Debug\Dump;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;

/**
 * Base output plugin
 */
class TextAnsiValue extends TextValue
{
    public $escapeReset = "\e[0m";

    /**
     * Add ansi escape sequences for classname type strings
     *
     * @param mixed $val        classname or classname(::|->)name (method/property/const)
     * @param bool  $asFunction (false) specify we're marking up a function
     *
     * @return string
     */
    public function markupIdentifier($val, $asFunction = false)
    {
        $parts = $this->parseIdentifier($val, $asFunction);
        $classname = '';
        $operator = $this->cfg['escapeCodes']['operator'] . $parts['operator'] . $this->escapeReset;
        $identifier = '';
        if ($parts['classname']) {
            $idx = \strrpos($parts['classname'], '\\');
            $classname = $parts['classname'];
            $classname = $idx
                ? $this->cfg['escapeCodes']['muted'] . \substr($classname, 0, $idx + 1) . $this->escapeReset
                    . "\e[1m" . \substr($classname, $idx + 1) . "\e[22m"
                : "\e[1m" . $classname . "\e[22m";
        }
        if ($parts['identifier']) {
            $identifier = "\e[1m" . $parts['identifier'] . "\e[22m";
        }
        $parts = \array_filter(array($classname, $identifier), 'strlen');
        return \implode($operator, $parts);
    }

    /**
     * Dump array as text
     *
     * @param array $array Array to display
     *
     * @return string
     */
    protected function dumpArray($array)
    {
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $escapeCodes = $this->cfg['escapeCodes'];
        $str = $escapeCodes['keyword'] . 'array' . $escapeCodes['punct'] . '(' . $this->escapeReset . "\n";
        foreach ($array as $k => $v) {
            $escapeKey = \is_int($k)
                ? $escapeCodes['numeric']
                : $escapeCodes['arrayKey'];
            $str .= '    '
                . $escapeCodes['punct'] . '[' . $escapeKey . $k . $escapeCodes['punct'] . ']'
                . $escapeCodes['operator'] . ' => ' . $this->escapeReset
                . $this->dump($v)
                . "\n";
        }
        $str .= $this->cfg['escapeCodes']['punct'] . ')' . $this->escapeReset;
        if (!$array) {
            $str = \str_replace("\n", '', $str);
        } elseif ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
     *
     * @return string
     */
    protected function dumpBool($val)
    {
        return $val
            ? $this->cfg['escapeCodes']['true'] . 'true' . $this->escapeReset
            : $this->cfg['escapeCodes']['false'] . 'false' . $this->escapeReset;
    }

    /**
     * Dump float value
     *
     * @param float       $val float value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return float|string
     */
    protected function dumpFloat($val, Abstraction $abs = null)
    {
        if ($val === Abstracter::TYPE_FLOAT_INF) {
            $val = 'INF';
        } elseif ($val === Abstracter::TYPE_FLOAT_NAN) {
            $val = 'NaN';
        }
        $date = $this->checkTimestamp($val, $abs);
        $val = $this->cfg['escapeCodes']['numeric'] . $val . $this->escapeReset;
        return $date
            ? '📅 ' . $val . ' ' . $this->cfg['escapeCodes']['muted'] . '(' . $date . ')' . $this->escapeReset
            : $val;
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return $this->cfg['escapeCodes']['muted'] . 'null' . $this->escapeReset;
    }

    /**
     * Dump object as text
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        $escapeCodes = $this->cfg['escapeCodes'];
        if ($abs['isRecursion']) {
            return $escapeCodes['excluded'] . '*RECURSION*' . $this->escapeReset;
        }
        if ($abs['isExcluded']) {
            return $escapeCodes['excluded'] . 'NOT INSPECTED' . $this->escapeReset;
        }
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $str = $this->markupIdentifier($abs['className']) . "\n"
            . $this->dumpObjectProperties($abs)
            . $this->dumpObjectMethods($abs);
        $str = \trim($str);
        if ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump object methods as text
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html
     */
    protected function dumpObjectMethods(Abstraction $abs)
    {
        $methodCollect = $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT;
        $methodOutput = $abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT;
        if (!$methodCollect || !$methodOutput) {
            return '';
        }
        $counts = array(
            'public' => 0,
            'protected' => 0,
            'private' => 0,
            'magic' => 0,
        );
        foreach ($abs['methods'] as $info) {
            $counts[ $info['visibility'] ] ++;
        }
        $counts = \array_filter($counts);
        $header = $counts
            ? "\e[4mMethods:\e[24m"
            : 'Methods: none!';
        $counts = \array_map(function ($vis, $count) {
            return '    ' . $vis
                . $this->cfg['escapeCodes']['punct'] . ':' . $this->escapeReset . ' '
                . $this->cfg['escapeCodes']['numeric'] . $count
                . $this->escapeReset . "\n";
        }, \array_keys($counts), $counts);
        return '  ' . $header . "\n" . \implode('', $counts);
    }

    /**
     * Dump object properties as text with ANSI escape codes
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObjectProperties(Abstraction $abs)
    {
        $str = '';
        if (isset($abs['methods']['__get'])) {
            $str .= '    ' . $this->cfg['escapeCodes']['muted']
                . '✨ This object has a __get() method'
                . $this->escapeReset
                . "\n";
        }
        foreach ($abs['properties'] as $name => $info) {
            $vis = $this->dumpPropVis($info);
            $str .= '    ' . $this->cfg['escapeCodes']['muted'] . '(' . $vis . ')' . $this->escapeReset
                . ' ' . $this->cfg['escapeCodes']['property'] . $name . $this->escapeReset
                . ($info['debugInfoExcluded']
                    ? ''
                    : ' '
                        . $this->cfg['escapeCodes']['operator'] . '=' . $this->escapeReset . ' '
                        . $this->dump($info['value'])
                ) . "\n";
        }
        $header = $str
            ? "\e[4mProperties:\e[24m"
            : 'Properties: none!';
        return '  ' . $header . "\n" . $str;
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return $this->cfg['escapeCodes']['keyword'] . 'array '
            . $this->cfg['escapeCodes']['recursion'] . '*RECURSION*'
            . $this->escapeReset;
    }

    /**
     * Dump string
     *
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, Abstraction $abs = null)
    {
        $addQuotes = $this->getDumpOpt('addQuotes');
        if (\is_numeric($val)) {
            return $this->dumpStringNumeric($val, $addQuotes, $abs);
        }
        $escapeCodes = $this->cfg['escapeCodes'];
        $ansiQuote = $escapeCodes['quote'] . '"' . $this->escapeReset;
        $val = $this->debug->utf8->dump($val);
        if ($addQuotes) {
            $val = $ansiQuote . $val . $ansiQuote;
        }
        $diff = $abs && $abs['strlen']
            ? $abs['strlen'] - \strlen($abs['value'])
            : 0;
        if ($diff) {
            $val .= $escapeCodes['maxlen']
                . '[' . $diff . ' more bytes (not logged)]'
                . $this->escapeReset;
        }
        return $val;
    }

    /**
     * Dump numeric string
     *
     * @param string      $val       numeric string value
     * @param bool        $addQuotes whether to add quotes
     * @param Abstraction $abs       (optional) full abstraction
     *
     * @return string
     */
    private function dumpStringNumeric($val, $addQuotes, Abstraction $abs = null)
    {
        $escapeCodes = $this->cfg['escapeCodes'];
        $date = $this->checkTimestamp($val, $abs);
        $val = $escapeCodes['numeric'] . $val;
        if ($addQuotes) {
            $val = $escapeCodes['quote'] . '"'
                . $val
                . $escapeCodes['quote'] . '"';
        }
        $val .= $this->escapeReset;
        return $date
            ? '📅 ' . $val . ' ' . $escapeCodes['muted'] . '(' . $date . ')' . $this->escapeReset
            : $val;
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return "\e[2mundefined\e[22m";
    }
}
