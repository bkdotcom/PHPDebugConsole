<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\HtmlStringEncoded;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Utf8;
use Closure;
use RuntimeException;

/**
 * Output value with HTML markup
 *
 * @property-read HtmlStringBinary  $binary
 * @property-read HtmlStringEncoded $encoded
 */
class HtmlString
{
    /** @var Debug */
    public $debug;

    /** @var bool */
    public $detectFiles = false;

    /** @var ValDumper */
    public $valDumper;

    /** @var array<string,mixed> */
    protected $lazy = array(
        'binary' => null,
        'encoded' => null,
    );

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Dump\Html\Value instance
     */
    public function __construct(ValDumper $valDumper)
    {
        $this->debug = $valDumper->debug;
        $this->valDumper = $valDumper;
    }

    /**
     * Magic method to get inaccessible / undefined properties
     * Lazy load child classes
     *
     * @param string $property property name
     *
     * @return mixed property value
     *
     * @throws RuntimeException if no getter defined
     */
    public function __get($property)
    {
        if (isset($this->lazy[$property])) {
            return $this->lazy[$property];
        }
        $getter = 'get' . \ucfirst($property);
        if (!\method_exists($this, $getter)) {
            throw new RuntimeException('Access to undefined property: ' . __CLASS__ . '::' . $property);
        }
        $val = $this->{$getter}();
        $this->lazy[$property] = $val;
        return $val;
    }

    /**
     * Dump string
     *
     * @param string           $val string value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string
     */
    public function dump($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        if (\is_numeric($val)) {
            $this->valDumper->checkTimestamp($val, $abs);
        }
        $return = $abs
            ? $this->dumpAbs($abs)
            : $this->doDump($val);
        if ($this->detectFiles && $this->debug->utility->isFile($val)) {
            $this->valDumper->optionSet('attribs.data-file', true);
        }
        if (!$this->valDumper->optionGet('addQuotes')) {
            $this->valDumper->optionSet('attribs.class.__push__', 'no-quotes');
        }
        return $return;
    }

    /**
     * Dump with min markup
     *
     * @param mixed $val  string value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    public function dumpAsSubstitution($val, $opts)
    {
        $isBinary = $val instanceof Abstraction && $val['typeMore'] === Type::TYPE_STRING_BINARY;
        if ($isBinary) {
            $val['brief'] = true;
            return $this->binary->dump($val);
        }
        // we do NOT wrap in <span>...  log('<a href="%s">link</a>', $url);
        $opts['tagName'] = null;
        return $this->valDumper->dump($val, $opts);
    }

    /**
     * Is value encoded (ie base64, json, or serialized)
     *
     * @param mixed $val string value (or abstraction)
     *
     * @return bool
     */
    public function isEncoded($val)
    {
        $typesEncoded = [
            Type::TYPE_STRING_BASE64,
            Type::TYPE_STRING_FORM,
            Type::TYPE_STRING_JSON,
            Type::TYPE_STRING_SERIALIZED,
        ];
        return $val instanceof Abstraction && \in_array($val['typeMore'], $typesEncoded, true);
    }

    /**
     * Add whitespace markup
     *
     * @param string $str string which to add whitespace html markup
     *
     * @return string
     */
    public function visualWhiteSpace($str)
    {
        // display \r, \n, & \t
        $str = \preg_replace_callback('/(\r\n|\r|\n)/', static function ($matches) {
            $search = ["\r", "\n"];
            $replace = ['<span class="ws_r"></span>', '<span class="ws_n"></span>' . "\n"];
            return \str_replace($search, $replace, $matches[1]);
        }, $str);
        return \str_replace("\t", '<span class="ws_t">' . "\t" . '</span>', $str);
    }

    /**
     * Dump string encapsulated by Abstraction
     *
     * @param Abstraction $abs String Abstraction
     *
     * @return string
     */
    private function dumpAbs(Abstraction $abs)
    {
        if ($abs['strlen'] === null) {
            $abs['strlen'] = \strlen($abs['value']);
        }
        if ($abs['strlenValue'] === null) {
            $abs['strlenValue'] = $abs['strlen'];
        }
        if ($abs['typeMore'] === Type::TYPE_STRING_BINARY) {
            return $this->binary->dump($abs);
        }
        if ($this->isEncoded($abs)) {
            return $this->encoded->dump($abs);
        }
        if ($abs['prettified']) {
            $this->valDumper->optionSet('visualWhiteSpace', false);
            $this->valDumper->optionSet('postDump', $this->buildPrettifiedPostDump($abs));
        }
        $val = $this->doDump((string) $abs);
        $strLenDiff = $abs['strlen'] - $abs['strlenValue'];
        if ($strLenDiff) {
            $val .= '<span class="maxlen">&hellip; ' . $strLenDiff . ' more bytes (not logged)</span>';
        }
        return $val;
    }

    /**
     * Build replacement for control character
     *
     * @param array<string,string|null> $info Character info
     *
     * @return string
     */
    private function buildHighlightReplacementControl(array $info)
    {
        return $this->debug->html->buildTag(
            'span',
            array(
                'class' => $info['class'],
                'data-abbr' => $info['abbr'],
                'title' => \implode(': ', \array_filter([
                    '\\x' . \str_pad(\dechex(\ord($info['char'])), 2, '0', STR_PAD_LEFT),
                    $info['desc'],
                ])),
            ),
            $info['replaceWith']
        );
    }

    /**
     * Build replacement for unicode character
     *
     * @param array<string,string|null> $info Character info
     *
     * @return string
     */
    private function buildHighlightReplacementOther(array $info)
    {
        $codePoint = $info['codePoint'] ?: \dechex(Utf8::ord($info['char']));
        return $this->debug->html->buildTag(
            'span',
            array(
                'class' => $info['class'],
                'data-code-point' => $codePoint,
                'title' => \implode(': ', \array_filter([
                    'U-' . $codePoint,
                    $info['desc'],
                ])),
            ),
            $info['replaceWith']
        );
    }

    /**
     * Add "prettified" tag to prettified value
     *
     * @param Abstraction $abs String abstraction
     *
     * @return Closure
     */
    private function buildPrettifiedPostDump(Abstraction $abs)
    {
        return function ($dumped, $opts) use ($abs) {
            if ($abs['prettifiedTag'] === false) {
                return $dumped;
            }
            $tagName = 'span';
            if ($opts['tagName'] === 'td') {
                $tagName = 'td';
                $parsed = $this->debug->html->parseTag($dumped);
                $dumped = $this->debug->html->buildTag('span', $parsed['attribs'], $parsed['innerhtml']);
            }
            return $this->debug->html->buildTag(
                $tagName,
                \array_filter(array(
                    'class' => 'value-container',
                    'data-type' => $abs['type'],
                    'data-type-more' => $abs['typeMore'],
                )),
                '<span class="prettified">(prettified)</span> ' . $dumped
            );
        };
    }

    /**
     * Sanitize and dump string.
     *
     * @param string $val string value to dump
     *
     * @return string
     */
    private function doDump($val)
    {
        $opts = $this->valDumper->optionGet();
        if ($opts['sanitize']) {
            $val = \htmlspecialchars($val);
        }
        if ($opts['charHighlight']) {
            $val = $this->highlightChars($val);
        }
        if ($opts['visualWhiteSpace']) {
            $val = $this->visualWhiteSpace($val);
        }
        return $val;
    }

    /**
     * lazy load HtmlStringEncoded instance
     *
     * @return HtmlStringBinary
     */
    protected function getBinary()
    {
        if (isset($this->lazy['binary'])) {
            return $this->lazy['binary'];
        }
        return new HtmlStringBinary($this);
    }

    /**
     * lazy load HtmlStringEncoded instance
     *
     * @return HtmlStringEncoded
     */
    protected function getEncoded()
    {
        if (isset($this->lazy['encoded'])) {
            return $this->lazy['encoded'];
        }
        return new HtmlStringEncoded($this);
    }

    /**
     * Highlight confusable and other characters
     *
     * @param string $str HTML String to update
     *
     * @return string
     */
    private function highlightChars($str)
    {
        $chars = $this->valDumper->findChars($str);
        $charInfo = \array_intersect_key($this->valDumper->charData, \array_flip($chars));
        foreach ($charInfo as $char => $info) {
            $info = \array_merge(array(
                'char' => $char,
                'class' => 'unicode',
                'codePoint' => null,
                'desc' => '',
                'replaceWith' => $char,
            ), $info);
            $replacement = \ord($char[0]) < 0x80
                ? $this->buildHighlightReplacementControl($info)
                : $this->buildHighlightReplacementOther($info);
            $str = \str_replace($char, $replacement, $str);
        }
        return $str;
    }
}
