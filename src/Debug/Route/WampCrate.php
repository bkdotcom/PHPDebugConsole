<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;

/**
 * "Crate" values for transport via WAMP
 */
class WampCrate
{
    /** @var Debug */
    private $debug;

    /** @var bool */
    private $detectFiles = false;

    /** @var list<string> */
    private $foundFiles = array();

    /** @var list<string> */
    private $classesCrated = array();

    /** @var list<string> */
    private $classesNew = array();

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * JSON doesn't handle binary well (at all)
     *     a) strings with invalid utf-8 can't be json_encoded
     *     b) "javascript has a unicode problem" / will munge strings
     *   base64_encode all strings!
     *
     * Associative arrays get JSON encoded to js objects...
     *     Javascript doesn't maintain order for object properties
     *     in practice this seems to only be an issue with int/numeric keys
     *     store key order if needed
     *
     * @param mixed $mixed value to crate
     *
     * @return array|string
     */
    public function crate($mixed)
    {
        if ($mixed instanceof Abstraction) {
            return $this->crateAbstraction($mixed);
        }
        if (\is_array($mixed)) {
            return $this->crateArray($mixed);
        }
        if (\is_string($mixed)) {
            return $this->crateString($mixed);
        }
        return $mixed;
    }

    /**
     * Crate LogEntry
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array
     */
    public function crateLogEntry(LogEntry $logEntry)
    {
        $this->classesNew = array();
        $this->detectFiles = $logEntry->getMeta('detectFiles', false);
        $args = $this->crate($logEntry['args']);
        $meta = $logEntry['meta'];
        if ($logEntry['method'] === 'error' && !empty($meta['trace'])) {
            $meta = $this->getErrorTraceMeta($logEntry);
            unset($args[2]); // error's filepath argument
        }
        if ($this->detectFiles) {
            $meta['foundFiles'] = $this->foundFiles();
        }
        return [$args, $meta, $this->classesNew];
    }

    /**
     * Returns files found during crating
     *
     * @return array
     */
    public function foundFiles()
    {
        $foundFiles = $this->foundFiles;
        $this->foundFiles = array();
        return $foundFiles;
    }

    /**
     * Crate abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return Abstraction
     */
    private function crateAbstraction(Abstraction $abs)
    {
        $clone = clone $abs;
        switch ($clone['type']) {
            case Type::TYPE_ARRAY:
                $clone['value'] = $this->crateArray($clone['value']);
                return $clone;
            case Type::TYPE_OBJECT:
                return $this->crateObject($clone);
            case Type::TYPE_STRING:
                $clone['value'] = $this->crateString($clone['value']);
                if (isset($clone['valueDecoded'])) {
                    $clone['valueDecoded'] = $this->crate($clone['valueDecoded']);
                }
                return $clone;
        }
        return $clone;
    }

    /**
     * Crate array (may be encapsulated by Abstraction)
     *
     * @param array $array array
     *
     * @return array
     */
    private function crateArray($array)
    {
        $return = array();
        foreach ($array as $k => $v) {
            if (\substr((string) $k, 0, 1) === "\x00") {
                // key starts with null...
                // php based wamp router will choke (attempt to json_decode to obj)
                $k = '_b64_:' . \base64_encode($k);
            }
            $return[$k] = $this->crate($v);
        }
        $keys = $this->debug->arrayUtil->isList($array) === false
            ? $this->crateArrayOrder(\array_keys($array))
            : null;
        if ($keys) {
            $return['__debug_key_order__'] = $keys;
        }
        return $return;
    }

    /**
     * Compare sorted vs unsorted
     * if differ pass the key order
     *
     * @param array $keys array keys
     *
     * @return array|null
     */
    private function crateArrayOrder($keys)
    {
        $keysSorted = $keys;
        \sort($keysSorted, SORT_STRING);
        return $keys !== $keysSorted
            ? $keys
            : null;
    }

    /**
     * Crate object abstraction
     * (make sure string values are base64 encoded when necessary)
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return array
     */
    private function crateObject(Abstraction $abs)
    {
        $info = $abs->jsonSerialize();
        $classKey = $info['inheritsFrom'];
        if (\in_array($classKey, $this->classesCrated, true) === false) {
            $this->classesNew[] = $classKey;
            $this->classesCrated[] = $classKey;
        }
        // methods may be populated with __toString info, or methods with static variables
        if (isset($info['methods'])) {
            $info['methods'] = $this->crate($info['methods']);
        }
        $properties = isset($info['properties'])
            ? $info['properties']
            : array();
        foreach ($properties as $k => $propInfo) {
            if (isset($propInfo['value'])) {
                $info['properties'][$k]['value'] = $this->crate($propInfo['value']);
            }
        }
        return $info;
    }

    /**
     * Base64 encode string if it contains non-utf8 characters
     *
     * @param string $str string
     *
     * @return string
     */
    private function crateString($str)
    {
        if (!$str) {
            return $str;
        }
        if ($this->detectFiles && $this->debug->utility->isFile($str)) {
            $this->foundFiles[] = $str;
        }
        return $str;
    }

    /**
     * Process trace meta data
     * (so that we find filepaths)
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array meta data
     */
    private function getErrorTraceMeta(LogEntry $logEntry)
    {
        $inclContextDefault = $logEntry->getMeta('errorCat') === 'fatal';
        $meta = $logEntry['meta'];
        $meta['inclContext'] = $logEntry->getMeta('inclContext', $inclContextDefault);
        $logEntryTmp = new LogEntry(
            $this->debug,
            'trace',
            array(),
            $meta
        );
        $this->debug->rootInstance->getPlugin('methodTrace')->doTrace($logEntryTmp);
        return \array_replace_recursive(
            $logEntryTmp['meta'],
            array(
                'trace' => $logEntryTmp['args'][0],
            )
        );
    }
}
