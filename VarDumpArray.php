<?php
/**
 * Methods used to display arrays
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3b
 */

namespace bdk\Debug;

/**
 * Dump Array
 */
class VarDumpArray
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->debug = Debug::getInstance();
    }

    /**
     * output human-readable array structure
     *
     * @param mixed  $abs      array abstraction or array
     * @param string $outputAs ['html']
     * @param array  $path     {@internal}
     *
     * @return string|array depends on $outputAs
     */
    public function dump($abs, $outputAs = 'html', $path = array())
    {
        $isAbs = in_array(VarDump::ABSTRACTION, $abs, true);
        if (!$path && !$isAbs) {
            $isAbs = true;
            $abs = $this->getAbstraction($abs);
        }
        if ($isAbs && $abs['isRecursion']) {
            $val = '(array) *RECURSION*';
        } else {
            $val = $isAbs
                ? $abs['values']
                : $abs;
            $pathCount = count($path);
            foreach ($val as $k => $val2) {
                $path[$pathCount] = $k;
                $val[$k] = $this->debug->varDump->dump($val2, $outputAs, $path);
            }
        }
        if ($outputAs == 'html') {
            $val = $this->dumpAsHtml($val);
        } elseif ($outputAs == 'script') {
            // dump as is.. will get json_encoded
        } elseif ($outputAs == 'text') {
            $val = trim(print_r($val, true));
            $val = str_replace("Array\n(", 'Array(', $val);
            if (count($path) > 1) {
                $val = str_replace("\n", "\n    ", $val);
            }
        }
        return $val;
    }

    /**
     * get html output
     *
     * @param array $val array
     *
     * @return string html
     */
    protected function dumpAsHtml($val)
    {
        if ($val === '(array) *RECURSION*') {
            $html = '<span class="t_keyword">Array</span> <span class="t_recursion">*RECURSION*</span>';
        } elseif (empty($val)) {
            $html = '<span class="t_keyword">Array</span>'
                .'<span class="t_punct">(</span>'."\n"
                .'<span class="t_punct">)</span>';
        } else {
            $html = '<span class="t_keyword">Array</span>'
                .'<span class="t_punct">(</span>'."\n"
                .'<span class="array-inner">'."\n";
            foreach ($val as $k => $val2) {
                $html .= "\t".'<span class="key-value">'
                        .'<span class="t_key">['.$k.']</span> '
                        .'<span class="t_operator">=&gt;</span> '
                        .$val2
                    .'</span>'."\n";
            }
            $html .= '</span>'
                .'<span class="t_punct">)</span>';
        }
        $val = '<span class="t_array">'.$html.'</span>';
        return $val;
    }

    /**
     * returns information about an array
     *
     * @param array $array array to inspect
     * @param array $hist  (@internal) array/object history
     *
     * @return array
     */
    public function getAbstraction(&$array, $hist = array())
    {
        $return = array(
            'debug' => VarDump::ABSTRACTION,
            'type' => 'array',
            'values' => array(),
            'isRecursion' => in_array($array, $hist, true),
        );
        if (!$return['isRecursion']) {
            $lastHistI = count($hist) - 1;
            $isNestedArray = isset($hist[$lastHistI]) && is_array($hist[$lastHistI]);
            $hist[] = $array;
            foreach ($array as $k => $v) {
                if (is_array($v) || is_object($v) || is_resource($v)) {
                    $v = $this->debug->varDump->getAbstraction($array[$k], $hist);
                }
                $return['values'][$k] = $v;
            }
            if ($isNestedArray) {
                $return = $return['values'];
            }
        }
        return $return;
    }
}
