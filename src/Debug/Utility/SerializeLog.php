<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\UnserializeLog;

/**
 * Serialize / compress / base64 encode log data
 */
class SerializeLog
{
    /** @var Debug */
    protected static $debug;

    /**
     * Import the config and data into the debug instance
     *
     * @param array      $data  Unpacked / Unserialized log data
     * @param Debug|null $debug (optional) Debug instance
     *
     * @return Debug
     *
     * @deprecated 3.6 use UnserializeLog::import instead
     */
    public static function import(array $data, $debug = null)
    {
        return UnserializeLog::import($data, $debug);
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
     *
     * @deprecated 3.6 use UnserializeLog::unserialize instead
     */
    public static function unserialize($str)
    {
        return UnserializeLog::unserialize($str);
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
        // Filter out orphaned definitions (not referenced by any logged abstraction)
        $data['classDefinitions'] = self::filterOrphanedDefinitions($data['classDefinitions']);
        foreach (['alerts', 'log', 'logSummary'] as $cat) {
            $data[$cat] = self::serializeGroup($data[$cat]);
        }
        return $data;
    }

    /**
     * Filter out orphaned class definitions (not referenced by any logged abstraction)
     *
     * @param array $classDefinitions Class definitions
     *
     * @return array
     */
    private static function filterOrphanedDefinitions(array $classDefinitions)
    {
        return \array_filter($classDefinitions, static function ($definition) {
            return !empty($definition['__isUsed']);
        });
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
}
