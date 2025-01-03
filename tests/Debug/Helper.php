<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler\Error;
use bdk\PubSub\ValueStore;

/**
 *
 */
class Helper
{
    public static function backtrace($limit = 0, $return = false)
    {
        $backtrace = $limit
            ? \array_slice(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0, $limit + 1)
            : \array_slice(\debug_backtrace(), 0, -8);
        $backtrace =  \array_map(static function ($frame) {
            if (isset($frame['args'])) {
                $frame['args'] = self::backtraceArgs($frame['args']);
            }
            if (isset($frame['object'])) {
                $frame['object'] = \get_class($frame['object']);
            }
            return $frame;
        }, $backtrace);
        if ($return) {
            return $backtrace;
        }
        self::stderr($backtrace);
    }

    /**
     * Arrayify abstractions
     * sort abstract values and meta values for consistency
     *
     * @param mixed $val args or value
     *
     * @return mixed
     */
    public static function crate($val, $mergeWithClass = false)
    {
        if ($val instanceof Abstraction) {
            $val = $mergeWithClass
                ? $val->getValues() + array('debug' => Abstracter::ABSTRACTION)
                : $val->jsonSerialize();
            \ksort($val);
        }
        if (\is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = self::crate($v, $mergeWithClass);
            }
        }
        return $val;
    }

    /**
     * Convert data log log entries into simple arrays
     *
     * @param array $data           log data
     * @param bool  $withKeys       whether log entries should be returned with keys or as a list
     * @param bool  $dropEmptyMeta  whether to omit meta if empty
     * @param bool  $mergeWithClass whether to merge with class values
     *
     * @return array
     */
    public static function deObjectifyData($data, $withKeys = true, $dropEmptyMeta = false, $mergeWithClass = false)
    {
        if ($data instanceof LogEntry) {
            return self::logEntryToArray($data, $withKeys, $dropEmptyMeta, $mergeWithClass);
        }
        if ($data instanceof Abstraction) {
            return self::crate($data, $mergeWithClass);
        }
        if ($data instanceof ValueStore) {
            $data = $data->getValues();
            \ksort($data);
            return $data;
        }
        if (\is_array($data) === false) {
            return $data;
        }
        foreach ($data as $i => $v) {
            $data[$i] = self::deObjectifyData($v, $withKeys, $dropEmptyMeta, $mergeWithClass);
        }
        return $data;
    }

    public function resetContainerValue($name)
    {
        $debug = Debug::getInstance();
        $container = \bdk\Debug\Utility\Reflection::propGet($debug, 'container');
        $invoked = \bdk\Debug\Utility\Reflection::propGet($container, 'invoked');
        if (isset($invoked[$name])) {
            $raw = \bdk\Debug\Utility\Reflection::propGet($container, 'raw');
            $values = \bdk\Debug\Utility\Reflection::propGet($container, 'values');
            $values[$name] = $raw[$name];
            unset($invoked[$name]);
            \bdk\Debug\Utility\Reflection::propSet($container, 'invoked', $invoked);
            \bdk\Debug\Utility\Reflection::propSet($container, 'raw', $raw);
            \bdk\Debug\Utility\Reflection::propSet($container, 'values', $values);
        }
    }

    /**
     * convert log entry to array
     *
     * @param LogEntry $logEntry LogEntry instance
     * @param bool     $withKeys Whether to return key => value or just list
     *
     * @return array|null
     */
    public static function logEntryToArray($logEntry, $withKeys = true, $dropEmptyMeta = false, $mergeWithClass = false)
    {
        if (\is_array($logEntry) && \array_keys($logEntry) === array('method','args','meta')) {
            return $logEntry;
        }
        if (!$logEntry || !($logEntry instanceof LogEntry)) {
            return null;
        }
        $return = $logEntry->export();
        $return['args'] = self::crate($return['args'], $mergeWithClass);
        \ksort($return['meta']);
        if ($dropEmptyMeta && empty($return['meta'])) {
            unset($return['meta']);
        }
        if (!$withKeys) {
            return \array_values($return);
        }
        return $return;
    }

    private static function backtraceArgs($args, $depth = 0)
    {
        foreach ($args as $i => $arg) {
            $type = \strtolower(\gettype($arg));
            if ($type === 'array') {
                $count = \count($arg);
                if ($count === 2 && \array_keys($arg) === [0, 1] && \is_object($arg[0]) && \is_string($args[1])) {
                    $arg = 'callable: [' . \get_class($arg[0]) . ', ' . $arg[1] . ']';
                } elseif ($depth < 2) {
                    $arg = self::backtraceArgs($arg, $depth + 1);
                } else {
                    $arg = 'array(' . $count . ')';
                }
            } elseif ($type === 'object') {
                if ($arg instanceof Error) {
                    $arg = 'Error: ' . $arg['message'] . ' @ ' . $arg['file'] . ':' . $arg['line'];
                } elseif ($arg instanceof LogEntry) {
                    $arg = 'LogEntry: ' . $arg['method'];
                } else {
                    $arg = 'object: ' . \get_class($arg);
                }
            } elseif ($type === 'string') {
                // $arg = '"' . \print_r($arg, true) . '"';
                continue;
            } elseif (\in_array($type, array('boolean', 'double', 'integer', 'null'), true)) {
                // $arg = \json_encode($arg);
                continue;
            } else {
                $arg = $type;
            }
            $args[$i] = $arg;
        }
        return $args;
    }

    /**
     * Util to output to console / help in creation of tests
     *
     * @return void
     */
    public static function stderr()
    {
        \call_user_func_array(array('bdk\Debug', 'varDump'), \func_get_args());
    }

    private static function varDump($val)
    {
        $iniKey = 'xdebug.var_display_max_depth';
        $iniWas = \ini_get($iniKey);
        \ini_set($iniKey, 8);
        \ob_start();
        \var_dump($val);
        \ini_set($iniKey, $iniWas);
        $new = \ob_get_clean();
        $new = \preg_replace('/^<pre[^>]*>\n(.*)<\/pre>$/is', '$1', $new);
        $new = \preg_replace('/^(\S+:\d+[\r\n]|<small>.*?<\/small>)/s', '', $new);
        $new = \preg_replace('/=>\n\s+/', '=> ', $new);
        $new = \preg_replace('/(string\(\d+\)|<small>string<\/small>) /', '', $new);
        $new = \preg_replace('/<i>\(length=\d+\)<\/i>/', '', $new);
        $new = \preg_replace('/array\(0\) \{\n\s*\}/', 'array()', $new);
        return \trim($new);
    }
}
