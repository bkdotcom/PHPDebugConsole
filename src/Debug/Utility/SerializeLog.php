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

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;

/**
 * Serialize / compress / base64 encode log data
 */
class SerializeLog
{

    protected static $debug;
    protected static $isLegacyData = false;

    /**
     * serialize log for emailing
     *
     * @param array $data log data to serialize
     *
     * @return string
     */
    public static function serialize($data)
    {
        foreach (array('alerts','log','logSummary') as $cat) {
            $data[$cat] = self::serializeCategory($cat, $data[$cat]);
        }
        $str = \serialize($data);
        if (\function_exists('gzdeflate')) {
            $str = \gzdeflate($str);
        }
        $str = \chunk_split(\base64_encode($str), 124);
        return "START DEBUG\n"
            . $str    // chunk_split appends a "\r\n"
            . 'END DEBUG';
    }

    /**
     * Use to unserialize the log serialized by emailLog
     *
     * @param string $str   serialized log data
     * @param Debug  $debug (optional) Debug instance
     *
     * @return array|false
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public static function unserialize($str, Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::getInstance();
        }
        self::$debug = $debug;
        $strStart = 'START DEBUG';
        $strEnd = 'END DEBUG';
        $regex = '/' . $strStart . '[\r\n]+(.+)[\r\n]+' . $strEnd . '/s';
        $matches = array();
        if (\preg_match($regex, $str, $matches)) {
            $str = $matches[1];
        }
        $str = self::unserializeDecode($str);
        $data = self::unserializeSafe($str, array(
            'bdk\\Debug\\Abstraction\\Abstraction',
        ));
        return self::unserializeLogData($data);
    }

    /**
     * "serialize" log data
     *
     * @param string $cat  ('alerts'|'log'|'logSummary')
     * @param array  $data data to serialize
     *
     * @return array
     */
    private static function serializeCategory($cat, $data)
    {
        foreach ($data as $i => $val) {
            if ($cat !== 'logSummary') {
                $data[$i] = \array_values($val->export());
                continue;
            }
            foreach ($val as $i2 => $val2) {
                $data[$i][$i2] = \array_values($val2->export());
            }
        }
        return $data;
    }

    /**
     * "unserialize" log data
     *
     * @param string $cat  ('alerts'|'log'|'logSummary')
     * @param array  $data data to unserialize
     *
     * @return array
     */
    private static function unserializeCategory($cat, $data)
    {
        foreach ($data as $i => $val) {
            if ($cat !== 'logSummary') {
                $data[$i] = self::unserializeLogEntry($val);
                continue;
            }
            foreach ($val as $priority => $val2) {
                $data[$i][$priority] = self::unserializeLogEntry($val2);
            }
        }
        return $data;
    }

    /**
     * for unserialized log, Convert logEntry arrays to log entry objects
     *
     * @param array $data unserialized log data
     *
     * @return array log data
     */
    private static function unserializeLogData($data)
    {
        $dataVer = isset($data['version'])
            ? $data['version']
            : '2.3' ;
        self::$isLegacyData = \version_compare($dataVer, '3.0', '<');
        foreach (array('alerts','log','logSummary') as $cat) {
            $data[$cat] = self::unserializeCategory($cat, $data[$cat]);
        }
        return $data;
    }

    /**
     * base64 decode and gzinflate
     *
     * @param string $str compressed / encoded log data
     *
     * @return string
     */
    private static function unserializeDecode($str)
    {
        $str = self::$debug->stringUtil->isBase64Encoded($str)
            ? \base64_decode($str)
            : false;
        if ($str && \function_exists('gzinflate')) {
            $strInflated = \gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        return $str;
    }

    /**
     * Unserialzie Log entry
     *
     * @param array $vals method, args, & meta values
     *
     * @return LogEntry
     */
    private static function unserializeLogEntry($vals)
    {
        if (self::$isLegacyData) {
            $vals[1] = self::unserializeLogLegacy($vals[1]);
        }
        return new LogEntry(self::$debug, $vals[0], $vals[1], $vals[2]);
    }

    /**
     * Convert pre 3.0 serialized log entry args to 3.0
     *
     * Prior to to v3.0, abstractions were stored as an array
     * Find these arrays and convert them to Abstraction objects
     *
     * @param array $args log entry args (unserialized)
     *
     * @return array
     */
    private static function unserializeLogLegacy($args)
    {
        foreach ($args as $k => $v) {
            if (!\is_array($v)) {
                continue;
            }
            if (isset($v['debug']) && $v['debug'] === Abstracter::ABSTRACTION) {
                $type = $v['type'];
                unset($v['debug'], $v['type']);
                if ($type === Abstracter::TYPE_OBJECT) {
                    $v['properties'] = self::unserializeLogLegacy($v['properties']);
                }
                $args[$k] = new Abstraction($type, $v);
                continue;
            }
            $args[$k] = self::unserializeLogLegacy($v);
        }
        return $args;
    }

    /**
     * Unserialize while only allowing the specified classes to be unserialized
     *
     * @param string   $str            serialized string
     * @param string[] $allowedClasses allowed class names
     *
     * @return mixed
     */
    private static function unserializeSafe($str, $allowedClasses = array())
    {
        if (\version_compare(PHP_VERSION, '7.0', '>=')) {
            // 2nd param is PHP >= 7.0 (get a warning: unserialize() expects exactly 1 parameter, 2 given)
            return \unserialize($str, array(
                'allowed_classes' => $allowedClasses,
            ));
        }
        // There's a possibility this pattern may be found inside a string (false positive)
        $regex = '#[CO]:(\d+):"([\w\\\\]+)":\d+:#';
        \preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $set) {
            if (\strlen($set[2]) !== $set[1]) {
                continue;
            } elseif (!\in_array($set[2], $allowedClasses)) {
                return false;
            }
        }
        return \unserialize($str);
    }
}
