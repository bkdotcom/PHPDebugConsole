<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\Php;
use bdk\Debug\Utility\StringUtil;

/**
 * Serialize / compress / base64 encode log data
 */
class SerializeLog
{
    /** @var Debug */
    protected static $debug;
    /** @var bool */
    protected static $isLegacyData = false;

    /**
     * Import the config and data into the debug instance
     *
     * @param array      $data  Unpacked / Unserialized log data
     * @param Debug|null $debug (optional) Debug instance
     *
     * @return Debug
     */
    public static function import(array $data, $debug = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($debug, 'bdk\Debug|null', 'debug');

        if (!$debug) {
            $debug = new Debug();
        }
        self::$isLegacyData = \version_compare($data['version'], '3.0', '<');
        self::$debug = $debug;
        // set config for any channels already present in debug
        foreach (\array_intersect_key($debug->getChannels(true, true), $data['config']['channels']) as $fqn => $channel) {
            $channel->setCfg($data['config']['channels'][$fqn], Debug::CONFIG_NO_RETURN);
        }
        $debug->setCfg($data['config'], Debug::CONFIG_NO_RETURN);
        unset($data['config'], $data['version']);
        foreach (['alerts', 'log', 'logSummary'] as $cat) {
            $data[$cat] = self::importGroup($cat, $data[$cat]);
        }
        foreach ($data as $k => $v) {
            $debug->data->set($k, $v);
        }
        return $debug;
    }

    /**
     * Serialize log
     *
     * @param array|Debug $data debug instance
     *
     * @return string
     */
    public static function serialize($data)
    {
        if ($data instanceof Debug) {
            $data = \array_merge(static::serializeGetData($data), array(
                'config' => static::serializeGetConfig($data),
                'version' => Debug::VERSION,
            ));
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
     * Unserialize log data serialized by emailLog
     *
     * @param string $str serialized log data
     *
     * @return array|false
     */
    public static function unserialize($str)
    {
        $str = self::extractLog($str);
        $str = self::unserializeDecodeAndInflate($str);
        $data = self::unserializeSafe($str);
        if (!$data) {
            return false;
        }
        $data = \array_merge(array(
            'config' => array(
                'channels' => array(),
            ),
            'version' => '2.3', // prior to 3.0, we didn't include version
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
        $matches = [];
        if (\preg_match($regex, $str, $matches)) {
            $str = $matches[1];
        }
        return $str;
    }

    /**
     * Unserialize Log entry
     *
     * @param array $vals method, args, & meta values
     *
     * @return LogEntry
     */
    private static function importLogEntry(array $vals)
    {
        $vals = \array_replace(['', array(), array()], $vals);
        $vals[1] = self::importLegacy($vals[1]);
        $logEntry = new LogEntry(self::$debug, $vals[0], $vals[1], $vals[2]);
        if (self::$isLegacyData && $vals[0] === 'table') {
            self::$debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
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
     * @param array $vals values or properties
     *
     * @return array
     */
    private static function importLegacy(array $vals)
    {
        return \array_map(static function ($val) {
            if (\is_array($val) === false) {
                return $val;
            }
            return isset($val['debug']) && $val['debug'] === Abstracter::ABSTRACTION
                ? self::importLegacyAbstraction($val)
                : self::importLegacy($val);
        }, $vals);
    }

    /**
     * Import legacy abstraction
     *
     * @param array<string,mixed> $absValues Abstraction values
     *
     * @return Abstraction
     */
    private static function importLegacyAbstraction(array $absValues)
    {
        $type = $absValues['type'];
        unset($absValues['debug'], $absValues['type']);
        return $type === Type::TYPE_OBJECT
            ? self::importLegacyObj($absValues)
            : new Abstraction($type, $absValues);
    }

    /**
     * Convert legacy object abstraction data
     *
     * @param array<string,mixed> $absValues Abstraction values
     *
     * @return ObjectAbstraction
     */
    private static function importLegacyObj(array $absValues)
    {
        $absValues['properties'] = self::importLegacy($absValues['properties']);
        $absValues = self::importLegacyObjConvert($absValues);
        $absValues = AbstractObject::buildValues($absValues);
        $absValues = ObjectAbstraction::unserializeBuildValues($absValues);

        $absValues['methods'] = \array_map(static function (array $methodInfo) {
            $methodInfo['phpDoc']['desc'] = $methodInfo['phpDoc']['description'];
            unset($methodInfo['phpDoc']['description']);
            return $methodInfo;
        }, $absValues['methods']);

        $valueStore = self::$debug->abstracter->abstractObject->definition->getValueStoreDefault();
        return new ObjectAbstraction($valueStore, $absValues);
    }

    /**
     * Convert values
     *
     * @param array<string,mixed> $absValues Object abstraction values
     *
     * @return array<string,mixed>
     */
    private static function importLegacyObjConvert($absValues)
    {
        if (isset($absValues['collectMethods'])) {
            if ($absValues['collectMethods'] === false) {
                $absValues['cfgFlags'] &= ~AbstractObject::METHOD_COLLECT;
            }
            unset($absValues['collectMethods']);
        }
        if (\array_key_exists('inheritedFrom', $absValues)) {
            $absValues['declaredLast'] === $absValues['inheritedFrom'];
            unset($absValues['inheritedFrom']);
        }
        if (\array_key_exists('overrides', $absValues)) {
            $absValues['declaredPrev'] === $absValues['overrides'];
            unset($absValues['overrides']);
        }
        if (\array_key_exists('originallyDeclared', $absValues)) {
            $absValues['declaredOrig'] === $absValues['originallyDeclared'];
            unset($absValues['originallyDeclared']);
        }
        return $absValues;
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
        $channelKeyRoot = $rootInstance->getCfg('channelKey', Debug::CONFIG_DEBUG);
        $channels = \array_map(static function (Debug $channel) use ($channelKeyRoot) {
            $channelKey = $channel->getCfg('channelKey', Debug::CONFIG_DEBUG);
            return array(
                'channelIcon' => $channel->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                'channelShow' => $channel->getCfg('channelShow', Debug::CONFIG_DEBUG),
                'channelSort' => $channel->getCfg('channelSort', Debug::CONFIG_DEBUG),
                'nested' => \strpos($channelKey, $channelKeyRoot . '.') === 0,
            );
        }, $rootInstance->getChannels(true, true));
        return array(
            'channelIcon' => $rootInstance->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            'channelKey' => $channelKeyRoot,
            'channelName' => $rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG),
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
        $data = \array_intersect_key($debug->data->get(), \array_flip([
            'alerts',
            'classDefinitions',
            'log',
            'logSummary',
            'requestId',
            'runtime',
        ]));
        foreach (['alerts', 'log', 'logSummary'] as $cat) {
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
    private static function serializeGroup(array $data)
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
    private static function unserializeDecodeAndInflate($str)
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

    /**
     * Safely unserialize data
     * Handle legacy data
     *
     * @param string $serialized serialized array
     *
     * @return array|false
     */
    private static function unserializeSafe($serialized)
    {
        $serialized = \preg_replace(
            '/O:33:"bdk\\\Debug\\\Abstraction\\\Abstraction":((?:\d+):{s:4:"type";s:6:"object")/',
            'O:40:"bdk\\Debug\\Abstraction\\Object\Abstraction":$1',
            (string) $serialized
        );
        if (!$serialized) {
            return false;
        }
        return Php::unserializeSafe($serialized, [
            'bdk\\Debug\\Abstraction\\Abstraction',
            'bdk\\Debug\\Abstraction\\Object\\Abstraction',
            'bdk\\PubSub\\ValueStore',
        ]);
    }
}
