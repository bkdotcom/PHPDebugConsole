<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.3
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
        if (!\is_array($arrayDef) || self::isCallable($arrayDef)) {
            // not array or appears to be a callable
            return $array2;
        }
        if (!\is_array($array2) || self::isCallable($array2)) {
            // not array or appears to be a callable
            return $array2;
        }
        foreach ($array2 as $k2 => $v2) {
            if (\is_int($k2)) {
                if (!\in_array($v2, $arrayDef)) {
                    $arrayDef[] = $v2;
                }
            } elseif (!isset($arrayDef[$k2])) {
                $arrayDef[$k2] = $v2;
            } else {
                $arrayDef[$k2] = self::arrayMergeDeep($arrayDef[$k2], $v2);
            }
        }
        return $arrayDef;
    }

    /**
     * Get value from array
     *
     * @param array        $array array to traverse
     * @param array|string $path  key path
     *                               path may contain special keys:
     *                                 * __count__ : return count() (traversal will cease)
     *                                 * __end__ : last value
     *                                 * __reset__ : first value
     *
     * @return mixed
     */
    public static function arrayPathGet($array, $path)
    {
        if (!\is_array($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        $path = \array_reverse($path);
        while ($path) {
            $key = \array_pop($path);
            if (!\is_array($array)) {
                return null;
            } elseif (isset($array[$key])) {
                $array = $array[$key];
            } elseif ($key == '__count__') {
                return \count($array);
            } elseif ($key == '__end__') {
                \end($array);
                $path[] = \key($array);
            } elseif ($key == '__reset__') {
                \reset($array);
                $path[] = \key($array);
            } else {
                return null;
            }
        }
        return $array;
    }

    /**
     * Build attribute string
     *
     * Attributes will be sorted by name
     * class & style attributes may be provided as arrays
     *
     * @param array $attribs key/pair values
     *
     * @return string
     * @see    https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#autofilling-form-controls:-the-autocomplete-attribute
     */
    public static function buildAttribString($attribs)
    {
        if (\is_string($attribs)) {
            return \rtrim(' '.\trim($attribs));
        }
        $attribPairs = array();
        foreach ($attribs as $k => $v) {
            if (\is_int($k)) {
                $k = $v;
                $v = true;
            }
            $isDataAttrib = \strpos($k, 'data-') === 0;
            if ($v === null) {
                // null valued attribs will not be output... not even data attributes
                continue;
            } elseif ($isDataAttrib) {
                $v = \json_encode($v);
                $v = \trim($v, '"');
            } elseif (\is_array($v)) {
                $v = self::buildAttribArrayVal($k, $v);
            } elseif (\is_bool($v)) {
                $v = self::buildAttribBoolVal($k, $v);
            } elseif ($k == 'class') {
                // class passed as string
                // sort and unique
                $v = \explode(' ', $v);
                $v = self::buildAttribArrayVal($k, $v);
            }
            $v = \trim($v);
            if (\array_filter(array(
                $v !== '',
                $k === 'value',
                $isDataAttrib,
            ))) {
                // at least one of the conditions is true
                $attribPairs[] = $k.'="'.\htmlspecialchars($v).'"';
            }
        }
        \sort($attribPairs);
        return \rtrim(' '.\implode(' ', $attribPairs));
    }

    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * @return string[string] The HTTP header key/value pairs.
     */
    public static function getAllHeaders()
    {
        if (\function_exists('getallheaders')) {
            return \getallheaders();
        }
        $headers = array();
        $copyServer = array(
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        );
        foreach ($_SERVER as $key => $value) {
            if (\substr($key, 0, 5) === 'HTTP_') {
                $key = \substr($key, 5);
                if (!isset($copyServer[$key]) || !isset($_SERVER[$key])) {
                    $key = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copyServer[$key])) {
                $headers[$copyServer[$key]] = $value;
            }
        }
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basicPass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . \base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basicPass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }
        return $headers;
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
        $backtrace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT, 8);
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
        $i += $offset;
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
        if (isset($backtrace[$iLine])) {
            $return['file'] = $backtrace[$iLine]['file'];
            $return['line'] = $backtrace[$iLine]['line'];
        } else {
            $return['file'] = $backtrace[$numFrames-1]['file'];
            $return['line'] = 0;
        }
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
        \usort($includedFiles, function ($valA, $valB) {
            $dirA = \dirname($valA);
            $dirB = \dirname($valB);
            return $dirA == $dirB
                ? \strnatcasecmp($valA, $valB)
                : \strnatcasecmp($dirA, $dirB);
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

    /**
     * Convert array attribute value to string
     *
     * Convert class/style array value to string
     * This function is not meant for data attributs
     *
     * @param string $key   attribute name (class|style)
     * @param array  $value classnames for class, key/value for style
     *
     * @return string
     */
    private static function buildAttribArrayVal($key, $value = array())
    {
        if ($key == 'class') {
            $value = \array_filter(\array_unique($value));
            \sort($value);
            $value = \implode(' ', $value);
        } elseif ($key == 'style') {
            $keyValues = array();
            foreach ($value as $k => $v) {
                $keyValues[] = $k.':'.$v.';';
            }
            \sort($keyValues);
            $value = \implode('', $keyValues);
        } else {
            $value = '';
        }
        return $value;
    }

    /**
     * Convert boolean attribute value to string
     *
     * @param string  $key   attribute name
     * @param boolean $value true|false
     *
     * @return string
     */
    private static function buildAttribBoolVal($key, $value = true)
    {
        if ($key == 'autocomplete') {
            $value = $value ? 'on' : 'off';
        } elseif ($value) {
            $value = $key;
        } else {
            $value = '';
        }
        return $value;
    }

    /**
     * Syntax-only is_callable() check
     * Additionally checks that $array[0] is an object
     *
     * @param array $array variable to check
     *
     * @return boolean [description]
     */
    private static function isCallable($array)
    {
        return \is_callable($array, true) && \is_object($array[0]);
    }
}
