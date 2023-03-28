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

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Dump\Html\HtmlStringEncoded;
use bdk\Debug\Dump\Html\Value as ValDumper;
use RuntimeException;

/**
 * Output object as HTML
 */
class HtmlString
{
    public $detectFiles = false;

    public $debug;
    public $valDumper;

    protected $lazy = array(
        'dumpEncoded' => null,
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
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    public function dump($val, Abstraction $abs = null)
    {
        if (\is_numeric($val)) {
            $this->valDumper->checkTimestamp($val, $abs);
        }
        if ($this->detectFiles && $this->debug->utility->isFile($val)) {
            $this->valDumper->setDumpOpt('attribs.data-file', true);
        }
        if (!$this->valDumper->getDumpOpt('addQuotes')) {
            $this->valDumper->setDumpOpt('attribs.class.__push__', 'no-quotes');
        }
        if ($abs) {
            return $this->dumpAbs($abs);
        }
        return $this->dumpHelper($val);
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
        $isBinary = $val instanceof Abstraction && $val['typeMore'] === Abstracter::TYPE_STRING_BINARY;
        if ($isBinary === false) {
            // we do NOT wrap in <span>...  log('<a href="%s">link</a>', $url);
            $opts['tagName'] = null;
            return $this->valDumper->dump($val, $opts);
        }
        // TYPE_STRING_BINARY
        if (!$val['value']) {
            return 'Binary data not collected';
        }
        $str = $this->debug->utf8->dump($val['value']);
        $diff = $val['strlen']
            ? $val['strlen'] - \strlen($val['value'])
            : 0;
        if ($diff) {
            $str .= '[' . $diff . ' more bytes (not logged)]';
        }
        return $str;
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
        $typesEncoded = array(
            Abstracter::TYPE_STRING_BASE64,
            Abstracter::TYPE_STRING_JSON,
            Abstracter::TYPE_STRING_SERIALIZED,
        );
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
            $search = array("\r","\n");
            $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>' . "\n");
            return \str_replace($search, $replace, $matches[1]);
        }, $str);
        $str = \str_replace("\t", '<span class="ws_t">' . "\t" . '</span>', $str);
        return $str;
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
        if ($abs['typeMore'] === Abstracter::TYPE_STRING_CLASSNAME) {
            return $this->dumpClassname($abs);
        }
        $val = $this->dumpHelper($abs['value']);
        if ($this->isEncoded($abs)) {
            return $this->dumpEncoded->dump($val, $abs);
        }
        if ($abs['typeMore'] === Abstracter::TYPE_STRING_BINARY) {
            return $this->dumpBinary($val, $abs);
        }
        if ($abs['strlen']) {
            $strlenDumped = \strlen($abs['value']);
            $val .= '<span class="maxlen">&hellip; ' . ($abs['strlen'] - $strlenDumped) . ' more bytes (not logged)</span>';
        }
        if ($abs['prettifiedTag']) {
            $this->valDumper->setDumpOpt('postDump', $this->buildPrettifiedPostDump($abs));
        }
        return $val;
    }

    /**
     * Dump classname
     *
     * @param Abstraction $abs String abstraction
     *
     * @return string html fragment
     */
    private function dumpClassname(Abstraction $abs)
    {
        $val = $this->valDumper->markupIdentifier($abs['value']);
        $parsed = $this->debug->html->parseTag($val);
        $attribs = $this->valDumper->getDumpOpt('attribs');
        $attribs = $this->debug->arrayUtil->mergeDeep($attribs, $parsed['attribs']);
        $this->valDumper->setDumpOpt('attribs', $attribs);
        return $parsed['innerhtml'];
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
     * Dump binary string
     *
     * @param string      $val dumped value
     * @param Abstraction $abs String Abstraction
     *
     * @return string
     */
    private function dumpBinary($val, Abstraction $abs)
    {
        $tagName = $this->valDumper->getDumpOpt('tagName');
        $this->valDumper->setDumpOpt('tagName', null);
        $strLenDiff = $abs['strlen'] - \strlen($abs['value']);
        if ($val && $strLenDiff) {
            $val .= '<span class="maxlen">&hellip; ' . $strLenDiff . ' more bytes (not logged)</span>';
        }
        if ($abs['brief']) {
            return $abs['contentType']
                ? '<span class="t_keyword">string</span>'
                    . '<span class="text-muted">(' . $abs['contentType'] . ')</span><span class="t_punct colon">:</span> '
                    . $this->debug->utility->getBytes($abs['strlen'])
                : $val;
        }
        $this->valDumper->setDumpOpt('postDump', $this->dumpBinaryPost($abs, $tagName));
        return $val;
    }

    /**
     * Dump binary post data
     *
     * @param Abstraction $abs     String Abstraction
     * @param string      $tagName html tag (ie div,td, or span)
     *
     * @return closure
     */
    private function dumpBinaryPost(Abstraction $abs, $tagName)
    {
        return function ($dumped) use ($abs, $tagName) {
            $lis = array();
            if ($abs['contentType']) {
                $lis[] = '<li>mime type = <span class="content-type t_string">' . $abs['contentType'] . '</span></li>';
            }
            $lis[] = '<li>size = <span class="t_int">' . $abs['strlen'] . '</span></li>';
            $lis[] = $dumped
                ? '<li class="t_string">' . $dumped . '</li>'
                : '<li>Binary data not collected</li>';
            $wrapped =  '<span class="t_keyword">string</span><span class="text-muted">(binary)</span>' . "\n"
                . $this->debug->html->buildTag(
                    'ul',
                    \array_filter(array(
                        'class' => array('list-unstyled', 'value-container'),
                        'data-type' => $abs['type'],
                        'data-type-more' => $abs['typeMore'],
                    )),
                    "\n" . \implode("\n", $lis) . "\n"
                );
            if ($tagName === 'td') {
                // wrap with td without adding class="binary t_string"
                $wrapped = '<td>' . $wrapped . '</td>';
            }
            return $wrapped;
        };
    }

    /**
     * Sanitize and dump string.
     *
     * @param string $val string value to dump
     *
     * @return string
     */
    private function dumpHelper($val)
    {
        $opts = $this->valDumper->getDumpOpt();
        $val = $this->debug->utf8->dump($val, array(
            'sanitizeNonBinary' => $opts['sanitize'],
            'useHtml' => true,
        ));
        if ($opts['visualWhiteSpace']) {
            $val = $this->visualWhiteSpace($val);
        }
        return $val;
    }

    /**
     * lazy load HtmlStringEncoded instance
     *
     * @return HtmlStringEncoded
     */
    protected function getDumpEncoded()
    {
        if (isset($this->lazy['dumpEncoded'])) {
            return $this->lazy['dumpEncoded'];
        }
        return new HtmlStringEncoded($this);
    }
}
