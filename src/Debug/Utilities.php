<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.1
 */

namespace bdk\Debug;

/**
 * Utility methods
 */
class Utilities
{

    /**
     * Recursively merge two arrays
     *
     * @param array $arrayDef default array
     * @param array $array2   array 2
     *
     * @return array
     */
    public static function arrayMergeDeep($arrayDef, $array2)
    {
        if (!\is_array($arrayDef) || !\is_array($array2)) {
            $arrayDef = $array2;
        } elseif (\array_keys($array2) == array(0,1) && \is_object($array2[0]) && \is_string($array2[1])) {
            // appears to be a callable
            $arrayDef = $array2;
        } else {
            foreach ($array2 as $k2 => $v2) {
                if (\is_int($k2)) {
                    if (!\in_array($v2, $arrayDef)) {
                        $arrayDef[] = $v2;
                    }
                } elseif (!isset($arrayDef[$k2])) {
                    $arrayDef[$k2] = $v2;
                } elseif (!\is_array($v2)) {
                    $arrayDef[$k2] = $v2;
                } else {
                    $arrayDef[$k2] = self::arrayMergeDeep($arrayDef[$k2], $v2);
                }
            }
        }
        return $arrayDef;
    }

    /**
     * Basic html attrib builder
     *
     * Attributes will be sorted by name
     * If class attribute is provided as an array, classnames will be sorted
     *
     * @param array $attribs key/pair values
     *
     * @return string
     * @see    https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#autofilling-form-controls:-the-autocomplete-attribute
     */
    public static function buildAttribString($attribs)
    {
        $attribPairs = array();
        foreach ($attribs as $k => $v) {
            if (\is_int($k)) {
                $k = $v;
                $v = true;
            }
            $isDataAttrib = \strpos($k, 'data-') === 0;
            if ($isDataAttrib) {
                $v = \json_encode($v);
                $v = \trim($v, '"');
            } elseif (\is_array($v)) {
                // ie an array of classnames
                $v = \array_filter(\array_unique($v));
                \sort($v);
                $v = \implode(' ', $v);
            } elseif (\is_bool($v)) {
                if ($k == 'autocomplete') {
                    $v = $v ? 'on' : 'off';
                } elseif ($v) {
                    $v = $k;
                } else {
                    continue;
                }
            } elseif ($v === null) {
                continue;
            } elseif ($v === '') {
                if ($k !== 'value') {
                    continue;
                }
            }
            $v = \trim($v);
            $attribPairs[] = $k.'="'.\htmlspecialchars($v).'"';
        }
        \sort($attribPairs);
        return \rtrim(' '.\implode(' ', $attribPairs));
    }

    /**
     * Convert size int into "1.23 kB"
     *
     * @param integer|string $size bytes or similar to "1.23M"
     *
     * @return string
     */
    public static function getBytes($size)
    {
        if (\is_string($size) && \preg_match('/^([\d,.]+)\s?([kmgtp])b?$/i', $size, $matches)) {
            $size = \str_replace(',', '', $matches[1]);
            switch (\strtolower($matches[2])) {
                case 'p':
                    $size *= 1024;
                    // no break
                case 't':
                    $size *= 1024;
                    // no break
                case 'g':
                    $size *= 1024;
                    // no break
                case 'm':
                    $size *= 1024;
                    // no break
                case 'k':
                    $size *= 1024;
            }
        }
        $units = array('B','kB','MB','GB','TB','PB');
        $pow = \pow(1024, ($i=\floor(\log($size, 1024))));
        $size = $pow == 0
            ? '0 B'
            : \round($size/$pow, 2).' '.$units[$i];
        return $size;
    }

    /**
     * Returns information regarding previous call stack position
     * call_user_func and call_user_func_array are skipped
     *
     * Information returned:
     *     function : function/method name
     *     class :    fully qualified classname
     *     file :     file
     *     line :     line number
     *     type :     "->": instance call, "::": static call, null: not object oriented
     *
     * If a method is defined as static:
     *    the class value will always be the class in which the method was defined,
     *    type will always be "::", even if called with an ->
     *
     * @param integer $offset Adjust how far to go back
     *
     * @return array
     */
    public static function getCallerInfo($offset = 0)
    {
        $return = array(
            'file' => null,
            'line' => null,
            'function' => null,
            'class' => null,
            'type' => null,
        );
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 8);
        $numFrames = \count($backtrace);
        $regexInternal = '/^'.\preg_quote(__NAMESPACE__).'\b/';
        if (isset($backtrace[1]['class']) && \preg_match($regexInternal, $backtrace[1]['class'])) {
            // called from within
            // find the frame that called/triggered a debug function
            for ($i = $numFrames - 1; $i >= 0; $i--) {
                if (isset($backtrace[$i]['class']) && \preg_match($regexInternal, $backtrace[$i]['class'])) {
                    break;
                }
            }
        } else {
            $i = 1;
        }
        $i = $i + $offset;
        $iLine = $i;
        $iFunc = $i + 1;
        if (isset($backtrace[$iFunc]) && \in_array($backtrace[$iFunc]['function'], array('call_user_func', 'call_user_func_array'))) {
            $iLine++;
            $iFunc++;
        }
        if (isset($backtrace[$iFunc])) {
            $return = \array_merge($return, \array_intersect_key($backtrace[$iFunc], $return));
            if ($return['type'] == '->') {
                $return['class'] = \get_class($backtrace[$iFunc]['object']);
            }
        }
        $return['file'] = $backtrace[$iLine]['file'];
        $return['line'] = $backtrace[$iLine]['line'];
        return $return;
    }

    /**
     * returns required/included files sorted by directory
     *
     * @return array
     */
    public static function getIncludedFiles()
    {
        $includedFiles = \get_included_files();
        \usort($includedFiles, function ($a, $b) {
            $adir = \dirname($a);
            $bdir = \dirname($b);
            return $adir == $bdir
                ? \strnatcasecmp($a, $b)
                : \strnatcasecmp($adir, $bdir);
        });
        return $includedFiles;
    }

    /**
     * Returns cli, cron, ajax, or http
     *
     * @return string cli | cron | ajax | http
     */
    public static function getInterface()
    {
        $return = 'http';
        if (\http_response_code() === false) {
            // TERM is a linux/unix thing
            $return = isset($_SERVER['TERM']) || \function_exists('posix_isatty') && \posix_isatty(STDOUT)
                ? 'cli'
                : 'cron';
        } elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            $return = 'ajax';
        }
        return $return;
    }

    /**
     * Returns a sent/pending response header value
     *
     * @param string $key default = 'Content-Type', header to return
     *
     * @return string
     * @req    php >= 5
     */
    public static function getResponseHeader($key = 'Content-Type')
    {
        $value = null;
        $headers = \headers_list();
        foreach ($headers as $header) {
            if (\preg_match('/^'.$key.':\s*([^;]*)/i', $header, $matches)) {
                $value = $matches[1];
                break;
            }
        }
        return $value;
    }

    /**
     * Checks if a given string is base64 encoded
     *
     * @param string $str string to check
     *
     * @return boolean
     */
    public static function isBase64Encoded($str)
    {
        return (boolean) \preg_match('%^[a-zA-Z0-9(!\s+)?\r\n/+]*={0,2}$%', \trim($str));
    }

    /**
     * Is passed argument a simple array with all-integer in sequence from 0 to n?
     * empty array returns true
     *
     * @param [mixed $val value to check
     *
     * @return boolean
     */
    public static function isList($val)
    {
        if (!\is_array($val)) {
            return false;
        }
        $keys = \array_keys($val);
        foreach ($keys as $i => $key) {
            if ($i != $key) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine PHP's MemoryLimit
     *
     * default
     *    php > 5.2.0: 128M
     *    php = 5.2.0: 16M
     *    php < 5.2.0: 8M
     *
     * @return string
     */
    public static function memoryLimit()
    {
        $iniVal = \ini_get('memory_limit');
        return $iniVal ?: '128M';
    }

    /**
     * grab the class attrib and innerHTML from tag
     * this function is optimized for internal use only
     *
     * @param string $html html tag
     *
     * @return array
     */
    public static function parseAttribString($html)
    {
        $regEx = '#^<span class="([^"]+)">(.*)</span>$#s';
        return \preg_match($regEx, $html, $matches)
            ? array(
                'class' => $matches[1],
                'innerhtml' => $matches[2],
            )
            : array(
                'class' => null,
                'innerhtml' => $html,
            );
    }

    /**
     * Generate a unique request id
     *
     * @return string
     */
    public static function requestId()
    {
        return \hash(
            'crc32b',
            (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'terminal')
                .(isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'])
                .(isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : '')
        );
    }

    /**
     * serialize log for emailing
     *
     * @param array $data log data to serialize
     *
     * @return string
     */
    public static function serializeLog($data)
    {
        $str = \serialize($data);
        if (\function_exists('gzdeflate')) {
            $str = \gzdeflate($str);
        }
        $str = \chunk_split(\base64_encode($str), 124);
        return "START DEBUG\n"
            .$str    // chunk_split appends a "\r\n"
            .'END DEBUG';
    }

    /**
     * Use to unserialize the log serialized by emailLog
     *
     * @param string $str serialized log data
     *
     * @return array | false
     */
    public static function unserializeLog($str)
    {
        $strStart = 'START DEBUG';
        $strEnd = 'END DEBUG';
        if (\preg_match('/'.$strStart.'[\r\n]+(.+)[\r\n]+'.$strEnd.'/s', $str, $matches)) {
            $str = $matches[1];
        }
        $str = self::isBase64Encoded($str)
            ? \base64_decode($str)
            : false;
        if ($str && \function_exists('gzinflate')) {
            $strInflated = \gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        $log = \unserialize($str);
        return $log;
    }
}
