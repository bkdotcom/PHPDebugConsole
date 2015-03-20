<?php
/**
 * Methods used to display and format values
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3b
 */

namespace bdk\Debug;

/**
 * VarDump:  Methods used to display and format values
 */
class VarDump
{

    protected $cfg = array();
    protected $utilities;
    protected $varDumpArray;
    protected $varDumpObject;

    const ABSTRACTION = "\x00debug\x00";
    const UNDEFINED = "\x00undefined\x00";

    /**
     * Constructor
     *
     * @param array  $cfg       config options
     * @param object $utilities optional utilities object
     */
    public function __construct($cfg = array(), $utilities = null)
    {
        $this->cfg = array(
            'addBR' => false,
            'propertySort' => 'visibility',   // none, visibility, or name
            'collectConstants' => true,
            'outputConstants' => true,
            'collectMethods' => true,
            'outputMethods' => true,
            'useDebugInfo' => true,
        );
        $this->cfg = array_merge($this->cfg, $cfg);
        if (is_object($utilities)) {
            $this->utilities = $utilities;
        } else {
            $this->utilities = new Utilities();
        }
        $this->varDumpArray = new VarDumpArray();
        $this->varDumpObject = new VarDumpObject();
    }

    /**
     * Returns string representation of value
     *
     * @param mixed  $val      value
     * @param string $outputAs options
     * @param array  $path     {@internal - used to check for recursion}
     *
     * @return string
     */
    public function dump($val, $outputAs = 'html', $path = array())
    {
        $type = null;
        $typeMore = null;
        if (empty($path)) {
            if (is_array($val) && !in_array(self::ABSTRACTION, $val, true)) {
                // array hasn't been prepped / could contain recursion
                $val = $this->getAbstraction($val);
            } elseif (is_object($val) || is_resource($val)) {
                $val = $this->getAbstraction($val);
            }
        }
        if (is_array($val)) {
            if (in_array(self::ABSTRACTION, $val, true)) {
                $type = $val['type'];
                $val = $this->dumpAbstraction($val, $outputAs, $path);
            } else {
                $type = 'array';
                $val = $this->varDumpArray->dump($val, $outputAs, $path);
            }
        } elseif (is_string($val)) {
            $type = 'string';
            if (is_numeric($val)) {
                $typeMore = 'numeric';
            } elseif ($this->utilities->isBinary($val)) {
                // all or partially binary data
                $typeMore = 'binary';
                $val = $this->dumpBinary($val, $outputAs == 'html');
            }
            if ($outputAs == 'text') {
                $val = '"'.$val.'"';
            }
        } elseif (is_int($val)) {
            $type = 'int';
        } elseif (is_float($val)) {
            $type = 'float';
        } elseif (is_bool($val)) {
            $type = 'bool';
            if (in_array($outputAs, array('html', 'text'))) {
                $val = $val ? 'true' : 'false';
            }
        } elseif (is_null($val)) {
            $type = 'null';
            if (in_array($outputAs, array('html', 'text'))) {
                $val = 'null';
            }
        }
        if ($outputAs == 'html') {
            if (!in_array($type, array('array','object'))) {
                $val = $this->dumpAsHtml($val, $type, $typeMore);
            }
        } elseif ($outputAs == 'script' && empty($path)) {
            $val = json_encode($val);
        }
        return $val;
    }

    /**
     * gets a value that has been abstracted
     * array (recursion), object, or resource
     *
     * @param array  $val      abstracted value
     * @param string $outputAs options
     * @param array  $path     {@internal - used to check for recursion}
     *
     * @return array [string $type, mixed $value]
     */
    protected function dumpAbstraction($val, $outputAs = 'html', $path = array())
    {
        $type = $val['type'];
        if ($type == 'array') {
            $val = $this->varDumpArray->dump($val, $outputAs, $path);
        } elseif ($type == 'object') {
            $val = $this->varDumpObject->dump($val, $outputAs, $path);
        } else {
            $val = $val['value'];
        }
        return $val;
    }

    /**
     * Add markup to value
     *
     * @param mixed  $val      value
     * @param string $type     type
     * @param string $typeMore numeric, binary, true, or false
     *
     * @return string html
     */
    protected function dumpAsHtml($val, $type = null, $typeMore = null)
    {
        $attribs = array(
            'class' => 't_'.$type,
            'title' => null,
        );
        if ($type == 'string') {
            if ($typeMore != 'binary') {
                $val = htmlspecialchars($this->utilities->toUtf8($val), ENT_COMPAT, 'UTF-8');
            }
            $val = $this->visualWhiteSpace($val);
        } elseif ($type == 'bool') {
            $typeMore = $val;
        }
        if (in_array($type, array('float','int')) || $typeMore == 'numeric') {
            $ts_now = time();
            $secs = 86400 * 90; // 90 days worth o seconds
            if ($val > $ts_now  - $secs && $val < $ts_now + $secs) {
                $attribs['class'] .= ' timestamp';
                $attribs['title'] = date('Y-m-d H:i:s', $val);
            }
        }
        if (!empty($typeMore) && $typeMore != 'binary') {
            $attribs['class'] .= ' '.$typeMore;
        }
        $val = '<span '.$this->utilities->buildAttribString($attribs).'>'.$val.'</span>';
        return $val;
    }

    /**
     * Display non-printable characters as hex
     *
     * @param string  $str     string containing binary
     * @param boolean $htmlout add html markup?
     *
     * @return string
     */
    public function dumpBinary($str, $htmlout)
    {
        $this->htmlout = $htmlout;
        $this->binaryStats = array(
            'ascii' => 0,
            'utf8'  => 0,   // bytes, not "chars"
            'other' => 0,
            'cur_text_len' => 0,
            'max_text_len' => 0,
            'text_segments' => 0,   // number of utf8 blocks
        );
        $stats = &$this->binaryStats;
        $regex = <<<EOD
/
( [\x01-\x7F] )                 # single-byte sequences   0xxxxxxx  (ascii 0 - 127)
| (
  (?: [\xC0-\xDF][\x80-\xBF]    # double-byte sequences   110xxxxx 10xxxxxx
    | [\xE0-\xEF][\x80-\xBF]{2} # triple-byte sequences   1110xxxx 10xxxxxx * 2
    | [\xF0-\xF7][\x80-\xBF]{3} # quadruple-byte sequence 11110xxx 10xxxxxx * 3
  ){1,100}                      # ...one or more times
)
| ( [\x80-\xBF] )               # invalid byte in range 10000000 - 10111111   128 - 191
| ( [\xC0-\xFF] )               # invalid byte in range 11000000 - 11111111   192 - 255
| (.)                           # null (including x00 in the regex = fail)
/x
EOD;
        $str_orig = $str;
        $strlen = strlen($str);
        $str = preg_replace_callback($regex, array($this,'dumpBinaryCallback'), $str);
        if ($stats['cur_text_len'] > $stats['max_text_len']) {
            $stats['max_text_len'] = $stats['cur_text_len'];
        }
        $percentBinary = $stats['other'] / $strlen * 100;
        if ($percentBinary > 33) {
            // treat it all as binary
            $str = bin2hex($str_orig);
            $str = trim(chunk_split($str, 2, ' '));
            if ($htmlout) {
                $str = '<span class="binary">'.$str.'</span>';
            }
        } else {
            $str = str_replace('</span><span class="binary">', '', $str);
        }
        return $str;
    }

    /**
     * Callback used by dumpBinary's preg_replace_callback
     *
     * @param array $matches matches
     *
     * @return string
     */
    protected function dumpBinaryCallback($matches)
    {
        $stats = &$this->binaryStats;
        $showHex = false;
        if ($matches[1] !== '') {
            // single byte sequence (may contain control char)
            $str = $matches[1];
            if (ord($str) < 32 || ord($str) == 127) {
                $showHex = true;
                if (in_array($str, array("\t","\n","\r"))) {
                    $showHex = false;
                }
            }
            if (!$showHex) {
                $stats['ascii']++;
                $stats['cur_text_len']++;
            }
            if ($this->htmlout) {
                $str = htmlspecialchars($str);
            }
        } elseif ($matches[2] !== '') {
            // Valid byte sequence. return unmodified.
            $str = $matches[2];
            $stats['utf8'] += strlen($str);
            $stats['cur_text_len'] += strlen($str);
            if ($str === "\xef\xbb\xbf") {
                // BOM
                $showHex = true;
            }
        } elseif ($matches[3] !== '' || $matches[4] !== '') {
            // Invalid byte
            $str = $matches[3] != ''
                ? $matches[3]
                : $matches[4];
            $showHex = true;
        } else {
            // null char
            $str = $matches[5];
            $showHex = true;
        }
        if ($showHex) {
            $str = $this->dumpBinaryHex($str, $stats);
        }
        return $str;
    }

    /**
     * display binary chars as hex
     *
     * @param string $str string containing binary
     *
     * @return string
     */
    protected function dumpBinaryHex($str)
    {
        $stats = &$this->binaryStats;
        $stats['other']++;
        if ($stats['cur_text_len']) {
            if ($stats['cur_text_len'] > $stats['max_text_len']) {
                $stats['max_text_len'] = $stats['cur_text_len'];
            }
            $stats['cur_text_len'] = 0;
            $stats['text_segments']++;
        }
        $chars = str_split($str);
        foreach ($chars as $i => $c) {
            $chars[$i] = '\x'.bin2hex($c);
        }
        $str = implode('', $chars);
        if ($this->htmlout) {
            $str = '<span class="binary">'.$str.'</span>';
        }
        return $str;
    }

    /**
     * Formats an array as a table
     *
     * @param array  $array   array
     * @param string $caption optional caption
     *
     * @return string
     */
    public function dumpTable($array, $caption = null)
    {
        $str = '';
        if (is_array($array) && in_array(self::ABSTRACTION, $array, true)) {
            $array = $array['values'];
        }
        if (!is_array($array)) {
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= $this->dump($array);
            $str = '<div class="m_log">'.$str.'</div>';
        } elseif (empty($array)) {
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= 'array()';
            $str = '<div class="m_log">'.$str.'</div>';
        } else {
            $keys = $this->utilities->arrayColKeys($array);
            $headers = array();
            foreach ($keys as $key) {
                $headers[] = $key === ''
                    ? 'value'
                    : htmlspecialchars($key);
            }
            $str = '<table>'."\n"
                .'<caption>'.$caption.'</caption>'."\n"
                .'<thead>'
                .'<tr><th>&nbsp;</th><th>'.implode('</th><th scope="col">', $headers).'</th></tr>'."\n"
                .'</thead>'."\n"
                .'<tbody>'."\n";
            foreach ($array as $k => $row) {
                $str .= $this->dumpTableRow($keys, $row, $k);
            }
            $str .= '</tbody>'."\n"
                .'</table>';
        }
        return $str;
    }

    /**
     * Returns table row
     *
     * @param array $keys   column keys
     * @param array $row    row
     * @param array $rowKey row key
     *
     * @return string
     */
    protected function dumpTableRow($keys, $row, $rowKey)
    {
        $str = '';
        $values = array();
        foreach ($keys as $key) {
            $value = '';
            if (is_array($row)) {
                $value = array_key_exists($key, $row)
                    ? $row[$key]
                    : self::UNDEFINED;
            } elseif ($key === '') {
                $value = $row;
            }
            if (is_array($value)) {
                $value = in_array(self::ABSTRACTION, $value, true) && $value['isRecursion']
                    ? '<span class="t_recursion">*RECURSION*</span>'
                    : $this->dumpTable($value);
            } elseif ($value === self::UNDEFINED) {
                $value = '<span class="t_undefined"></span>';
            } else {
                $value = $this->dump($value);
            }
            $values[] = $value;
        }
        $classAndInner = $this->utilities->parseAttribString($this->dump($rowKey));
        $str .= '<tr><td class="'.$classAndInner['class'].'">'.$classAndInner['innerhtml'].'</td>';
        foreach ($values as $v) {
            // remove the span wrapper.. add span's class to TD
            $classAndInner = $this->utilities->parseAttribString($v);
            $str .= $classAndInner['class']
                ? '<td class="'.$classAndInner['class'].'">'
                : '<td>';
            $str .= $classAndInner['innerhtml'];
            $str .= '</td>';
        }
        $str .= '</tr>'."\n";
        return $str;
    }

    /**
     * Retrieve a config or data value
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function get($path)
    {
        $ret = null;
        if (isset($this->cfg[$path])) {
            $ret = $this->cfg[$path];
        }
        return $ret;
    }

    /**
     * Want to store a "snapshot" of arrays, objects, & resources
     * Remove any reference to an "external" variable
     *
     * Deep cloning objects = problematic
     *   + some objects are uncloneable & throw fatal error
     *   + difficult to maintain circular references
     * Instead of storing objects in log, store "abstraction" array containing
     *     type, methods, & properties
     *
     * @param mixed $mixed array, object, or resource to prep
     * @param array $hist  (@internal) array/object history (used to test for recursion)
     *
     * @return array
     */
    public function getAbstraction(&$mixed, $hist = array())
    {
        if (is_array($mixed)) {
            return $this->varDumpArray->getAbstraction($mixed, $hist);
        } elseif (is_object($mixed)) {
            return $this->varDumpObject->getAbstraction($mixed, $hist);
        } elseif (is_resource($mixed)) {
            return array(
                'debug' => self::ABSTRACTION,
                'type' => 'resource',
                'value' => print_r($mixed, true).': '.get_resource_type($mixed),
            );
        }
    }

    /**
     * Set one or more config values
     *
     * @param string $path   key
     * @param mixed  $newVal value
     *
     * @return mixed
     */
    public function set($path, $newVal = null)
    {
        $ret = null;
        if (is_string($path)) {
            $ret = $this->cfg[$path];
            $this->cfg[$path] = $newVal;
        } elseif (is_array($path)) {
            $this->cfg = array_merge($this->cfg, $path);
        }
        return $ret;
    }

    /**
     * Add whitespace markup
     *
     * @param string $str string which to add whitespace html markup
     *
     * @return string
     */
    public function visualWhiteSpace($str)
    {
        // display \r, \n, & \t
        $str = preg_replace_callback('/(\r\n|\r|\n)/', array($this, 'visualWhiteSpaceCallback'), $str);
        $str = preg_replace('#(<br />)?\n$#', '', $str);
        $str = str_replace("\t", '<span class="ws_t">'."\t".'</span>', $str);
        return $str;
    }

    /**
     * Adds whitespace markup
     *
     * @param array $matches passed from preg_replace_callback
     *
     * @return string
     */
    protected function visualWhiteSpaceCallback($matches)
    {
        $strBr = $this->cfg['addBR'] ? '<br />' : '';
        $search = array("\r","\n");
        $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>'.$strBr."\n");
        return str_replace($search, $replace, $matches[1]);
    }
}
