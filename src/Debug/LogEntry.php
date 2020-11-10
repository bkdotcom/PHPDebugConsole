<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\PubSub\Event;

/**
 * Represents a log entry
 */
class LogEntry extends Event
{

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
            'args' => $args ?: array(),
            'meta' => $meta,
            'numArgs' => 0,     // number of non-meta aargs passed (does not include added default values)
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
                $this->values['meta'][$k] = $args[$k];
                unset($args[$k]);
            }
            $this->values['args'] = \array_values($args + $argsMore);
        }
        $this->setMeta($metaExtracted);
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
                unset($this->values['meta'][$key]);
                return;
            }
            $meta = array($key => $val);
        }
        $meta = \array_merge($this->values['meta'], $meta);
        $this->values['meta'] = $meta;
        if (\array_key_exists('channel', $meta)) {
            $channel = $meta['channel'];
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
}
