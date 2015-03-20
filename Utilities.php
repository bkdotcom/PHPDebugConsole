<?php
/**
 * General-purpose utilities
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3b
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
        $last_stack = array();
        $new_stack = array();
        $current_stack = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $current_stack = is_array($row)
                    ? array_keys($row)
                    : array('');
                if (empty($last_stack)) {
                    $last_stack = $current_stack;
                } elseif ($current_stack != $last_stack) {
                    $new_stack = array();
                    while (!empty($current_stack)) {
                        $current_key = array_shift($current_stack);
                        if (!empty($last_stack) && $current_key === $last_stack[0]) {
                            array_push($new_stack, $current_key);
                            array_shift($last_stack);
                        } elseif (false !== $position = array_search($current_key, $last_stack, true)) {
                            $segment = array_splice($last_stack, 0, $position+1);
                            array_splice($new_stack, count($new_stack), 0, $segment);
                        } elseif (!in_array($current_key, $new_stack, true)) {
                            array_push($new_stack, $current_key);
                        }
                    }
                    // put on remaining from last_stack
                    array_splice($new_stack, count($new_stack), 0, $last_stack);
                    $new_stack = array_unique($new_stack);
                    $last_stack = $new_stack;
                }
            }
        }
        $keys = $last_stack;
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
        $attrib_pairs = array();
        foreach ($attribs as $k => $v) {
            if (isset($v)) {
                $attrib_pairs[] = $k.'="'.htmlspecialchars($v).'"';
            }
        }
        return implode(' ', $attrib_pairs);
    }

    /**
     * Returns a sent/pending response header value
     * only works with php >= 5
     *
     * @param string $key default = 'Content-Type', header to return
     *
     * @return string
     */
    public static function getResponseHeader($key = 'Content-Type')
    {
        $value = null;
        if (function_exists('headers_list')) {
            $headers = headers_list();
            $key = 'Content-Type';
            foreach ($headers as $header) {
                if (preg_match('/^'.$key.':\s*([^;]*)/i', $header, $matches)) {
                    $value = $matches[1];
                    break;
                }
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
        return preg_match('%^[a-zA-Z0-9(!\s+)?\r\n/+]*={0,2}$%', trim($str));
    }

    /**
     * Intent is to check if a given string is "binary" data or readable text
     *
     * @param string $str string to check
     *
     * @return boolean
     */
    public static function isBinary($str)
    {
        $isBinary = false;
        if (is_string($str)) {
            $isUtf8 = self::isUtf8($str, $ctrl);
            if (!$isUtf8 || $ctrl) {
                $isBinary = true;
            }
        }
        return $isBinary;
    }

    /**
     * Determine if string is UTF-8 encoded
     *
     * @param string  $str  string to check
     * @param boolean $ctrl does string contain a "non-printable" control char?
     *
     * @return boolean
     */
    public static function isUtf8($str, &$ctrl = false)
    {
        $length = strlen($str);
        $ctrl = false;
        for ($i=0; $i < $length; $i++) {
            $char = ord($str[$i]);
            if ($char < 0x80) {                 # 0bbbbbbb
                $bytes = 0;
            } elseif (($char & 0xE0) == 0xC0) { # 110bbbbb
                $bytes=1;
            } elseif (($char & 0xF0) == 0xE0) { # 1110bbbb
                $bytes=2;
            } elseif (($char & 0xF8) == 0xF0) { # 11110bbb
                $bytes=3;
            } elseif (($char & 0xFC) == 0xF8) { # 111110bb
                $bytes=4;
            } elseif (($char & 0xFE) == 0xFC) { # 1111110b
                $bytes=5;
            } else {                            # Does not match any model
                return false;
            }
            for ($j=0; $j<$bytes; $j++) { # n bytes matching 10bbbbbb follow ?
                if (++$i == $length || (ord($str[$i]) & 0xC0) != 0x80) {
                    return false;
                }
            }
            if ($bytes == 0 && ( $char < 32 || $char == 127 )) {
                if (!in_array($str[$i], array("\t","\n","\r"))) {
                    $ctrl = true;
                }
            }
        }
        if (strpos($str, "\xef\xbb\xbf") !== false) {
            $ctrl = true;   // treat BOM as ctrl char
        }
        return true;
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
        preg_match($regEx, $html, $matches);
        return array(
            'class' => $matches[1],
            'innerhtml' => $matches[2],
        );
    }

    /**
     * Attempt to convert string to UTF-8 encoding
     *
     * @param string $str string to convert
     *
     * @return string
     */
    public static function toUtf8($str)
    {
        if (extension_loaded('mbstring') && function_exists('iconv')) {
            $encoding = mb_detect_encoding($str, mb_detect_order(), true);
            if ($encoding && !in_array($encoding, array('ASCII','UTF-8'))) {
                $str_new = iconv($encoding, 'UTF-8', $str);
                if ($str_new !== false) {
                    $str = $str_new;
                } else {
                    // iconv error?
                }
            }
        }
        return $str;
    }

    /**
     * translate configuration keys
     *
     * @param mixed $mixed string key or config array
     *
     * @return mixed
     */
    public static function translateCfgKeys($mixed)
    {
        $objKeys = array(
            'varDump' => array(
                'addBR', 'propertySort', 'useDebugInfo',
                'collectConstants', 'outputConstants', 'collectMethods', 'outputMethods',
            ),
            'errorHandler' => array('lastError', 'onError'),
            'output' => array(
                'css', 'filepathCss', 'filepathScript', 'firephpInc', 'firephpOptions',
                'onOutput', 'outputAs', 'outputCss', 'outputScript',
            ),
        );
        if (is_string($mixed)) {
            $path = preg_split('#[\./]#', $mixed);
            foreach ($objKeys as $objKey => $keys) {
                if (in_array($path[0], $keys)) {
                    array_unshift($path, $objKey);
                    break;
                }
            }
            if (count($path)==1) {
                array_unshift($path, 'debug');
            }
            $mixed = implode('/', $path);
        } elseif (is_array($mixed)) {
            foreach ($mixed as $k => $v) {
                if (is_array($v)) {
                    continue;
                }
                $translated = false;
                foreach ($objKeys as $objKey => $keys) {
                    if (in_array($k, $keys)) {
                        unset($mixed[$k]);
                        $mixed[$objKey][$k] = $v;
                        $translated = true;
                        break;
                    }
                }
                if (!$translated) {
                    unset($mixed[$k]);
                    $mixed['debug'][$k] = $v;
                }
            }
        }
        return $mixed;
    }

    /**
     * serialize log for emailing
     *
     * @param string $str string
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
