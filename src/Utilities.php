<?php
/**
 * General-purpose utilities
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3.3
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
                : ( $adir < $bdir ? -1 : 1 );
        });
        return $includedFiles;
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
     * Intent is to check if a given string contains non-readable "binary" text
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
            if (!$encoding) {
                $str_conv = false;
                if (function_exists('iconv')) {
                    $str_conv = iconv('cp1252', 'UTF-8', $str);
                }
                if ($str_conv === false) {
                    fwrite(STDOUT, 'Desperation'. "\n");
                    $str_conv = htmlentities($str, ENT_COMPAT);
                    $str_conv = html_entity_decode($str_conv, ENT_COMPAT, 'UTF-8');
                }
                $str = $str_conv;
            } elseif (!in_array($encoding, array('ASCII','UTF-8'))) {
                $str_new = iconv($encoding, 'UTF-8', $str);
                if ($str_new !== false) {
                    $str = $str_new;
                }
            }
        }
        return $str;
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
