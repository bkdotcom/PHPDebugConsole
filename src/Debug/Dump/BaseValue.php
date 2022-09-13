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

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Dump\Base as Dumper;
use bdk\PubSub\Event;
use DateTime;

/**
 * Dump values
 */
class BaseValue extends AbstractComponent
{
    public $debug;

    protected $dumper;
    protected $dumpOptions = array();
    protected $dumpOptStack = array();
    protected $simpleTypes = array(
        Abstracter::TYPE_ARRAY,
        Abstracter::TYPE_BOOL,
        Abstracter::TYPE_FLOAT,
        Abstracter::TYPE_INT,
        Abstracter::TYPE_NULL,
        Abstracter::TYPE_STRING,
    );

    /**
     * Constructor
     *
     * @param Dumper $dumper "parent" dump class
     */
    public function __construct(Dumper $dumper)
    {
        $this->dumper = $dumper;
        $this->debug = $dumper->debug;
    }

    /**
     * Dump value
     *
     * @param mixed $val  value to dump
     * @param array $opts options & info for string values
     *
     * @return mixed
     */
    public function dump($val, $opts = array())
    {
        $opts = \array_merge(array(
            'addQuotes' => true,
            'sanitize' => true,     // only applies to html
            'type' => null,
            'typeMore' => null,
            'visualWhiteSpace' => true,
        ), $opts);
        if ($opts['type'] === null) {
            list($opts['type'], $opts['typeMore']) = $this->debug->abstracter->getType($val);
        }
        if ($opts['typeMore'] === Abstracter::TYPE_RAW) {
            if ($opts['type'] === Abstracter::TYPE_OBJECT || $this->dumper->crateRaw) {
                $val = $this->debug->abstracter->crate($val, 'dump');
            }
            $opts['typeMore'] = null;
        }
        $this->dumpOptStack[] = $opts;
        $method = 'dump' . \ucfirst($opts['type']);
        $return = $opts['typeMore'] === Abstracter::TYPE_ABSTRACTION
            ? $this->dumpAbstraction($val)
            : $this->{$method}($val);
        $this->dumpOptions = \array_pop($this->dumpOptStack);
        return $return;
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
        $path = $what === null
            ? '__end__'
            : '__end__.' . $what;
        return $this->debug->arrayUtil->pathGet($this->dumpOptStack, $path);
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
        if (\is_array($what)) {
            $this->debug->arrayUtil->pathSet($this->dumpOptStack, '__end__', $what);
            return;
        }
        $this->debug->arrayUtil->pathSet(
            $this->dumpOptStack,
            '__end__.' . $what,
            $val
        );
    }

    /**
     * Extend me to format classname/constant, etc
     *
     * @param mixed $val classname or classname(::|->)name (method/property/const)
     *
     * @return string
     */
    public function markupIdentifier($val)
    {
        if ($val instanceof Abstraction) {
            $val = $val['value'];
            if (\is_array($val)) {
                $val = $val[0] . '::' . $val[1];
            }
        }
        return $val;
    }

    /**
     * Is value a timestamp?
     *
     * @param mixed       $val value to check
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string|false
     */
    protected function checkTimestamp($val, Abstraction $abs = null)
    {
        if ($abs && $abs['typeMore'] === Abstracter::TYPE_TIMESTAMP) {
            $datetime = new DateTime('@' . (int) $val);
            $datetimeStr = $datetime->format('Y-m-d H:i:s T');
            $datetimeStr = \str_replace('GMT+0000', 'GMT', $datetimeStr);
            return $datetimeStr;
        }
        return false;
    }

    /**
     * Dump an abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return string|null
     */
    protected function dumpAbstraction(Abstraction $abs)
    {
        $type = $abs['type'];
        $method = 'dump' . \ucfirst($type);
        $opts = $this->dumpAbstractionOpts($abs);
        if ($abs['options']) {
            $opts = \array_merge($opts, $abs['options']);
        }
        $opts['typeMore'] = $abs['typeMore'];
        $this->setDumpOpt($opts);
        if (\method_exists($this, $method) === false) {
            $event = $this->debug->publishBubbleEvent(Debug::EVENT_DUMP_CUSTOM, new Event(
                $abs,
                array(
                    'valDumper' => $this,
                    'return' => '',
                )
            ));
            $this->setDumpOpt('typeMore', $abs['typeMore']);
            return $event['return'];
        }
        return \in_array($type, $this->simpleTypes, true)
            ? $this->{$method}($abs['value'], $abs)
            : $this->{$method}($abs);
    }

    /**
     * Dump array
     *
     * @param array $array array to dump
     *
     * @return array|string
     */
    protected function dumpArray($array)
    {
        foreach ($array as $key => $val) {
            $array[$key] = $this->dump($val);
        }
        return $array;
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
     *
     * @return bool|string
     */
    protected function dumpBool($val)
    {
        return $val;
    }

    /**
     * Dump callable
     *
     * @param Abstraction $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        return (!$abs['hideType'] ? 'callable: ' : '')
             . $this->markupIdentifier($abs);
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
        return $abs['name'];
    }

    /**
     * Dump float value
     *
     * @param float|int   $val float value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return float|string
     */
    protected function dumpFloat($val, Abstraction $abs = null)
    {
        $date = $this->checkTimestamp($val, $abs);
        return $date
            ? $val . ' (' . $date . ')'
            : $val;
    }

    /**
     * Dump integer value
     *
     * @param int         $val integer value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return int|string
     */
    protected function dumpInt($val, Abstraction $abs = null)
    {
        $val = $this->dumpFloat($val, $abs);
        return \is_string($val)
            ? $val
            : (int) $val;
    }

    /**
     * Dump non-inspected value (likely object)
     *
     * @return string
     */
    protected function dumpNotInspected()
    {
        return 'NOT INSPECTED';
    }

    /**
     * Dump null value
     *
     * @return null|string
     */
    protected function dumpNull()
    {
        return null;
    }

    /**
     * Dump object
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string|array
     */
    protected function dumpObject(Abstraction $abs)
    {
        if ($abs['isRecursion']) {
            return '(object) ' . $abs['className'] . ' *RECURSION*';
        }
        if ($abs['isExcluded']) {
            return '(object) ' . $abs['className'] . ' NOT INSPECTED';
        }
        return array(
            '___class_name' => $abs['className'],
        ) + (array) $this->dumpObjectProperties($abs);
    }

    /**
     * Return array of object properties (name->value)
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return array|string
     */
    protected function dumpObjectProperties(Abstraction $abs)
    {
        $return = array();
        foreach ($abs['properties'] as $name => $info) {
            $vis = $this->dumpPropVis($info);
            $name = '(' . $vis . ') ' . \str_replace('debug.', '', $name);
            $return[$name] = $this->dump($info['value']);
        }
        return $return;
    }

    /**
     * Dump property visibility
     *
     * @param array $info property info array
     *
     * @return string visibility
     */
    protected function dumpPropVis($info)
    {
        $vis = (array) $info['visibility'];
        foreach ($vis as $i => $v) {
            if (\in_array($v, array('magic','magic-read','magic-write'), true)) {
                $vis[$i] = '✨ ' . $v;    // "sparkles": there is no magic-wand unicode char
            } elseif ($v === 'private' && $info['inheritedFrom']) {
                $vis[$i] = '🔒 ' . $v;
            }
        }
        if ($info['debugInfoExcluded']) {
            $vis[] = 'excluded';
        }
        return \implode(' ', $vis);
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return 'array *RECURSION*';
    }

    /**
     * Dump resource
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return string
     */
    protected function dumpResource(Abstraction $abs)
    {
        return $abs['value'];
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
            $date = $this->checkTimestamp($val, $abs);
            return $date
                ? $val . ' (' . $date . ')'
                : $val;
        }
        return $abs
            ? $this->dumpStringAbs($abs)
            : $this->debug->utf8->dump($val);
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return Abstracter::UNDEFINED;
    }

    /**
     * Dump Abstraction::TYPE_UNKNOWN
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return array|string
     */
    protected function dumpUnknown(Abstraction $abs)
    {
        $values = $abs->getValues();
        \ksort($values);
        return $values;
    }

    /**
     * Split identifier into classname, operator, & identifier
     * Identifier = classname, function, or property
     *
     * @param Abstraction|array|string $val        classname or classname(::|->)name (method/property/const)
     * @param bool                     $asFunction (false)
     *
     * @return array
     */
    protected function parseIdentifier($val, $asFunction = false)
    {
        if ($val instanceof Abstraction) {
            $val = $val['value'];
        }
        $parts = array(
            'classname' => $val,
            'operator' => '::',
            'identifier' => '',
        );
        $matches = array();
        if (\is_array($val)) {
            $parts['classname'] = $val[0];
            $parts['identifier'] = $val[1];
        } elseif (\preg_match('/^(.+)(::|->)(.+)$/', $val, $matches)) {
            $parts['classname'] = $matches[1];
            $parts['operator'] = $matches[2];
            $parts['identifier'] = $matches[3];
        } elseif (\preg_match('/^(.+)(\\\\\{closure\})$/', $val, $matches)) {
            $parts['classname'] = $matches[1];
            $parts['operator'] = '';
            $parts['identifier'] = $matches[2];
        } elseif ($asFunction) {
            $parts['classname'] = '';
            $parts['identifier'] = $val;
        }
        return $parts;
    }

    /**
     * Get dump options for abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return array
     */
    private function dumpAbstractionOpts(Abstraction $abs)
    {
        $opts = $this->getDumpOpt();
        foreach (\array_keys($opts) as $k) {
            if ($abs[$k] !== null) {
                $opts[$k] = $abs[$k];
            }
        }
        return $opts;
    }

    /**
     * Dump string abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return string
     */
    private function dumpStringAbs(Abstraction $abs)
    {
        if ($abs['typeMore'] === Abstracter::TYPE_STRING_BINARY && !$abs['value']) {
            return 'Binary data not collected';
        }
        $val = $this->debug->utf8->dump($abs['value']);
        $diff = $abs['strlen']
            ? $abs['strlen'] - \strlen($abs['value'])
            : 0;
        if ($diff) {
            $val .= '[' . $diff . ' more bytes (not logged)]';
        }
        return $val;
    }
}
