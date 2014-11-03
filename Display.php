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
     * @param mixed $v    value
     * @param array $opts options
     * @param array $hist {@internal - used to check for recursion}
     *
     * @return string
     */
    public function getDisplayValue($v, $opts = array(), $hist = array())
    {
        $type = null;
        $typeMore = null;
        if (empty($hist)) {
            $opts = array_merge(array(
                'html' => true,     // use html markup
                'flatten' => false, // flatten array & obj structures (only applies when !html)
                'boolNullToString' => true,
            ), $opts);
            if (is_array($v) && !in_array(self::VALUE_ABSTRACTION, $v, true)) {
                // array hasn't been prepped / could contain recursion
                $v = $this->utilities->valuePrep($v);
            } elseif (is_object($v) || is_resource($v)) {
                $v = $this->utilities->valuePrep($v);
            }
        }
        if (is_array($v)) {
            if (in_array(self::VALUE_ABSTRACTION, $v, true)) {
                list($type, $v) = $this->getValueAbstraction($v, $opts);
            }
            if (is_array($v)) {
                $type = 'array';
                $hist[] = 'array';
                foreach ($v as $k => $v2) {
                    $v[$k] = $this->getDisplayValue($v2, $opts, $hist);
                }
                if ($opts['flatten']) {
                    $v = trim(print_r($v, true));
                    if (count($hist) > 1) {
                        $v = str_replace("\n", "\n    ", $v);
                    }
                }
            }
        } elseif (is_string($v)) {
            $type = 'string';
            if (is_numeric($v)) {
                $typeMore = 'numeric';
            } elseif ($this->utilities->isBinary($v)) {
                // all or partially binary data
                $typeMore = 'binary';
                $v = $this->getBinary($v, $opts['html']);
            }
        } elseif (is_int($v)) {
            $type = 'int';
        } elseif (is_float($v)) {
            $type = 'float';
        } elseif (is_bool($v)) {
            $type = 'bool';
            $vStr = $v ? 'true' : 'false';
            if ($opts['boolNullToString']) {
                $v = $vStr;
            }
            $typeMore = $vStr;
        } elseif (is_null($v)) {
            $type = 'null';
            if ($opts['boolNullToString']) {
                $v = 'null';
            }
        }
        if ($opts['html']) {
            $v = $this->getValueHtml($v, $type, $typeMore);
        }
        return $v;
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
     * @param array $m matches
     *
     * @return string
     */
    protected function getBinaryCallback($m)
    {
        $stats = &$this->displayBinaryStats;
        $showHex = false;
        if ($m[1] !== '') {
            // single byte sequence (may contain control char)
            $str = $m[1];
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
        } elseif ($m[2] !== '') {
            // Valid byte sequence. return unmodified.
            $str = $m[2];
            $stats['utf8'] += strlen($str);
            $stats['cur_text_len'] += strlen($str);
            if ($str === "\xef\xbb\xbf") {
                // BOM
                $showHex = true;
            }
        } elseif ($m[3] !== '' || $m[4] !== '') {
            // Invalid byte
            $str = $m[3] != ''
                ? $m[3]
                : $m[4];
            $showHex = true;
        } else {
            // null char
            $str = $m[5];
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
            $undefined = "\x00".'undefined'."\x00";
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
                $values = array();
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
                $str .= '<tr valign="top"><td>'.$k.'</td>';
                foreach ($values as $v) {
                    // remove the span wrapper.. add span's class to TD
                    $class = null;
                    if (preg_match('#^<span class="([^"]+)">(.*)</span>$#s', $v, $matches)) {
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
            }
            $str .= '</tbody>'."\n".'</table>';
        }
        return $str;
    }

    /**
     * gets a value that has been abstracted
     * array (recursion), object, or resource
     *
     * @param array $v    abstracted value
     * @param array $opts options
     * @param array $hist {@internal - used to check for recursion}
     *
     * @return array [string $type, mixed $value]
     */
    protected function getValueAbstraction($v, $opts = array(), $hist = array())
    {
        $type = $v['type'];
        if ($type == 'object') {
            $type = 'object';
            if ($v['isRecursion']) {
                $v = '<span class="t_object">'
                        .'<span class="t_object-class">'.$v['class'].' object</span>'
                        .' <span class="t_recursion">*RECURSION*</span>'
                    .'</span>';
                if (!$opts['html']) {
                    $v = strip_tags($v);
                }
                $type = null;
            } else {
                $hist[] = &$v;
                $v = array(
                    'class'      => $v['class'].' object',
                    'properties' => $this->getDisplayValue($v['properties'], $opts, $hist),
                    'methods'    => $this->getDisplayValue($v['methods'], $opts, $hist),
                );
                if ($opts['html'] || $opts['flatten']) {
                    $v = $v['class']."\n"
                        .'    methods: '.$v['methods']."\n"
                        .'    properties: '.$v['properties'];
                    if ($opts['flatten'] && count($hist) > 1) {
                        $v = str_replace("\n", "\n    ", $v);
                    }
                }
            }
        } elseif ($type == 'array' && $v['isRecursion']) {
            $v = '<span class="t_array">'
                    .'<span class="t_keyword">Array</span>'
                    .' <span class="t_recursion">*RECURSION*</span>'
                .'</span>';
            if (!$opts['html']) {
                $v = strip_tags($v);
            }
            $type = null;
        } else {
            $v = $v['value'];
        }
        return array($type, $v);
    }

    /**
     * add markup to value
     *
     * @param mixed  $v        value
     * @param string $type     type
     * @param string $typeMore numeric, binary, true, or false
     *
     * @return string html
     */
    protected function getValueHtml($v, $type = null, $typeMore = null)
    {
        if ($type == 'array') {
            $html = '<span class="t_keyword">Array</span><br />'."\n"
                .'<span class="t_punct">(</span>'."\n"
                .'<span class="t_array-inner">'."\n";
            foreach ($v as $k => $v2) {
                $html .= "\t".'<span class="t_key_value">'
                        .'<span class="t_key">['.$k.']</span> '
                        .'<span class="t_operator">=&gt;</span> '
                        .$v2
                    .'</span>'."\n";
            }
            $html .= '</span>'
                .'<span class="t_punct">)</span>';
            $v = '<span class="t_'.$type.'">'.$html.'</span>';
        } elseif ($type == 'object') {
            $html = preg_replace(
                '#^([^\n]+)\n(.+)$#s',
                '<span class="t_object-class">\1</span>'."\n".'<span class="t_object-inner">\2</span>',
                $v
            );
            $html = preg_replace('#\sproperties: #', '<br />properties: ', $html);
            $v = '<span class="t_'.$type.'">'.$html.'</span>';
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
                    $v = htmlspecialchars($this->utilities->toUtf8($v), ENT_COMPAT, 'UTF-8');
                }
                $v = $this->visualWhiteSpace($v);
            }
            if (in_array($type, array('float','int')) || $typeMore == 'numeric') {
                $ts_now = time();
                $secs = 86400 * 90; // 90 days worth o seconds
                if ($v > $ts_now  - $secs && $v < $ts_now + $secs) {
                    $attribs['class'] .= ' timestamp';
                    $attribs['title'] = date('Y-m-d H:i:s', $v);
                }
            }
            $v = '<span '.$this->utilities->buildAttribString($attribs).'>'.$v.'</span>';
        }
        return $v;
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
        $br = $this->cfg['addBR'] ? '<br />' : '';
        $search = array("\r","\n");
        $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>'.$br."\n");
        return str_replace($search, $replace, $matches[1]);
    }
}
