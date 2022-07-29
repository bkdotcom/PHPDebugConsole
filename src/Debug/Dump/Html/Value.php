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
use bdk\Debug\Dump\BaseValue;
use bdk\Debug\Dump\Html as Dumper;
use bdk\Debug\Dump\Html\HtmlObject;
use bdk\Debug\Dump\Html\HtmlString;

/**
 * Dump val as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 */
class Value extends BaseValue
{
    /** @var HtmlString string dumper */
    public $string;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /** @var HtmlObject */
    protected $lazyObject;

    /**
     * Constructor
     *
     * @param Dumper $dumper "parent" dump class
     */
    public function __construct(Dumper $dumper)
    {
        parent::__construct($dumper); // sets debug and dumper
        $this->html = $this->debug->html;
        $this->string = new HtmlString($this);
    }

    /**
     * Is value a timestamp?
     * Add classname & title if so
     *
     * Extends Base
     *
     * @param mixed       $val value to check
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string|false
     */
    public function checkTimestamp($val, Abstraction $abs = null)
    {
        $date = parent::checkTimestamp($val, $abs);
        if ($date === false) {
            return false;
        }
        $this->setDumpOpt('postDump', function ($dumped, $opts) use ($val, $date) {
            $attribsContainer = array(
                'class' => array('timestamp', 'value-container'),
                'title' => $date,
            );
            if ($opts['tagName'] === 'td') {
                return $this->html->buildTag(
                    'td',
                    $attribsContainer,
                    $this->html->buildTag('span', $opts['attribs'], $val)
                );
            }
            return $this->html->buildTag('span', $attribsContainer, $dumped);
        });
        return $date;
    }

    /**
     * Dump value as html
     *
     * @param mixed $val  value to dump
     * @param array $opts options for string values
     *                      addQuotes, sanitize, visualWhitespace, etc
     *
     * @return string
     */
    public function dump($val, $opts = array())
    {
        $opts = $this->setDumpOptDefaults($val, $opts);
        $val = parent::dump($val, $opts);
        $this->dumpOptions['attribs']['class'][] = 't_' . $this->dumpOptions['type'];
        if ($this->dumpOptions['typeMore'] !== null) {
            $this->dumpOptions['attribs']['data-type-more'] = \trim($this->dumpOptions['typeMore']);
        }
        $tagName = $this->dumpOptions['tagName'];
        if ($tagName === '__default__') {
            $tagName = $this->dumpOptions['type'] === Abstracter::TYPE_OBJECT
                ? 'div'
                : 'span';
        }
        if ($tagName) {
            $val = $this->html->buildTag($tagName, $this->dumpOptions['attribs'], $val);
        }
        if ($this->dumpOptions['postDump']) {
            $val = \call_user_func($this->dumpOptions['postDump'], $val, $this->dumpOptions);
        }
        return $val;
    }

    /**
     * Get "option" of value being dumped
     *
     * @param string $what (optional) name of option to get (ie sanitize, type, typeMore)
     *
     * @return mixed
     */
    public function getDumpOpt($what = null)
    {
        $val = parent::getDumpOpt($what);
        if ($what === 'tagName' && $val === '__default__') {
            $val = 'span';
            if (parent::getDumpOpt('type') === Abstracter::TYPE_OBJECT) {
                $val = 'div';
            }
        }
        return $val;
    }

    /**
     * Set "option" of value being dumped
     *
     * @param array|string $what name of value to set (or key/value array)
     * @param mixed        $val  value
     *
     * @return void
     */
    public function setDumpOpt($what, $val = null)
    {
        if ($what === 'attribs' && empty($val['class'])) {
            // make sure class is set
            $val['class'] = array();
        }
        parent::setDumpOpt($what, $val);
    }

    /**
     * Wrap classname in span.classname
     * if namespaced additionally wrap namespace in span.namespace
     * If callable, also wrap with .t_operator and .t_identifier
     *
     * @param mixed  $val        classname or classname(::|->)name (method/property/const)
     * @param string $asFunction (false) specify we're marking up a function
     * @param string $tagName    ("span") html tag to use
     * @param array  $attribs    (optional) additional html attributes for classname span
     * @param bool   $wbr        (false)
     *
     * @return string
     */
    public function markupIdentifier($val, $asFunction = false, $tagName = 'span', $attribs = array(), $wbr = false)
    {
        $parts = $this->parseIdentifier($val, $asFunction);
        $operator = '<span class="t_operator">' . \htmlspecialchars($parts['operator']) . '</span>';
        if ($parts['classname']) {
            $classname = $parts['classname'];
            $idx = \strrpos($classname, '\\');
            if ($idx) {
                $classname = '<span class="namespace">' . \str_replace('\\', '\\<wbr />', \substr($classname, 0, $idx + 1)) . '</span>'
                    . \substr($classname, $idx + 1);
            }
            $parts['classname'] = $this->debug->html->buildTag(
                $tagName,
                $this->debug->arrayUtil->mergeDeep(array(
                    'class' => array('classname'),
                ), (array) $attribs),
                $classname
            ) . '<wbr />';
        }
        $parts['identifier'] = $parts['identifier']
            ? '<span class="t_identifier">' . $parts['identifier'] . '</span>'
            : '';
        $html = \implode($operator, \array_filter(array($parts['classname'], $parts['identifier']), 'strlen'));
        if ($wbr === false) {
            $html = \str_replace('<wbr />', '', $html);
        }
        return $html;
    }

    /**
     * Dump array as html
     *
     * @param array $array array
     *
     * @return string html
     */
    protected function dumpArray($array)
    {
        if (empty($array)) {
            return '<span class="t_keyword">array</span>'
                . '<span class="t_punct">()</span>';
        }
        $opts = \array_merge(array(
            'asFileTree' => false,
            'expand' => null,
            'showListKeys' => true,
        ), $this->getDumpOpt());
        if ($opts['expand'] !== null) {
            $this->setDumpOpt('attribs.data-expand', $opts['expand']);
        }
        if ($opts['asFileTree']) {
            $this->setDumpOpt('attribs.class.__push__', 'array-file-tree');
        }
        $showKeys = $opts['showListKeys'] || !$this->debug->arrayUtil->isList($array);
        $html = '<span class="t_keyword">array</span>'
            . '<span class="t_punct">(</span>' . "\n"
            . '<ul class="array-inner list-unstyled">' . "\n";
        foreach ($array as $key => $val) {
            $html .= $this->dumpArrayValue($key, $val, $showKeys);
        }
        $html .= '</ul>'
            . '<span class="t_punct">)</span>';
        return $html;
    }

    /**
     * Dump an array key/value pair
     *
     * @param int|string $key     key
     * @param mixed      $val     value
     * @param bool       $withKey include key with value?
     *
     * @return string
     */
    private function dumpArrayValue($key, $val, $withKey)
    {
        return $withKey
            ? "\t" . '<li>'
                . $this->html->buildTag(
                    'span',
                    array(
                        'class' => array(
                            't_key',
                            't_int' => \is_int($key),
                        ),
                    ),
                    $this->dump($key, array('tagName' => null)) // don't wrap it
                )
                . '<span class="t_operator">=&gt;</span>'
                . $this->dump($val)
            . '</li>' . "\n"
            : "\t" . $this->dump($val, array('tagName' => 'li')) . "\n";
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
        return $val ? 'true' : 'false';
    }

    /**
     * Dump "Callable" as html
     *
     * @param Abstraction $abs callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        return (!$abs['hideType'] ? '<span class="t_type">callable</span> ' : '')
            . $this->markupIdentifier($abs);
    }

    /**
     * Dump "const" abstration as html
     *
     * Object constant or method param's default value
     *
     * @param Abstraction $abs const abstraction
     *
     * @return string
     */
    protected function dumpConst(Abstraction $abs)
    {
        $this->setDumpOpt('attribs.title', $abs['value']
            ? 'value: ' . $this->debug->getDump('text')->valDumper->dump($abs['value'])
            : null);
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
        if ($val === Abstracter::TYPE_FLOAT_INF) {
            return 'INF';
        }
        if ($val === Abstracter::TYPE_FLOAT_NAN) {
            return 'NaN';
        }
        $this->checkTimestamp($val, $abs);
        return $val;
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return 'null';
    }

    /**
     * Dump object as html
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        /*
            Were we debugged from inside or outside of the object?
        */
        $this->setDumpOpt('attribs.data-accessible', $abs['scopeClass'] === $abs['className']
            ? 'private'
            : 'public');
        return $this->object->dump($abs);
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        $this->setDumpOpt('tagName', null); // don't wrap value span
        return '<span class="t_keyword">array</span> <span class="t_recursion">*RECURSION*</span>';
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
        return $this->string->dump($val, $abs);
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return '';
    }

    /**
     * Dump Abstraction::TYPE_UNKNOWN
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return string
     */
    protected function dumpUnknown(Abstraction $abs)
    {
        return 'unknown type';
    }

    /**
     * Getter for this->object
     *
     * @return HtmlObject
     */
    protected function getObject()
    {
        if (!$this->lazyObject) {
            $this->lazyObject = new HtmlObject($this, $this->dumper->helper, $this->html);
        }
        return $this->lazyObject;
    }

    /**
     * Get dump options
     *
     * @param mixed $val  value being dumpted
     * @param array $opts options for string values
     *                      addQuotes, sanitize, visualWhitespace, etc
     *
     * @return array
     */
    private function setDumpOptDefaults($val, $opts)
    {
        $attribs = array(
            'class' => array(),
        );
        if ($val instanceof Abstraction && \is_array($val['attribs'])) {
            $attribs = \array_merge(
                $attribs,
                $val['attribs']
            );
        }
        $opts = \array_merge(array(
            'tagName' => '__default__',
            'attribs' => $attribs,
            'postDump' => null,
        ), $opts);
        return $opts;
    }
}
