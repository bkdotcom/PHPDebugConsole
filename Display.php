<?php

namespace bdk\Debug;

/**
 * Display/Formatting related methods
 */
class Display
{

    protected $cfg = array();
    protected $utilities;

    const VALUE_ABSTRACTION = "\x00debug\x00";

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
        );
        $this->cfg = array_merge($this->cfg, $cfg);
        if (is_object($utilities)) {
            $this->utilities = $utilities;
        } else {
            require_once dirname(__FILE__).'/Utilities.php';
            $this->utilities = new Utilities();
        }
    }

    /**
     * Returns string representation of value
     *
     * @param mixed $val  value
     * @param array $opts options
     * @param array $hist {@internal - used to check for recursion}
     *
     * @return string
     */
    public function getDisplayValue($val, $opts = array(), $hist = array())
    {
        $type = null;
        $typeMore = null;
        if (empty($hist)) {
            $opts = array_merge(array(
                'html' => true,     // use html markup
                'flatten' => false, // flatten array & obj structures (only applies when !html)
                'boolNullToString' => true,
            ), $opts);
            if (is_array($val) && !in_array(self::VALUE_ABSTRACTION, $val, true)) {
                // array hasn't been prepped / could contain recursion
                $val = $this->utilities->valuePrep($val);
            } elseif (is_object($val) || is_resource($val)) {
                $val = $this->utilities->valuePrep($val);
            }
        }
        if (is_array($val)) {
            if (in_array(self::VALUE_ABSTRACTION, $val, true)) {
                list($type, $val) = $this->getValueAbstraction($val, $opts);
            }
            if (is_array($val)) {
                $type = 'array';
                $hist[] = 'array';
                foreach ($val as $k => $val2) {
                    $val[$k] = $this->getDisplayValue($val2, $opts, $hist);
                }
                if ($opts['flatten']) {
                    $val = trim(print_r($val, true));
                    if (count($hist) > 1) {
                        $val = str_replace("\n", "\n    ", $val);
                    }
                }
            }
        } elseif (is_string($val)) {
            $type = 'string';
            if (is_numeric($val)) {
                $typeMore = 'numeric';
            } elseif ($this->utilities->isBinary($val)) {
                // all or partially binary data
                $typeMore = 'binary';
                $val = $this->getBinary($val, $opts['html']);
            }
        } elseif (is_int($val)) {
            $type = 'int';
        } elseif (is_float($val)) {
            $type = 'float';
        } elseif (is_bool($val)) {
            $type = 'bool';
            $vStr = $val ? 'true' : 'false';
            if ($opts['boolNullToString']) {
                $val = $vStr;
            }
            $typeMore = $vStr;
        } elseif (is_null($val)) {
            $type = 'null';
            if ($opts['boolNullToString']) {
                $val = 'null';
            }
        }
        if ($opts['html']) {
            $val = $this->getValueHtml($val, $type, $typeMore);
        }
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
    public function getBinary($str, $htmlout)
    {
        $this->htmlout = $htmlout;
        $this->displayBinaryStats = array(
            'ascii' => 0,
            'utf8'  => 0,   // bytes, not "chars"
            'other' => 0,
            'cur_text_len' => 0,
            'max_text_len' => 0,
            'text_segments' => 0,   // number of utf8 blocks
        );
        $stats = &$this->displayBinaryStats;
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
        $str = preg_replace_callback($regex, array($this,'getBinaryCallback'), $str);
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
     * Callback used by getBinary's preg_replace_callback
     *
     * @param array $matches matches
     *
     * @return string
     */
    protected function getBinaryCallback($matches)
    {
        $stats = &$this->displayBinaryStats;
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
            $str = $this->getBinaryHex($str, $stats);
        }
        return $str;
    }

    /**
     * [getBinaryHex description]
     *
     * @param string $str string containing binary
     *
     * @return string
     */
    protected function getBinaryHex($str)
    {
        $stats = &$this->displayBinaryStats;
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
    public function getTable($array, $caption = null)
    {
        $str = '';
        if (is_array($array) && in_array(self::VALUE_ABSTRACTION, $array, true)) {
            $array = $array['value'];
        }
        if (!is_array($array)) {
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= $this->getDisplayValue($array);
            $str = '<div class="log">'.$str.'</div>';
        } elseif (empty($array)) {
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= 'array()';
            $str = '<div class="log">'.$str.'</div>';
        } else {
            $keys = $this->utilities->arrayColKeys($array);
            $str = '<table cellpadding="1" cellspacing="0" border="1">'."\n"   // style="border:solid 1px;"
                .'<caption>'.$caption.'</caption>'."\n";
            $values = array();
            foreach ($keys as $key) {
                $values[] = $key === ''
                    ? 'value'
                    : htmlspecialchars($key);
            }
            $str .= ''
                .'<thead>'
                .'<tr><th>&nbsp;</th><th>'.implode('</th><th scope="col">', $values).'</th></tr>'."\n"
                .'</thead>'."\n";
            $str .= '<tbody>'."\n";
            foreach ($array as $k => $row) {
                $str .= $this->getTableRow($keys, $row, $k);
            }
            $str .= '</tbody>'."\n".'</table>';
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
    protected function getTableRow($keys, $row, $rowKey)
    {
        $str = '';
        $values = array();
        $undefined = "\x00".'undefined'."\x00";
        $displayValRegEx = '#^<span class="([^"]+)">(.*)</span>$#s';
        foreach ($keys as $key) {
            $value = '';
            if (is_array($row)) {
                $value = array_key_exists($key, $row)
                    ? $row[$key]
                    : $undefined;
            } elseif ($key === '') {
                $value = $row;
            }
            if (is_array($value)) {
                $value = call_user_func(array($this,__FUNCTION__), $value);
            } elseif ($value === $undefined) {
                $value = '<span class="t_undefined"></span>';
            } else {
                $value = $this->getDisplayValue($value);
            }
            $values[] = $value;
        }
        $rowKey = $this->getDisplayValue($rowKey);
        if (preg_match($displayValRegEx, $rowKey, $matches)) {
            $class = $matches[1];
            $rowKey = $matches[2];
        }
        $str .= '<tr valign="top"><td class="'.$class.'">'.$rowKey.'</td>';
        foreach ($values as $v) {
            // remove the span wrapper.. add span's class to TD
            $class = null;
            if (preg_match($displayValRegEx, $v, $matches)) {
                $class = $matches[1];
                $v = $matches[2];
            }
            $str .= $class
                ? '<td class="'.$class.'">'
                : '<td>';
            $str .= $v;
            $str .= '</td>';
        }
        $str .= '</tr>'."\n";
        return $str;
    }

    /**
     * gets a value that has been abstracted
     * array (recursion), object, or resource
     *
     * @param array $val  abstracted value
     * @param array $opts options
     * @param array $hist {@internal - used to check for recursion}
     *
     * @return array [string $type, mixed $value]
     */
    protected function getValueAbstraction($val, $opts = array(), $hist = array())
    {
        $type = $val['type'];
        if ($type == 'object') {
            $type = 'object';
            if ($val['isRecursion']) {
                $val = '<span class="t_object">'
                        .'<span class="t_object-class">'.$val['class'].' object</span>'
                        .' <span class="t_recursion">*RECURSION*</span>'
                    .'</span>';
                if (!$opts['html']) {
                    $val = strip_tags($val);
                }
                $type = null;
            } else {
                $hist[] = &$val;
                $val = array(
                    'class'      => $val['class'].' object',
                    'properties' => $this->getDisplayValue($val['properties'], $opts, $hist),
                    'methods'    => $this->getDisplayValue($val['methods'], $opts, $hist),
                );
                if ($opts['html'] || $opts['flatten']) {
                    $val = $val['class']."\n"
                        .'    methods: '.$val['methods']."\n"
                        .'    properties: '.$val['properties'];
                    if ($opts['flatten'] && count($hist) > 1) {
                        $val = str_replace("\n", "\n    ", $val);
                    }
                }
            }
        } elseif ($type == 'array' && $val['isRecursion']) {
            $val = '<span class="t_array">'
                    .'<span class="t_keyword">Array</span>'
                    .' <span class="t_recursion">*RECURSION*</span>'
                .'</span>';
            if (!$opts['html']) {
                $val = strip_tags($val);
            }
            $type = null;
        } else {
            $val = $val['value'];
        }
        return array($type, $val);
    }

    /**
     * add markup to value
     *
     * @param mixed  $val      value
     * @param string $type     type
     * @param string $typeMore numeric, binary, true, or false
     *
     * @return string html
     */
    protected function getValueHtml($val, $type = null, $typeMore = null)
    {
        if ($type == 'array') {
            $html = '<span class="t_keyword">Array</span><br />'."\n"
                .'<span class="t_punct">(</span>'."\n"
                .'<span class="t_array-inner">'."\n";
            foreach ($val as $k => $val2) {
                $html .= "\t".'<span class="t_key_value">'
                        .'<span class="t_key">['.$k.']</span> '
                        .'<span class="t_operator">=&gt;</span> '
                        .$val2
                    .'</span>'."\n";
            }
            $html .= '</span>'
                .'<span class="t_punct">)</span>';
            $val = '<span class="t_'.$type.'">'.$html.'</span>';
        } elseif ($type == 'object') {
            $html = preg_replace(
                '#^([^\n]+)\n(.+)$#s',
                '<span class="t_object-class">\1</span>'."\n".'<span class="t_object-inner">\2</span>',
                $val
            );
            $html = preg_replace('#\sproperties: #', '<br />properties: ', $html);
            $val = '<span class="t_'.$type.'">'.$html.'</span>';
        } elseif ($type) {
            $attribs = array(
                'class' => 't_'.$type,
                'title' => null,
            );
            if (!empty($typeMore) && $typeMore != 'binary') {
                $attribs['class'] .= ' '.$typeMore;
            }
            if ($type == 'string') {
                if ($typeMore != 'binary') {
                    $val = htmlspecialchars($this->utilities->toUtf8($val), ENT_COMPAT, 'UTF-8');
                }
                $val = $this->visualWhiteSpace($val);
            }
            if (in_array($type, array('float','int')) || $typeMore == 'numeric') {
                $ts_now = time();
                $secs = 86400 * 90; // 90 days worth o seconds
                if ($val > $ts_now  - $secs && $val < $ts_now + $secs) {
                    $attribs['class'] .= ' timestamp';
                    $attribs['title'] = date('Y-m-d H:i:s', $val);
                }
            }
            $val = '<span '.$this->utilities->buildAttribString($attribs).'>'.$val.'</span>';
        }
        return $val;
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
