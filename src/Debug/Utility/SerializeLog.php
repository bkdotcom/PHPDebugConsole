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

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\Php;
use bdk\Debug\Utility\StringUtil;

/**
 * Serialize / compress / base64 encode log data
 */
class SerializeLog
{
    protected static $debug;
    protected static $isLegacyData = false;

    /**
     * Import the config and data into the debug instance
     *
     * @param array $data  Unpacked / Unserialized log data
     * @param Debug $debug (optional) Debug instance
     *
     * @return Debug
     */
    public static function import($data, Debug $debug = null)
    {
        if (!$debug) {
            $debug = new Debug();
        }
        self::$isLegacyData = \version_compare($data['version'], '3.0', '<');
        self::$debug = $debug;
        // set config for any channels already present in debug
        foreach (\array_intersect_key($debug->getChannels(true, true), $data['config']['channels']) as $fqn => $channel) {
            $channel->setCfg($data['config']['channels'][$fqn]);
        }
        $debug->setCfg($data['config']);
        unset($data['config'], $data['version']);
        foreach (array('alerts','log','logSummary') as $cat) {
            $data[$cat] = self::importGroup($cat, $data[$cat]);
        }
        foreach ($data as $k => $v) {
            $debug->data->set($k, $v);
        }
        return $debug;
    }

    /**
     * Serialize log for emailing
     *
     * @param Debug $debug debug instance
     *
     * @return string
     */
    public static function serialize(Debug $debug)
    {
        $data = \array_merge(static::serializeGetData($debug), array(
            'config' => static::serializeGetConfig($debug),
            'version' => Debug::VERSION,
        ));
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
     * @param string $str serialized log data
     *
     * @return array|false
     */
    public static function unserialize($str)
    {
        $data = false;
        $str = self::extractLog($str);
        $str = self::unserializeDecode($str);
        if ($str) {
            $data = Php::unserializeSafe($str, array(
                'bdk\\Debug\\Abstraction\\Abstraction',
            ));
        }
        if (!$data) {
            return false;
        }
        $data = \array_merge(array(
            'version' => '2.3',
            'config' => array(
                'channels' => array(),
            ),
        ), $data);
        if (isset($data['rootChannel'])) {
            $data['config']['channelName'] = $data['rootChannel'];
            $data['config']['channels'] = array();
            unset($data['rootChannel']);
        }
        return $data;
    }

    /**
     * Extract serialized/encoded log data from between "START DEBUG" & "END DEBUG"
     *
     * @param string $str string containing serialized log
     *
     * @return string
     */
    private static function extractLog($str)
    {
        $strStart = 'START DEBUG';
        $strEnd = 'END DEBUG';
        $regex = '/' . $strStart . '[\r\n]+(.+)[\r\n]+' . $strEnd . '/s';
        $matches = array();
        if (\preg_match($regex, $str, $matches)) {
            $str = $matches[1];
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
    private static function importLogEntry($vals)
    {
        $vals = \array_replace(array('', array(), array()), $vals);
        if (self::$isLegacyData) {
            $vals[1] = self::importLegacy($vals[1]);
        }
        $logEntry = new LogEntry(self::$debug, $vals[0], $vals[1], $vals[2]);
        if (self::$isLegacyData && $vals[0] === 'table') {
            self::$debug->methodTable->doTable($logEntry);
        }
        return $logEntry;
    }

    /**
     * "unserialize" log data
     *
     * @param string $cat  ('alerts'|'log'|'logSummary')
     * @param array  $data data to unserialize
     *
     * @return array
     */
    private static function importGroup($cat, $data)
    {
        foreach ($data as $i => $val) {
            if ($cat !== 'logSummary') {
                $data[$i] = self::importLogEntry($val);
                continue;
            }
            foreach ($val as $priority => $val2) {
                $data[$i][$priority] = self::importLogEntry($val2);
            }
        }
        return $data;
    }

    /**
     * Convert pre 3.0 serialized log entry args to 3.0
     *
     * Prior to to v3.0, abstractions were stored as an array
     * Find these arrays and convert them to Abstraction objects
     *
     * @param array $vals values orproperties
     *
     * @return array
     */
    private static function importLegacy($vals)
    {
        foreach ($vals as $k => $v) {
            if (\is_array($v) === false) {
                continue;
            }
            if (!isset($v['debug']) || $v['debug'] !== Abstracter::ABSTRACTION) {
                $vals[$k] = self::importLegacy($v);
                continue;
            }
            // we are an abstraction
            $type = $v['type'];
            unset($v['debug'], $v['type']);
            if ($type === Abstracter::TYPE_OBJECT) {
                $v['properties'] = self::importLegacy($v['properties']);
                $v = self::importLegacyObj($v);
            }
            $vals[$k] = new Abstraction($type, $v);
        }
        return $vals;
    }

    /**
     * Convert legacy object abstraction data
     *
     * @param array $abs Abstraction values
     *
     * @return array
     */
    private static function importLegacyObj($abs)
    {
        $abs = AbstractObject::buildObjValues($abs);
        if (isset($abs['collectMethods'])) {
            if ($abs['collectMethods'] === false) {
                $abs['cfgFlags'] &= ~AbstractObject::METHOD_COLLECT;
            }
            unset($abs['collectMethods']);
        }
        $baseMethodInfoRef = new \ReflectionProperty('bdk\Debug\Abstraction\AbstractObjectMethods', 'baseMethodInfo');
        $baseMethodInfoRef->setAccessible(true);
        $baseMethodInfo = $baseMethodInfoRef->getValue();
        foreach ($abs['methods'] as $name => $meth) {
            $abs['methods'][$name] = \array_merge($baseMethodInfo, $meth);
        }
        $basePropInfoRef = new \ReflectionProperty('bdk\Debug\Abstraction\AbstractObjectProperties', 'basePropInfo');
        $basePropInfoRef->setAccessible(true);
        $basePropInfo = $basePropInfoRef->getValue();
        foreach ($abs['properties'] as $name => $prop) {
            $abs['properties'][$name] = \array_merge($basePropInfo, $prop);
        }
        return $abs;
    }

    /**
     * Get debug configuration
     *
     * @param Debug $debug Debug instance
     *
     * @return array
     */
    private static function serializeGetConfig(Debug $debug)
    {
        $rootInstance = $debug->rootInstance;
        $channelNameRoot = $rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG);
        $channels = \array_map(static function (Debug $channel) use ($channelNameRoot) {
            $channelName = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
            return array(
                'channelIcon' => $channel->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                'channelShow' => $channel->getCfg('channelShow', Debug::CONFIG_DEBUG),
                'channelSort' => $channel->getCfg('channelSort', Debug::CONFIG_DEBUG),
                'nested' => \strpos($channelName, $channelNameRoot . '.') === 0,
            );
        }, $rootInstance->getChannels(true, true));
        return array(
            'channelIcon' => $rootInstance->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            'channelName' => $channelNameRoot,
            'channels' => $channels,
            'logRuntime' => $rootInstance->getCfg('logRuntime', Debug::CONFIG_DEBUG),
        );
    }

    /**
     * Get debug log data
     *
     * @param Debug $debug Debug instance
     *
     * @return array
     */
    private static function serializeGetData(Debug $debug)
    {
        $data = \array_intersect_key($debug->data->get(), \array_flip(array(
            'alerts',
            'log',
            'logSummary',
            'requestId',
            'runtime',
        )));
        foreach (array('alerts','log','logSummary') as $cat) {
            $data[$cat] = self::serializeGroup($data[$cat]);
        }
        return $data;
    }

    /**
     * "serialize" log data
     *
     * @param array $data data to serialize
     *
     * @return array
     */
    private static function serializeGroup($data)
    {
        foreach ($data as $i => $val) {
            if (!($val instanceof LogEntry)) {
                $data[$i] = static::serializeGroup($val);
                continue;
            }
            $logEntryArray = $val->export();
            if (empty($logEntryArray['meta'])) {
                unset($logEntryArray['meta']);
            }
            $data[$i] = \array_values($logEntryArray);
        }
        return $data;
    }

    /**
     * base64 decode and gzinflate
     *
     * @param string $str compressed / encoded log data
     *
     * @return string|false
     */
    private static function unserializeDecode($str)
    {
        $str = StringUtil::isBase64Encoded($str)
            ? \base64_decode($str, true)
            : false;
        if ($str && \function_exists('gzinflate')) {
            $strInflated = \gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        return $str;
    }
}
