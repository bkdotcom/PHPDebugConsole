<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2026 Brad Kent
 * @since     3.6
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\Php;
use bdk\Debug\Utility\StringUtil;
use bdk\Debug\Utility\UnserializeLogBackwards;

/**
 * Unserialize / decompress / base64 decode log data
 */
class UnserializeLog
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
     */
    public static function import(array $data, $debug = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($debug, 'bdk\Debug|null', 'debug');

        if (!$debug) {
            $debug = new Debug();
        }
        self::$debug = $debug;

        // set config for any channels already present in debug
        foreach (\array_intersect_key($debug->getChannels(true, true), $data['config']['channels']) as $fqn => $channel) {
            $channel->setCfg($data['config']['channels'][$fqn], Debug::CONFIG_NO_RETURN);
        }
        $debug->setCfg($data['config'], Debug::CONFIG_NO_RETURN);

        if ($data['classDefinitions'] && empty($data['classDefinitions']["\x00default\x00"])) {
            $data['classDefinitions']["\x00default\x00"] = self::$debug->abstracter->abstractObject->definition->getValueStoreDefault();
        }
        $data['classDefinitions'] = \array_map(static function ($def) {
            return UnserializeLogBackwards::updateClassDefinition($def, self::$debug);
        }, $data['classDefinitions']);
        // set classDefinitions early so can reference them when importing log entries
        $debug->data->set('classDefinitions', $data['classDefinitions']);

        foreach (['alerts', 'log', 'logSummary'] as $cat) {
            $data[$cat] = self::importGroup($cat, $data[$cat]);
        }

        unset($data['config']);
        unset($data['version']);
        unset($data['classDefinitions']); // already set above (or when processing log)
        foreach ($data as $k => $v) {
            $debug->data->set($k, $v);
        }
        return $debug;
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
            'classDefinitions' => array(),
            'config' => array(
                'channels' => array(),
            ),
            'version' => '2.3', // prior to 3.0, we didn't include version
        ), $data);
        \ksort($data);
        \ksort($data['classDefinitions']);
        \ksort($data['runtime']); // 2.3 runtime not sorted
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
        $regex = '/(?:' . $strStart . '[\r\n]+)(.+)(?:[\r\n]+' . $strEnd . ')/s';
        $matches = [];
        if (\preg_match($regex, $str, $matches)) {
            $str = $matches[1];
        }
        return $str;
    }

    /**
     * "unserialize" log data
     *
     * @param string $cat  ('alerts'|'log'|'logSummary')
     * @param array  $data data to import
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
     * Unserialize Log entry
     *
     * @param array $vals method, args, & meta values
     *
     * @return LogEntry
     */
    private static function importLogEntry(array $vals)
    {
        $vals = \array_replace(['', array(), array()], $vals);
        $logEntry = new LogEntry(self::$debug, $vals[0], $vals[1], $vals[2]);
        return UnserializeLogBackwards::updateLogEntry($logEntry);
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
