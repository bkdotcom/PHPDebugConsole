<?php

namespace bdk\Debug;

/**
 * Utility methods
 */
class Utilities
{

    const VALUE_ABSTRACTION = "\x00debug\x00";

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array $rows array
     *
     * @return array
     */
    public function arrayColKeys($rows)
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
    public function arrayMergeDeep($arrayDef, $array2)
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
                    $arrayDef[$k2] = $this->arrayMergeDeep($arrayDef[$k2], $v2);
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
    public function buildAttribString($attribs)
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
    public function getResponseHeader($key = 'Content-Type')
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
    public function isBase64Encoded($str)
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
    public function isBinary($str)
    {
        $isBinary = false;
        if (is_string($str)) {
            $isUtf8 = $this->isUtf8($str, $ctrl);
            if (!$isUtf8 || $ctrl) {
                $isBinary = true;
            }
        }
        return $isBinary;
    }

    /**
     * Determine if passed array contains a self referencing loop
     *
     * @param mixed $mixed array or object to check
     * @param mixed $key   check if this is the key/value that is the reference
     *
     * @return boolean
     * @internal
     * @link http://stackoverflow.com/questions/9105816/is-there-a-way-to-detect-circular-arrays-in-pure-php
     */
    public function isRecursive($mixed, $key = null)
    {
        $recursive = false;
        // "Array *RECURSION" or "Object *RECURSION*"
        if (strpos(print_r($mixed, true), "\n *RECURSION*\n") !== false) {
            // contains recursion somewhere
            $recursive = true;
            if ($key !== null) {
                // array contains recursion or a string containing "Array *RECURSION*"
                $recursive = $this->isRecursiveIteration($mixed);
                if ($recursive) {
                    // test if this is the value that's the reference
                    $recursive = $key === $recursive[0];
                }
            }
        }
        return $recursive;
    }

    /**
     * Returns a path to first recursive loop found or false if no recursion
     *
     * @param array $array  array
     * @param mixed $unique some unique value/object
     *          this value will be appended to the array and checked for in nested structure
     * @param array $path   {@internal}
     *
     * @return mixed false, or path to reference
     * @internal
     */
    public function isRecursiveIteration(&$array, $unique = null, $path = array())
    {
        if ($unique === null) {
            $unique = new \stdclass();
        } elseif ($unique === end($array)) {
            return $path;
        }
        if (is_array($array)) {
            $type = 'array';
            $array[] = $unique;
            $keys = array_keys($array);
        } else {
            $type = 'object';
            $keys = array_keys(get_object_vars($array));
        }
        foreach ($keys as $k) {
            if ($type == 'array') {
                $v = &$array[$k];
            } else {
                $v = &$array->{$k};
            }
            $path_new = $path;
            $path_new[] = $k;
            if (is_array($v) || is_object($v)) {
                $path_new = $this->isRecursiveIteration($v, $unique, $path_new);
                if ($path_new) {
                    if (end($array) === $unique) {
                        unset($array[key($array)]);
                    }
                    return $path_new;
                }
            }
        }
        if (end($array) === $unique) {
            unset($array[key($array)]);
        }
        return array();
    }

    /**
     * Determine if string is UTF-8 encoded
     *
     * @param string  $str  string to check
     * @param boolean $ctrl does string contain a "non-printable" control char?
     *
     * @return boolean
     */
    public function isUtf8($str, &$ctrl = false)
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
                if ((++$i == $length) || ( (ord($str[$i]) & 0xC0) != 0x80 )) {
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
     * Attempt to convert string to UTF-8 encoding
     *
     * @param string $str string to convert
     *
     * @return string
     */
    public function toUtf8($str)
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
     * Use to unserialize the log serialized by emailLog
     *
     * @param string $str serialized log
     *
     * @return string
     */
    public function unserializeLog($str)
    {
        $pos = strpos($str, 'START DEBUG');
        if ($pos !== false) {
            $str = substr($str, $pos+11);
            $str = preg_replace('/^[^\r\n]*[\r\n]+/', '', $str);
        }
        $str = $this->isBase64Encoded($str)
            ? base64_decode($str)
            : false;
        if ($str && function_exists('gzinflate')) {
            $strInflated = gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        return $str;
    }

    /**
     * Want to store a "snapshot" of arrays, objects, & resources
     * Remove any reference to an "external" variable
     *
     * Deep cloning objects = problematic
     *   + some objects are uncloneable & throw fatal error
     *   + difficult to maintain circular references
     * Instead of storing objects in log, store array containing
     *     type, methods, & properties
     *
     * @param array|object $mixed array or object to walk/prep
     * @param array        $hist  (@internal)
     * @param array        $path  {@internal}
     *
     * @return array
     */
    public function valuePrep($mixed, $hist = array(), $path = array())
    {
        if (empty($hist)) {
            $this->data['recursion'] = $this->isRecursive($mixed);
        }
        $vars = array();
        if (is_array($mixed)) {
            $isRecursion = $path
                && $this->data['recursion']
                && $this->isRecursive($mixed, end($path));
            $return = array(
                'debug' => self::VALUE_ABSTRACTION,
                'type' => 'array',
                'value' => array(),
                'isRecursion' => $isRecursion,
            );
            if ($isRecursion) {
                return $return;
            } else {
                $hist[] = &$mixed;
                $vars = $mixed;
            }
        } elseif (is_object($mixed)) {
            $return = array(
                'debug' => self::VALUE_ABSTRACTION,
                'type' => 'object',
                'class' => get_class($mixed),
                'methods' => get_class_methods($mixed),
                'properties' => array(),
                'isRecursion' => in_array($mixed, $hist, true),
            );
            if ($return['isRecursion']) {
                return $return;
            } elseif ($return['class'] == __CLASS__) {
                // special case for debugging self (only show public methods/props for self)
                $hist[] = &$mixed;
                $return['methods'] = call_user_func('get_class_methods', $mixed);
                // pass thru call_user_func to lose scope
                $vars = call_user_func('get_object_vars', $mixed);
            } else {
                $hist[] = &$mixed;
                $vars = get_object_vars($mixed);
            }
        } elseif (is_resource($mixed)) {
            $return = array(
                'debug' => self::VALUE_ABSTRACTION,
                'type' => 'resource',
                'value' => print_r($mixed, true).': '.get_resource_type($mixed),
            );
            return $return;
        }
        foreach ($vars as $k => $v) {
            if (is_array($v) || is_object($v) || is_resource($v)) {
                $path_new = $path;
                $path_new[] = $k;
                $v_new = $this->valuePrep($v, $hist, $path_new);
            } else {
                $v_new = $v;
            }
            unset($vars[$k]);   // remove any reference
            $vars[$k] = $v_new;
        }
        if ($return['type']=='array') {
            if (empty($path)) {
                $return['value'] = $vars;
                return $return;
            } else {
                return $vars;
            }
        } elseif ($return['type']=='object') {
            $return['properties'] = $vars;
            return $return;
        }
    }
}
