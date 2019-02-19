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
     * @param Debug  $debug       Debug instance (event subject)
     * @param string $method      Debug method
     * @param array  $args        arguments passed to method
     * @param array  $meta        meta values
     * @param array  $defaultArgs default arguments
     * @param array  $argsToMeta  move specified keys to meta
     */
    public function __construct(Debug $debug, $method, $args = array(), $meta = array(), $defaultArgs = array(), $argsToMeta = array())
    {
        $this->subject = $debug;
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
     * Create log entry
     *
     * @param Debug  $debug  Debug instance
     * @param string $method debug method
     * @param array  $args   method arguments
     * @param array  $meta   meta values
     *
     * @return LogEntry
     */
    /*
    public static function create(Debug $debug, $method, $args = array(), $meta = array())
    {
        return new static($debug, array(
            'method' => $method,
            'args' => $args,
            'meta' => \array_merge(array(
                'channel' => $debug->cfg['channel']
            ), $meta),
        ));
    }
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
    public static function metaExtract(&$array)
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

    /**
     * Move meta-data args to meta
     *
     * @param array $defaultArgs default argv values (key->value array)
     * @param array $argsToMeta  arg keys to move to meta
     *
     * @return void
     */
    /*
    public function moveMeta($defaultArgs, $argsToMeta)
    {
        $args = \array_slice($this->values['args'], 0, \count($defaultArgs));
        $args = \array_combine(
            \array_keys($defaultArgs),
            \array_replace(\array_values($defaultArgs), $args)
        );
        foreach ($argsToMeta as $argk => $metak) {
            if (\is_int($argk)) {
                $argk = $metak;
            }
            $this->values['meta'][$metak] = $args[$argk];
            unset($args[$argk]);
        }
        $this->values['args'] = \array_values($args);
    }
    */
}
