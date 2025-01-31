<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Dump\Html\Value as ValDumper;

/**
 * Output array with HTML markup
 */
class HtmlArray
{
    /** @var Debug */
    public $debug;

    /** @var ValDumper */
    public $valDumper;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Dump\Html\Value instance
     */
    public function __construct(ValDumper $valDumper)
    {
        $this->debug = $valDumper->debug;
        $this->html = $this->debug->html;
        $this->valDumper = $valDumper;
    }

    /**
     * Dump array
     *
     * @param array            $array string value
     * @param Abstraction|null $abs   (optional) full abstraction
     *
     * @return string
     */
    public function dump(array $array, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        $opts = $this->optionsGet();
        if ($opts['isMaxDepth']) {
            $this->valDumper->optionSet('attribs.class.__push__', 'max-depth');
            return '<span class="t_keyword">array</span> <span class="t_maxDepth">*MAX DEPTH*</span>';
        }
        if (empty($array)) {
            return '<span class="t_keyword">array</span><span class="t_punct">()</span>';
        }
        if ($opts['expand'] !== null) {
            $this->valDumper->optionSet('attribs.data-expand', $opts['expand']);
        }
        if ($opts['asFileTree']) {
            $this->valDumper->optionSet('attribs.class.__push__', 'array-file-tree');
        }
        $keys = isset($abs['keys']) ? $abs['keys'] : array();
        $outputKeys = $opts['showListKeys'] || !$this->debug->arrayUtil->isList($array);
        return '<span class="t_keyword">array</span><span class="t_punct">(</span>' . "\n"
            . '<ul class="array-inner list-unstyled">' . "\n"
            . $this->dumpArrayValues($array, $outputKeys, $keys)
            . '</ul><span class="t_punct">)</span>';
    }

    /**
     * Dump an array key/value pair
     *
     * @param array $array      array to output
     * @param bool  $outputKeys include key with value?
     * @param array $absKeys    keys that required abstraction (ie, non-utf8, or containing confusable characters)
     *
     * @return string
     */
    private function dumpArrayValues(array $array, $outputKeys, array $absKeys)
    {
        $html = '';
        foreach ($array as $key => $val) {
            if (isset($absKeys[$key])) {
                $key = $absKeys[$key];
            }
            $html .= $outputKeys
                ? "\t" . '<li>'
                    . $this->html->buildTag(
                        'span',
                        array(
                            'class' => array(
                                't_int' => \is_int($key),
                                't_key' => true,
                            ),
                        ),
                        $this->valDumper->dump($key, array('tagName' => null)) // don't wrap it
                    )
                    . '<span class="t_operator">=&gt;</span>'
                    . $this->valDumper->dump($val)
                . '</li>' . "\n"
                : "\t" . $this->valDumper->dump($val, array('tagName' => 'li')) . "\n";
        }
        return $html;
    }

    /**
     * Return current options with defaults
     *
     * @return array<non-empty-string,mixed>
     */
    private function optionsGet()
    {
        return \array_merge(array(
            'asFileTree' => false,
            'expand' => null,
            'isMaxDepth' => false,
            'showListKeys' => true,
        ), $this->valDumper->optionGet());
    }
}
