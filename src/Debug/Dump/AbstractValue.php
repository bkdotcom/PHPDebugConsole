<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Base as Dumper;
use bdk\PubSub\Event;
use DateTime;

/**
 * Dump values
 */
abstract class AbstractValue extends AbstractComponent
{
    /** @var Debug */
    public $debug;

    /** @var array<string,charInfo> */
    public $charData = array();

    /** @var Dumper  */
    protected $dumper;

    /** @var non-empty-string */
    protected $charRegex = '';

    /** @var list<array<string,mixed>> */
    protected $optionStack = array();

    /** @var array<string,mixed> Pointer to top of optionsStack */
    protected $optionsCurrent = array();

    /** @var list<Type::TYPE_*> */
    protected $simpleTypes = [
        Type::TYPE_ARRAY,
        Type::TYPE_BOOL,
        Type::TYPE_FLOAT,
        Type::TYPE_INT,
        Type::TYPE_NULL,
        Type::TYPE_STRING,
    ];

    /**
     * Constructor
     *
     * @param Dumper $dumper "parent" dump class
     */
    public function __construct(Dumper $dumper)
    {
        $this->charData = require __DIR__ . '/charData.php';
        $this->charRegex = $this->buildCharRegex();
        $this->dumper = $dumper;
        $this->debug = $dumper->debug;
        $this->optionStackPush(array(
            'addQuotes' => true,
            'charHighlight' => true,
            'charReplace' => true,
        ));
    }

    /**
     * Is value a timestamp?
     *
     * @param mixed            $val value to check
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string|false
     */
    public function checkTimestamp($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        if ($abs && $abs['typeMore'] === Type::TYPE_TIMESTAMP) {
            $datetime = new DateTime('@' . (int) $val);
            $datetimeStr = $datetime->format('Y-m-d H:i:s T');
            $datetimeStr = \str_replace('GMT+0000', 'GMT', $datetimeStr);
            return $datetimeStr;
        }
        return false;
    }

    /**
     * Dump value
     *
     * @param mixed $val  Value to dump
     * @param array $opts Options & info for string values
     *
     * @return mixed
     */
    public function dump($val, $opts = array())
    {
        $opts = $this->getPerValueOptions($val, $opts);
        if ($opts['typeMore'] === Type::TYPE_RAW) {
            if ($opts['type'] === Type::TYPE_OBJECT || $this->dumper->crateRaw) {
                $val = $this->debug->abstracter->crate($val, 'dump');
            }
            $opts['typeMore'] = null;
        }
        $this->optionStackPush($opts);
        $return = $this->doDump($val);
        $this->optionStackPop();
        return $return;
    }

    /**
     * Find characters that should be highlighted
     *
     * @param string $str String to search for chars
     *
     * @return list<string>
     */
    public function findChars($str)
    {
        $matches = array();
        \preg_match_all($this->charRegex, (string) $str, $matches);
        return \array_unique($matches[0]);
    }

    /**
     * Get "option" of value being dumped
     *
     * @param string $what (optional) name of option to get (ie sanitize, type, typeMore)
     *
     * @return mixed
     */
    public function optionGet($what = null)
    {
        return $what === null
            ? $this->optionsCurrent
            : $this->debug->arrayUtil->pathGet($this->optionsCurrent, $what);
    }

    /**
     * Set "option" of value being dumped
     *
     * @param array|string $what name of value to set (or key/value array)
     * @param mixed        $val  value
     *
     * @return void
     */
    public function optionSet($what, $val = null)
    {
        if (\is_array($what)) {
            $this->optionsCurrent = \array_merge($this->optionsCurrent, $what);
            return;
        }
        $this->debug->arrayUtil->pathSet($this->optionsCurrent, $what, $val);
    }

    /**
     * Push options onto stack
     *
     * @param array $options Options to push onto stack
     *
     * @return void
     */
    public function optionStackPush(array $options)
    {
        if ($this->optionStack) {
            $options = \array_merge(\end($this->optionStack), $options);
        }
        $index = \count($this->optionStack);
        $this->optionStack[] = $options;
        $this->optionsCurrent = &$this->optionStack[$index];
    }

    /**
     * Pop options off of stack
     *
     * @return void
     */
    public function optionStackPop()
    {
        \array_pop($this->optionStack);
        $this->optionsCurrent = &$this->optionStack[\count($this->optionStack) - 1];
    }

    /**
     * Build character regex
     *
     * @return string
     */
    private function buildCharRegex()
    {
        $charList = '[' . \implode(\array_keys($this->charData)) . ']';
        $charControl = '[^\P{C}\r\n\t]';   // \p{C} includes \r, \n, & \t
        $charSeparator = '[^\P{Z} ]';      // \p{Z} includes space (but not \r, \n, & \t)

        // remove chars that are covered via character properties regexs
        $charList = \preg_replace('/(' . $charControl . '|' . $charSeparator . ')/u', '', $charList);

        return '/(' . $charList . '|' . $charControl . '|' . $charSeparator . ')/u';
    }

    /**
     * Dump value using current options
     *
     * @param mixed $val Value to dump
     *
     * @return mixed
     */
    protected function doDump($val)
    {
        $type = $this->optionsCurrent['type'];
        $method = 'dump' . \ucfirst($type);
        return $val instanceof Abstraction
            ? $this->dumpAbstraction($val)
            : $this->{$method}($val);
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
        $this->optionSet($opts);
        if (\method_exists($this, $method) === false) {
            $event = $this->debug->publishBubbleEvent(Debug::EVENT_DUMP_CUSTOM, new Event(
                $abs,
                array(
                    'return' => '',
                    'valDumper' => $this,
                )
            ));
            $this->optionSet('typeMore', $abs['typeMore']);
            return $event['return'];
        }
        return \in_array($type, $this->simpleTypes, true)
            ? $this->{$method}($abs['value'], $abs)
            : $this->{$method}($abs);
    }

    /**
     * Get dump options for abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return array<string,mixed>
     */
    private function dumpAbstractionOpts(Abstraction $abs)
    {
        $opts = $this->optionGet();
        foreach (\array_keys($opts) as $k) {
            // Note:  this has side-effect of adding the key to to the abstraction
            if ($abs[$k] !== null) {
                $opts[$k] = $abs[$k];
            }
        }
        return $opts;
    }

    /**
     * Dump array
     *
     * @param array            $array array to be dumped
     * @param Abstraction|null $abs   (optional) full abstraction
     *
     * @return array|string
     */
    abstract protected function dumpArray(array $array, $abs = null);

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
     *
     * @return bool|string
     */
    abstract protected function dumpBool($val);

    /**
     * Dump float value
     *
     * @param float|int        $val float value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return float|string
     */
    abstract protected function dumpFloat($val, $abs = null);

    /**
     * Dump integer value
     *
     * @param int              $val integer value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return int|string
     */
    abstract protected function dumpInt($val, $abs = null);

    /**
     * Dump null value
     *
     * @return null|string
     */
    abstract protected function dumpNull();

    /**
     * Dump object
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string|array
     */
    abstract protected function dumpObject(ObjectAbstraction $abs);

    /**
     * Dump string
     *
     * @param string           $val string value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string
     */
    abstract protected function dumpString($val, $abs = null);

    /**
     * Escape hex and unicode escape sequences.
     * This allows us to differentiate between '\u{03c5}' and a replaced "\u{03c5}"
     *
     * @param string $val string value
     *
     * @return string
     */
    protected function escapeEscapeSequences($val)
    {
        return \preg_replace('/\\\\(x[0-1A-Fa-f]{1,2}|u\{[0-1A-Fa-f]+\})/', '\\\\$1', $val);
    }

    /**
     * Get dump options
     *
     * @param mixed $val  value being dumped
     * @param array $opts options for string values
     *
     * @return array<string,mixed>
     */
    protected function getPerValueOptions($val, $opts)
    {
        $opts = \array_merge(array(
            'type' => null,
            'typeMore' => null,
        ), $opts);
        if ($opts['type'] === null) {
            list($opts['type'], $opts['typeMore']) = $this->debug->abstracter->type->getType($val);
        }
        return $opts;
    }

    /**
     * Split identifier into classname, operator, & name.
     *
     * classname may be namespace\classname
     * identifier = classname, constant function, or property
     *
     * @param string|array $val  classname or classname(::|->)name (method/property/const)
     * @param string       $what ("classname"), "const", or "method"
     *
     * @return array
     */
    protected function parseIdentifier($val, $what = 'className')
    {
        $parts = \array_fill_keys(['classname', 'name', 'namespace', 'operator'], '');
        $parts['classname'] = $val;
        $matches = array();
        if (\is_array($val)) {
            $parts['classname'] = $val[0];
            $parts['operator'] = '::';
            $parts['name'] = $val[1];
        } elseif (\preg_match('/^(.+)(::|->)(.+)$/', $val, $matches)) {
            $parts['classname'] = $matches[1];
            $parts['operator'] = $matches[2];
            $parts['name'] = $matches[3];
        } elseif (\in_array($what, ['const', 'method', 'function'], true)) {
            \preg_match('/^(.+\\\\)?(.+)$/', $val, $matches);
            $parts['classname'] = '';
            $parts['namespace'] = $matches[1];
            $parts['name'] = $matches[2];
        }
        return $parts;
    }
}
