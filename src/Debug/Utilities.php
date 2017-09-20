<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.0
 */

namespace bdk\Debug;

/**
 * Utility methods
 */
class Utilities
{

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array $rows array
     *
     * @return array
     */
    public static function arrayColKeys($rows)
    {
        $lastStack = array();
        $newStack = array();
        $currentStack = array();
        if (!is_array($rows)) {
            return array();
        }
        foreach ($rows as $row) {
            if (is_array($row) && in_array(Abstracter::ABSTRACTION, $row, true)) {
                // abstraction
                if ($row['type'] == 'object') {
                    if (in_array('Traversable', $row['implements'])) {
                        $row = $row['values'];
                    } else {
                        $row = array_filter($row['properties'], function ($prop) {
                            return $prop['visibility'] === 'public';
                        });
                    }
                } else {
                    $row = null;
                }
            }
            $currentStack = is_array($row)
                ? array_keys($row)
                : array('');
            if (empty($lastStack)) {
                $lastStack = $currentStack;
            } elseif ($currentStack != $lastStack) {
                $newStack = array();
                while (!empty($currentStack)) {
                    $currentKey = array_shift($currentStack);
                    if (!empty($lastStack) && $currentKey === $lastStack[0]) {
                        array_push($newStack, $currentKey);
                        array_shift($lastStack);
                    } elseif (false !== $position = array_search($currentKey, $lastStack, true)) {
                        $segment = array_splice($lastStack, 0, $position+1);
                        array_splice($newStack, count($newStack), 0, $segment);
                    } elseif (!in_array($currentKey, $newStack, true)) {
                        array_push($newStack, $currentKey);
                    }
                }
                // put on remaining from last_stack
                array_splice($newStack, count($newStack), 0, $lastStack);
                $newStack = array_unique($newStack);
                $lastStack = $newStack;
            }
        }
        $keys = $lastStack;
        return $keys;
    }

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
        if (!is_array($arrayDef) || !is_array($array2)) {
            $arrayDef = $array2;
        } else {
            foreach ($array2 as $k2 => $v2) {
                if (is_int($k2)) {
                    if (!in_array($v2, $arrayDef)) {
                        $arrayDef[] = $v2;
                    }
                } elseif (!isset($arrayDef[$k2])) {
                    $arrayDef[$k2] = $v2;
                } elseif (!is_array($v2)) {
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
     * @param array $attribs key/pair values
     *
     * @return string
     */
    public static function buildAttribString($attribs)
    {
        $attribPairs = array();
        foreach ($attribs as $k => $v) {
            if (is_array($v)) {
                // ie an array of classnames
                $v = implode(' ', array_filter(array_unique($v)));
            } elseif (is_bool($v)) {
                $v = $v ? $k : '';
            }
            if (strlen($v)) {
                $attribPairs[] = $k.'="'.htmlspecialchars($v).'"';
            }
        }
        return rtrim(' '.implode(' ', $attribPairs));
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
        if (is_string($size) && preg_match('/^([\d,.]+)\s?([kmgtp])b?$/i', $size, $matches)) {
            $size = str_replace(',', '', $matches[1]);
            switch (strtolower($matches[2])) {
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
        $pow = pow(1024, ($i=floor(log($size, 1024))));
        $size = $pow == 0
            ? '0 B'
            : round($size/$pow, 2).' '.$units[$i];
        return $size;
    }

    /**
     * returns required/included files sorted by directory
     *
     * @return array
     */
    public static function getIncludedFiles()
    {
        $includedFiles = get_included_files();
        usort($includedFiles, function ($a, $b) {
            $adir = dirname($a);
            $bdir = dirname($b);
            return $adir == $bdir
                ? strnatcasecmp($a, $b)
                : strnatcasecmp($adir, $bdir);
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
        if (http_response_code() === false) {
            // TERM is a linux/unix thing
            $return = isset($_SERVER['TERM']) || function_exists('posix_isatty') && posix_isatty(STDOUT)
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
        $headers = headers_list();
        foreach ($headers as $header) {
            if (preg_match('/^'.$key.':\s*([^;]*)/i', $header, $matches)) {
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
        return (boolean) preg_match('%^[a-zA-Z0-9(!\s+)?\r\n/+]*={0,2}$%', trim($str));
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
        $iniVal = ini_get('memory_limit');
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
        return preg_match($regEx, $html, $matches)
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
        return hash(
            'crc32b',
            (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'terminal')
                .(isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'])
                .(isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : '')
        );
    }

    /**
     * serialize log for emailing
     *
     * @param array $log string
     *
     * @return string
     */
    public static function serializeLog($log)
    {
        $str = serialize($log);
        if (function_exists('gzdeflate')) {
            $str = gzdeflate($str);
        }
        $str = chunk_split(base64_encode($str), 1024);
        $str = "\nSTART DEBUG:\n"
            .$str;
        return $str;
    }

    /**
     * Use to unserialize the log serialized by emailLog
     *
     * @param string $str serialized log
     *
     * @return array
     */
    public static function unserializeLog($str)
    {
        $pos = strpos($str, 'START DEBUG');
        if ($pos !== false) {
            $str = substr($str, $pos+11);
            $str = preg_replace('/^[^\r\n]*[\r\n]+/', '', $str);
        }
        $str = self::isBase64Encoded($str)
            ? base64_decode($str)
            : false;
        if ($str && function_exists('gzinflate')) {
            $strInflated = gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        $log = unserialize($str);
        return $log;
    }
}
