<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Dump\TextAnsi;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Text\Value as TextValue;
use bdk\Debug\Dump\TextAnsi as Dumper;
use bdk\Debug\Dump\TextAnsi\TextAnsiObject;
use bdk\Debug\Utility\Utf8;

/**
 * Base output plugin
 */
class Value extends TextValue
{
    /** @var string */
    protected $escapeReset = "\e[0m";

    /** @var TextAnsiObject */
    protected $lazyObject;

    /**
     * Constructor
     *
     * @param Dumper $dumper "parent" dump class
     */
    public function __construct(Dumper $dumper)
    {
        parent::__construct($dumper); // sets debug and dumper
        $this->optionStackPush(array(
            'charReplace' => false,
        ));
    }

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
     * @param string|array $val  classname or classname(::|->)name (method/property/const)
     * @param string       $what ("classname"), "const", or "function" - specify what we're marking if ambiguous
     *
     * @return string
     */
    public function markupIdentifier($val, $what = 'className')
    {
        $parts = $this->parseIdentifier($val, $what);
        $escapeReset = $this->escapeReset;
        $operator = $this->cfg['escapeCodes']['operator'] . $parts['operator'] . $this->escapeReset;
        $identifier = '';
        $classnameOut = $parts['classname']
            ? $this->markupIdentifierClassname($parts['classname'])
            : '';
        $namespaceOut = $parts['namespace']
            ? $this->markupIdentifierNamespace($parts['namespace'])
            : '';
        if ($parts['name']) {
            $this->escapeReset = "\e[0;1m";
            $identifier = "\e[1m" . $this->highlightChars($parts['name']) . "\e[22m";
            $this->escapeReset = $escapeReset;
        }
        $parts = \array_filter([$namespaceOut, $classnameOut, $identifier], 'strlen');
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
    protected function dumpArray(array $array, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

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
     * Dump float value
     *
     * @param float            $val float value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return float|string
     */
    protected function dumpFloat($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

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
     * Dump identifier (constant, classname, method, property)
     *
     * @param Abstraction $abs constant abstraction
     *
     * @return string
     */
    protected function dumpIdentifier(Abstraction $abs)
    {
        return $this->markupIdentifier($abs['value'], $abs['typeMore']);
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
     * @param string           $val string value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

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
            return $chunk[0] === Utf8::TYPE_OTHER
                ? $this->cfg['escapeCodes']['binary'] . $chunk[1] . $this->escapeReset
                : $this->highlightChars($chunk[1]);
        }, $abs['chunks'] ?: array()));
    }

    /**
     * Dump numeric string
     *
     * @param string           $val numeric string value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string
     */
    private function dumpStringNumeric($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

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
     * @param string $str HTML String to update
     *
     * @return string
     */
    protected function highlightChars($str)
    {
        $chars = $this->findChars($str);
        $charReplace = $this->optionGet('charReplace');
        foreach ($chars as $char) {
            $replacement = $this->cfg['escapeCodes']['char']
                . $this->charReplacement($char, $charReplace)
                . $this->escapeReset;
            $str = \str_replace($char, $replacement, $str);
        }
        return $str;
    }

    /**
     * Markup classname (namespace & classname) portion of identifier string
     *
     * @param string $classname classname
     *
     * @return string
     */
    private function markupIdentifierClassname($classname)
    {
        $classnameOut = '';
        $idx = \strrpos($classname, '\\');
        if ($idx) {
            $namespace = \substr($classname, 0, $idx + 1);
            $classname = \substr($classname, $idx + 1);
            $classnameOut = $this->markupIdentifierNamespace($namespace);
        }
        $escapeReset = $this->escapeReset;
        $this->escapeReset = "\e[0;1m";
        $classnameOut .= "\e[1m" . $this->highlightChars($classname) . "\e[22m";
        $this->escapeReset = $escapeReset;
        return $classnameOut;
    }

    /**
     * Markup namespace portion of identifier string
     *
     * @param string $namespace namespace
     *
     * @return string
     */
    private function markupIdentifierNamespace($namespace)
    {
        $escapeReset = $this->escapeReset;
        $this->escapeReset = $this->cfg['escapeCodes']['muted'];
        $namespace = $this->cfg['escapeCodes']['muted']
            . $this->highlightChars($namespace)
            . $escapeReset;
        $this->escapeReset = $escapeReset;
        return $namespace;
    }
}
