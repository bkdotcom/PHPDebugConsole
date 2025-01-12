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
use bdk\Debug\Dump\Html\HtmlString;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Utf8;

/**
 * Output binary string
 */
class HtmlStringBinary
{
    /** @var Debug */
    private $debug;

    /** @var HtmlString */
    private $htmlString;

    /** @var ValDumper */
    private $valDumper;

    /**
     * Constructor
     *
     * @param HtmlString $htmlString HtmlString instance
     */
    public function __construct(HtmlString $htmlString)
    {
        $this->htmlString = $htmlString;
        $this->debug = $htmlString->debug;
        $this->valDumper = $htmlString->valDumper;
    }

    /**
     * Dump binary string
     *
     * @param Abstraction $abs Binary string abstraction
     *
     * @return string
     */
    public function dump(Abstraction $abs)
    {
        $tagName = $this->valDumper->optionGet('tagName');
        $str = $this->dumpBasic($abs);
        $strLenDiff = $abs['strlen'] - $abs['strlenValue'];
        if ($abs['strlenValue'] && $strLenDiff) {
            $str .= '<span class="maxlen">&hellip; ' . $strLenDiff . ' more bytes (not logged)</span>';
        }
        if ($abs['brief']) {
            $this->valDumper->optionSet('tagName', null);
            return $this->dumpBrief($str, $abs);
        }
        if ($abs['percentBinary'] > 33 || $abs['contentType']) {
            $this->valDumper->optionSet('tagName', null);
            $this->valDumper->optionSet('postDump', $this->dumpPost($abs, $tagName));
        }
        return $str;
    }

    /**
     * Build dumped binary string
     *
     * @param Abstraction $abs Binary string abstraction
     *
     * @return string
     */
    private function dumpBasic(Abstraction $abs)
    {
        if ($abs['strlenValue'] === 0) {
            return '';
        }
        return isset($abs['chunks'])
            ? \implode('', \array_map(function (array $chunk) {
                return $chunk[0] === Utf8::TYPE_OTHER
                    ? '<span class="binary">\x' . \str_replace(' ', ' \\x', $chunk[1]) . '</span>'
                    : $this->htmlString->dump($chunk[1]);
            }, $abs['chunks']))
            : '<span class="binary">'
                . \substr(\chunk_split($abs['value'], 3 * 32, '<br />'), 0, -6)
                . '</span>';
    }

    /**
     * Dump binary string (brief)
     *
     * @param string      $str Dumped string
     * @param Abstraction $abs Binary string abstraction
     *
     * @return string
     */
    private function dumpBrief($str, Abstraction $abs)
    {
        return $abs['contentType']
            ? '<span class="t_keyword">string</span>'
                . '<span class="text-muted">(' . $abs['contentType'] . ')</span><span class="t_punct colon">:</span> '
                . $this->debug->utility->getBytes($abs['strlen'])
            : $str;
    }

    /**
     * Post-process binary string.
     * Display size, contentType, & data
     *
     * @param Abstraction $abs     String Abstraction
     * @param string      $tagName html tag (ie div,td, or span)
     *
     * @return Closure
     */
    private function dumpPost(Abstraction $abs, $tagName)
    {
        return function ($dumped) use ($abs, $tagName) {
            $lis = [];
            if ($abs['contentType']) {
                $lis[] = '<li>mime type = <span class="content-type t_string">' . $abs['contentType'] . '</span></li>';
            }
            $lis[] = '<li>size = <span class="t_int">' . $abs['strlen'] . '</span></li>';
            $lis[] = $dumped
                ? '<li class="t_string">' . $dumped . '</li>'
                : '<li>Binary data not collected</li>';
            $wrapped =  '<span class="t_keyword">string</span><span class="text-muted">(binary)</span>' . "\n"
                . $this->debug->html->buildTag(
                    'ul',
                    \array_filter(array(
                        'class' => ['list-unstyled', 'value-container'],
                        'data-type' => $abs['type'],
                        'data-type-more' => $abs['typeMore'],
                    )),
                    "\n" . \implode("\n", $lis) . "\n"
                );
            if ($tagName === 'td') {
                // wrap with td without adding class="binary t_string"
                $wrapped = '<td>' . $wrapped . '</td>';
            }
            return $wrapped;
        };
    }
}
