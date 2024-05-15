<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump\TextAnsi;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Text\Value as TextValue;
use bdk\Debug\Dump\TextAnsi\TextAnsiObject;
use bdk\Debug\Utility\Utf8;

/**
 * Base output plugin
 */
class Value extends TextValue
{
    /** @var string */
    public $escapeReset = "\e[0m";

    /** @var TextAnsiObject */
    protected $lazyObject;

    /**
     * Get escape reset sequence
     *
     * @return string
     */
    public function getEscapeReset()
    {
        return $this->escapeReset;
    }

    /**
     * Set escape reset sequence
     *
     * @param string $escapeReset ("\e[0m") escape reset sequence
     *
     * @return void
     */
    public function setEscapeReset($escapeReset = "\e[0m")
    {
        $this->escapeReset = $escapeReset;
    }

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
     * Wrap string in quotes
     *
     * @param string $val string value
     *
     * @return string
     */
    protected function addQuotes($val)
    {
        $ansiQuote = $this->cfg['escapeCodes']['quote'] . '"' . $this->escapeReset;
        return $this->optionGet('addQuotes')
            ? $ansiQuote . $val . $ansiQuote
            : $val;
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpArray(array $array, Abstraction $abs = null)
    {
        $this->valDepth++;
        $isNested = $this->valDepth > 0;
        $escapeCodes = $this->cfg['escapeCodes'];
        if ($this->optionGet('isMaxDepth')) {
            return $this->cfg['escapeCodes']['keyword'] . 'array '
                . $this->cfg['escapeCodes']['recursion'] . '*MAX DEPTH*'
                . $this->escapeReset;
        }
        $absKeys = isset($abs['keys'])
            ? $abs['keys']
            : array();
        $str = $escapeCodes['keyword'] . 'array' . $escapeCodes['punct'] . '(' . $this->escapeReset . "\n"
            . $this->dumpArrayValues($array, $absKeys)
            . $this->cfg['escapeCodes']['punct'] . ')' . $this->escapeReset;
        if (!$array) {
            $str = \str_replace("\n", '', $str);
        } elseif ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump an array key/value pair
     *
     * @param array $array   array to output
     * @param array $absKeys keys that required abstraction (ie, non-utf8, or containing confusable characters)
     *
     * @return string
     */
    private function dumpArrayValues(array $array, array $absKeys)
    {
        $str = '';
        $escapeCodes = $this->cfg['escapeCodes'];
        $escapeResetBackup = $this->escapeReset;
        $regexRemoveKeyReset = '/' . \preg_quote($escapeCodes['arrayKey']) . '$/';
        foreach ($array as $key => $val) {
            if (isset($absKeys[$key])) {
                $key = $absKeys[$key];
            }
            $this->escapeReset = \str_replace('m', ';49m', $escapeCodes['arrayKey']);
            $key = (\is_int($key) ? '' : $escapeCodes['arrayKey']) . $this->dump($key, array('addQuotes' => false));
            $key = \preg_replace($regexRemoveKeyReset, '', $key);
            $this->escapeReset = $escapeResetBackup;
            $str .= '    '
                . $escapeCodes['punct'] . '[' . $key . $escapeCodes['punct'] . ']'
                . $escapeCodes['operator'] . ' => ' . $this->escapeReset
                . $this->dump($val) . "\n";
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
     * Dump constant
     *
     * @param Abstraction $abs constant abstraction
     *
     * @return string
     */
    protected function dumpConst(Abstraction $abs)
    {
        return $this->markupIdentifier($abs['name']);
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
        if ($val === Type::TYPE_FLOAT_INF) {
            $val = 'INF';
        } elseif ($val === Type::TYPE_FLOAT_NAN) {
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
     * Dump object
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(ObjectAbstraction $abs)
    {
        return $this->object->dump($abs);
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
        if (\is_numeric($val)) {
            return $this->dumpStringNumeric($val, $abs);
        }
        if ($abs) {
            return $this->dumpStringAbs($abs);
        }
        $val = $this->highlightChars($val);
        $val = $this->addQuotes($val);
        return $val;
    }

    /**
     * Dump string abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return string
     */
    protected function dumpStringAbs(Abstraction $abs)
    {
        if ($abs['strlen'] === null) {
            $abs['strlen'] = \strlen($abs['value']);
        }
        if ($abs['strlenValue'] === null) {
            $abs['strlenValue'] = $abs['strlen'];
        }
        $val = $abs['typeMore'] === Type::TYPE_STRING_BINARY
            ? $this->dumpStringBinary($abs)
            : $this->highlightChars((string) $abs);
        $strLenDiff = $abs['strlen'] - $abs['strlenValue'];
        if ($abs['strlenValue'] && $strLenDiff) {
            $val .= $this->cfg['escapeCodes']['maxlen']
            . '[' . $strLenDiff . ' more bytes (not logged)]'
            . $this->escapeReset;
        }
        return $this->addQuotes($val);
    }

    /**
     * Get binary value
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return string
     */
    protected function dumpStringBinary(Abstraction $abs)
    {
        if ($abs['brief'] && $abs['contentType']) {
            return $this->cfg['escapeCodes']['keyword'] . 'string' . $this->escapeReset
                . $this->cfg['escapeCodes']['muted'] . '(' . $abs['contentType'] . ')' . $this->escapeReset . ': '
                . $this->debug->utility->getBytes($abs['strlen']);
        }
        if ($abs['value']) {
            return $this->cfg['escapeCodes']['binary'] . $abs['value'] . $this->escapeReset;
        }
        return \implode('', \array_map(function ($chunk) {
            return $chunk[0] === Utf8::TYPE_UTF8
                ? $this->highlightChars($chunk[1])
                : $this->cfg['escapeCodes']['binary'] . $chunk[1] . $this->escapeReset;
        }, $abs['chunks'] ?: array()));
    }

    /**
     * Dump numeric string
     *
     * @param string      $val numeric string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    private function dumpStringNumeric($val, Abstraction $abs = null)
    {
        $escapeCodes = $this->cfg['escapeCodes'];
        $date = $this->checkTimestamp($val, $abs);
        $val = $escapeCodes['numeric'] . $val . $this->escapeReset;
        $val = $this->addQuotes($val);
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
        return "\e[2m" . 'undefined' . "\e[22m"; // dim & reset dim
    }

    /**
     * Getter for this->object
     *
     * @return TextAnsiObject
     */
    protected function getObject()
    {
        if (!$this->lazyObject) {
            $this->lazyObject = new TextAnsiObject($this);
        }
        return $this->lazyObject;
    }

    /**
     * Highlight confusable and other characters
     *
     * @param string $str      HTML String to update
     * @param bool   $charLink Whether to hyperlink to unicode.info
     *
     * @return string
     */
    protected function highlightChars($str, $charLink = true)
    {
        $chars = $this->findChars($str);
        foreach ($chars as $char) {
            $replacement = \ord($char[0]) < 0x80
                ? '\\x' . \str_pad(\dechex(\ord($char)), 2, '0', STR_PAD_LEFT)
                : '\\u{' . \str_pad(\dechex(Utf8::ord($char)), 4, '0', STR_PAD_LEFT) . '}';
            $replacement = $this->cfg['escapeCodes']['char'] . $replacement . $this->escapeReset;
            $str = \str_replace($char, $replacement, $str);
        }
        return $str;
    }
}
