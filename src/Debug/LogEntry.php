<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
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

    public $subRegex;

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
        $this->subRegex = '/%'
            . '(?:'
            . '[coO]|'               // c: css, o: obj with max info, O: obj w generic info
            . '[+-]?'                // sign specifier
            . '(?:[ 0]|\'.{1})?'     // padding specifier
            . '-?'                   // alignment specifier
            . '\d*'                  // width specifier
            . '(?:\.\d+)?'           // precision specifier
            . '[difs]'
            . ')'
            . '/';
        $this->values = array(
            'method' => $method,
            'args' => $args ?: array(),
            'meta' => array(),
            'numArgs' => 0,     // number of initial non-meta aargs passed (does not include added default values)
            'appendLog' => true,
            'return' => null,
        );
        $metaExtracted = $this->metaExtract($this->values['args']);
        if ($defaultArgs) {
            $count = \count($defaultArgs);
            $args = \array_slice($this->values['args'], 0, $count);
            $argsMore = \array_slice($this->values['args'], $count);
            $args = \array_combine(
                \array_keys($defaultArgs),
                \array_replace(\array_values($defaultArgs), $args)
            );
            foreach ($argsToMeta as $k) {
                $meta[$k] = $args[$k];
                unset($args[$k]);
            }
            $this->values['args'] = \array_values($args + $argsMore);
        }
        $this->values['meta'] = \array_merge($meta, $metaExtracted);
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
    public function jsonSerialize()
    {
        return $this->export();
    }

    /**
     * Set meta value(s)
     * Value(s) get merged with existing values
     *
     * @param mixed $key (string) key or (array) key/value array
     * @param mixed $val value if updating a single key
     *
     * @return void
     */
    public function setMeta($key, $val = null)
    {
        $meta = $key;
        if (!\is_array($key)) {
            if ($val === null) {
                /** @psalm-suppress EmptyArrayAccess */
                unset($this->values['meta'][$key]);
                return;
            }
            $meta = array($key => $val);
        }
        $meta = \array_merge($this->values['meta'], $meta);
        $this->setValue('meta', $meta);
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
        $array = \array_values($array);
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
        if (isset($values['meta']['attribs'])) {
            if (!isset($values['meta']['attribs']['class'])) {
                $this->values['meta']['attribs']['class'] = array();
            } elseif (\is_string($values['meta']['attribs']['class'])) {
                $this->values['meta']['attribs']['class'] = \explode(' ', $values['meta']['attribs']['class']);
            }
        }
        if (\array_key_exists('channel', $values['meta'])) {
            $channel = $values['meta']['channel'];
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
}
