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

namespace bdk\Debug;

use bdk\Debug;
use bdk\PubSub\Event;
use JsonSerializable;

/**
 * Represents a log entry
 */
class LogEntry extends Event implements JsonSerializable
{
    /**
     * Regular expression for determining if argument contains "substitutions"
     *
     * @var string
     */
    public $subRegex = '/%
        (?:
            [coO]|           # c: css, o: obj with max info, O: obj w generic info
            [+-]?            # sign specifier
            (?:[ 0]|\'.{1})? # padding specifier
            -?               # alignment specifier
            \d*              # width specifier
            (?:\.\d+)?       # precision specifier
            [difs]
        )
        /x';

    /**
     * Construct a log entry
     *
     * meta precedence low to high
     *   $meta
     *   $argsToMeta
     *   meta extracted from $args
     *
     * @param Debug  $subject     Debug instance
     * @param string $method      Debug method
     * @param array  $args        arguments passed to method (may include meta args)
     * @param array  $meta        default meta values
     * @param array  $defaultArgs default arguments (key/value array)
     * @param array  $argsToMeta  move specified keys to meta
     */
    public function __construct(Debug $subject, $method, $args = array(), $meta = array(), $defaultArgs = array(), $argsToMeta = array())
    {
        $this->subject = $subject;
        $this->values = array(
            'method' => $method,
            'args' => $args,
            'meta' => $meta,
            'numArgs' => 0,     // number of initial non-meta aargs passed (does not include added default values)
            'appendLog' => true,
            'return' => null,
        );
        $metaExtracted = $this->metaExtract($this->values['args']);
        $this->mergeDefaultArgs($defaultArgs, $argsToMeta);
        $this->values['meta'] = \array_merge($this->values['meta'], $metaExtracted);
        $this->onSet($this->values);
    }

    /**
     * Do the logEntry arguments appear to have string substitutions
     *
     * @return bool
     */
    public function containsSubstitutions()
    {
        $args = $this->values['args'];
        if (\count($args) < 2 || \is_string($args[0]) === false) {
            return false;
        }
        return \preg_match($this->subRegex, $args[0]) === 1;
    }

    /**
     * "crate" all logEntry arguments
     *
     * @return void
     */
    public function crate()
    {
        $firstArgVal = \reset($this->values['args']);
        if ($this->subject->php->isThrowable($firstArgVal)) {
            $exception = $firstArgVal;
            $this->values['args'][0] = $exception->getMessage();
            $this->setMeta(array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->subject->backtrace->get(null, 0, $exception),
            ));
        }
        foreach ($this->values['args'] as $i => $val) {
            $this->values['args'][$i] = $this->subject->abstracter->crate($val, $this->values['method']);
        }
    }

    /**
     * Return an array containing method, args, & meta
     *
     * @return array
     */
    public function export()
    {
        $return = array(
            'method' => $this->values['method'],
            'args' => $this->values['args'],
            'meta' => $this->values['meta'],
        );
        if ($this->subject->parentInstance) {
            $return['meta']['channel'] = $this->getChannelName();
        }
        return $return;
    }

    /**
     * Return channel name
     *
     * shortcut for getSubject()->getCfg('channelName')
     *
     * @return string
     */
    public function getChannelName()
    {
        return $this->subject->getCfg('channelName', Debug::CONFIG_DEBUG);
    }

    /**
     * Get meta value
     *
     * @param string $key     key to get
     *                        if not passed, return all meta values (no different than $logEntry['meta'])
     * @param mixed  $default (null) value to get
     *
     * @return mixed
     */
    public function getMeta($key = null, $default = null)
    {
        if ($key === null) {
            return $this->values['meta'];
        }
        return \array_key_exists($key, $this->values['meta'])
            ? $this->values['meta'][$key]
            : $default;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->export();
    }

    /**
     * Set meta value(s)
     * Value(s) get merged with existing values
     *
     * @param mixed $mixed (string) key or (array) key/value array
     * @param mixed $val   value if updating a single key
     *
     * @return void
     */
    public function setMeta($mixed, $val = null)
    {
        if (\is_array($mixed) === false) {
            if ($val === null) {
                /** @psalm-suppress EmptyArrayAccess */
                unset($this->values['meta'][$mixed]);
                return;
            }
            $mixed = array($mixed => $val);
        }
        $this->setValue('meta', \array_merge($this->values['meta'], $mixed));
    }

    /**
     * Merge default args with args
     * Move args listed in argsToMeta to meta
     *
     * @param array $defaultArgs default arguments (key=>value array)
     * @param array $argsToMeta  move specified keys to meta
     *
     * @return void
     */
    private function mergeDefaultArgs($defaultArgs, $argsToMeta)
    {
        if (!$defaultArgs) {
            return;
        }
        $count = \count($defaultArgs);
        $args = \array_slice($this->values['args'], 0, $count);
        $argsMore = \array_slice($this->values['args'], $count);
        $args = \array_combine(
            \array_keys($defaultArgs),
            \array_replace(\array_values($defaultArgs), $args)
        );
        foreach ($argsToMeta as $k) {
            $this->values['meta'][$k] = $args[$k];
            unset($args[$k]);
        }
        $this->values['args'] = \array_values($args + $argsMore);
    }

    /**
     * Remove meta values from array
     *
     * @param array $array array such as an argument array
     *
     * @return array meta values
     */
    private function metaExtract(&$array)
    {
        $meta = array();
        foreach ($array as $i => $v) {
            if (\is_array($v) && isset($v['debug']) && $v['debug'] === Debug::META) {
                unset($v['debug']);
                $meta = \array_merge($meta, $v);
                unset($array[$i]);
            }
        }
        $array = \array_values($array); // update $array passed by reference
        $this->values['numArgs'] = \count($array);
        return $meta;
    }

    /**
     * Make sure attribs['class'] is an array
     *
     * @param array $values key => values being set
     *
     * @return void
     */
    protected function onSet($values = array())
    {
        if (isset($values['meta']) === false) {
            return;
        }
        if (isset($values['meta']['appendGroup'])) {
            $this->values['meta']['appendGroup'] = $this->subject->html->sanitizeId($values['meta']['appendGroup']);
        }
        $this->onSetMetaAttribs();
        if (\array_key_exists('channel', $values['meta'])) {
            $this->onSetMetaChannel();
        }
    }

    /**
     * if we have 'attribs' make sure attribs['class'] is set and is an array
     *
     * @return void
     */
    private function onSetMetaAttribs()
    {
        $meta = $this->values['meta'];
        if (isset($meta['id'])) {
            // move 'id' to attribs
            $meta['attribs']['id'] = $meta['id'];
            unset($meta['id']);
        }
        if (isset($meta['attribs']) === false) {
            return;
        }
        if (!isset($meta['attribs']['class'])) {
            $meta['attribs']['class'] = array();
        } elseif (\is_string($meta['attribs']['class'])) {
            $meta['attribs']['class'] = \explode(' ', $meta['attribs']['class']);
        }
        if (isset($meta['attribs']['id'])) {
            $meta['attribs']['id'] = $this->subject->html->sanitizeId($meta['attribs']['id']);
        }
        $this->values['meta'] = $meta;
    }

    /**
     * Handle meta['channel']
     * Set this->subject from channel value
     *
     * @return void
     */
    private function onSetMetaChannel()
    {
        $channel = $this->values['meta']['channel'];
        unset($this->values['meta']['channel']);
        if ($channel === null) {
            $this->subject = $this->subject->rootInstance;
            return;
        }
        $this->subject = $this->subject->parentInstance
            ? $this->subject->parentInstance->getChannel($channel)
            : $this->subject->getChannel($channel);
    }
}
