<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\PubSub\Event;
use bdk\Debug;

/**
 * Error object
 */
class LogEntry extends Event
{

    /**
     * Construct a log entry
     *
     * @param Debug|OutputInterface $subject     Debug instance or OutputInterface
     * @param string                $method      Debug method
     * @param array                 $args        arguments passed to method
     * @param array                 $meta        meta values
     * @param array                 $defaultArgs default arguments
     * @param array                 $argsToMeta  move specified keys to meta
     */
    public function __construct($subject, $method, $args = array(), $meta = array(), $defaultArgs = array(), $argsToMeta = array())
    {
        $this->subject = $subject;
        $this->values = array(
            'method' => $method,
            'args' => $args,
            'meta' => $meta,
            'appendLog' => true,
            'return' => null,
        );
        $metaExtracted = $this->metaExtract($this->values['args']);
        if ($defaultArgs) {
            $args = \array_slice($this->values['args'], 0, \count($defaultArgs));
            $args = \array_combine(
                \array_keys($defaultArgs),
                \array_replace(\array_values($defaultArgs), $args)
            );
            foreach ($argsToMeta as $k) {
                $this->values['meta'][$k] = $args[$k];
                unset($args[$k]);
            }
            $this->values['args'] = \array_values($args);
        }
        $this->values['meta'] = \array_merge($this->values['meta'], $metaExtracted);
    }

    /**
     * Return an array containing method, args, & meta
     *
     * @return array
     */
    public function export()
    {
        return array(
            'method' => $this->values['method'],
            'args' => $this->values['args'],
            'meta' => $this->values['meta'],
        );
    }

    /**
     * Get meta value
     *
     * @param string $key     key to get
     * @param mixed  $default (null) value to get
     *
     * @return mixed
     */
    public function getMeta($key, $default = null)
    {
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
        if (\is_array($key)) {
            $this->values['meta'] = \array_merge($this->values['meta'], $key);
        } elseif ($val === null) {
            unset($this->values['meta'][$key]);
        } else {
            $this->values['meta'][$key] = $val;
        }
    }

    /**
     * Remove meta values from array
     *
     * @param array $array array such as an argument array
     *
     * @return array meta values
     */
    private static function metaExtract(&$array)
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
        return $meta;
    }
}
