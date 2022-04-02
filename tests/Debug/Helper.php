<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\ArrayUtil;
use bdk\ErrorHandler\Error;
use ReflectionProperty;

/**
 *
 */
class Helper
{
    public static function backtrace($limit = 0)
    {
        $backtrace = $limit
            ? \array_slice(\debug_backtrace(0, $limit + 1), 1)
            : \array_slice(\debug_backtrace(0), 0, -8);
        return \array_map(function ($frame) {
            if (isset($frame['args'])) {
                $frame['args'] = self::backtraceArgs($frame['args']);
            }
            if (isset($frame['object'])) {
                $frame['object'] = \get_class($frame['object']);
            }
            return $frame;
        }, $backtrace);
        /*
        $str = \print_r($backtrace, true);
        $output = true || self::isCli()
            ? $str . "\n"
            : '<pre>' . \htmlspecialchars($str) . '</pre>';
        \fwrite(STDERR, $output);
        */
    }

    /**
     * Arrayify abstractions
     * sort abtract values and meta values for consistency
     *
     * @param mixed $val args or value
     *
     * @return mixed
     */
    public function crate($val)
    {
        if ($val instanceof Abstraction) {
            $val = $val->jsonSerialize();
            \ksort($val);
        }
        if (\is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = $this->crate($v);
            }
        }
        return $val;
    }

    /**
     * Convert data log log entries into simple arrays
     *
     * @param array $data     log data
     * @param bool  $withKeys whether log entries should be returned with keys or as a list
     *
     * @return array
     */
    public function deObjectifyData($data, $withKeys = true)
    {
        if ($data instanceof LogEntry) {
            return $this->logEntryToArray($data, $withKeys);
        }
        if ($data instanceof Abstraction) {
            return $this->crate($data);
        }
        if (\is_array($data) === false) {
            return $data;
        }
        foreach ($data as $i => $v) {
            $data[$i] = $this->deObjectifyData($v, $withKeys);
        }
        return $data;
    }

    /**
     * Get inaccessable property value via reflection
     *
     * @param object $obj  object instance
     * @param string $prop property name
     *
     * @return mixed
     */
    public static function getPrivateProp($obj, $prop)
    {
        $propRef = new ReflectionProperty($obj, $prop);
        $propRef->setAccessible(true);
        return $propRef->getValue($obj);
    }

    /**
     * Is script running from command line (or cron)?
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    public static function isCli()
    {
        $argv = isset($_SERVER['argv'])
            ? $_SERVER['argv']
            : null;
        $query = isset($_SERVER['QUERY_STRING'])
            ? $_SERVER['QUERY_STRING']
            : null;
        return $argv && \implode('+', $argv) !== $query;
    }

    /**
     * Set inaccessable property value via reflection
     *
     * @param object $obj  object instance
     * @param string $prop property name
     * @param mixed  $val  new value
     *
     * @return mixed
     */
    public static function setPrivateProp($obj, $prop, $val)
    {
        $propRef = new ReflectionProperty($obj, $prop);
        $propRef->setAccessible(true);
        \is_string($obj) || $propRef->isStatic()
            ? $propRef->setValue($val)
            : $propRef->setValue($obj, $val);
    }

    /**
     * convert log entry to array
     *
     * @param LogEntry $logEntry LogEntry instance
     * @param bool     $withKeys Whether to return key => value or just list
     *
     * @return array|null
     */
    public function logEntryToArray($logEntry, $withKeys = true)
    {
        if (\is_array($logEntry) && \array_keys($logEntry) === array('method','args','meta')) {
            return $logEntry;
        }
        if (!$logEntry || !($logEntry instanceof LogEntry)) {
            return null;
        }
        $return = $logEntry->export();
        // convert any abstractions to array via json_encode
        // $return['args'] = \json_decode(\json_encode($return['args']), true);
        $return['args'] = $this->crate($return['args']);
        \ksort($return['meta']);
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
                if ($count === 2 && \array_keys($arg) === [0, 1] && \is_object($arg[0])) {
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
                $arg = '"' . \print_r($arg, true) . '"';
            } elseif (\in_array($type, array('boolean', 'double', 'integer', 'null'))) {
                $arg = \json_encode($arg);
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
        $args = \array_map(function ($val) {
            $new = 'null';
            if ($val !== null) {
                $new = Debug::getInstance()->getDump('textAnsi')->valDumper->dump($val);
                Debug::getInstance()->getDump('textAnsi')->valDumper->setValDepth(0);
            }
            if (\json_last_error() !== JSON_ERROR_NONE) {
                $new = \var_export($val, true);
            }
            return $new;
        }, \func_get_args());
        $glue = \func_num_args() > 2
            ? ', '
            : ' = ';
        \fwrite(STDERR, \implode($glue, $args) . "\n");
    }
}
