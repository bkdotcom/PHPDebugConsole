<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Dump\Html\HtmlString;
use bdk\Debug\Dump\Html\Value as ValDumper;

/**
 * Output "encoded" string (ie base64, json, or serialized)
 */
class HtmlStringEncoded
{
    private $debug;
    private $htmlString;
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
     * Dump encoded string (base64, json, serialized)
     *
     * @param string      $val raw value dumped
     * @param Abstraction $abs full value abstraction
     *
     * @return string
     */
    public function dump($val, Abstraction $abs)
    {
        if ($abs['brief']) {
            $vals = $this->tabValues($abs);
            return $vals['valRaw'];
        }
        $tabs = $this->buildTabsAndPanes($abs);
        $html = $this->debug->html->buildTag(
            $this->valDumper->getDumpOpt('tagName'),
            array(
                'class' => 'string-encoded tabs-container',
                'data-type-more' => $abs['typeMore'], // dumpEncodedUpdateVals may set to null,
            ),
            "\n"
            . '<nav role="tablist">'
                . \implode('', $tabs['tabs'])
            . '</nav>' . "\n"
            . \implode('', $tabs['panes'])
        );
        $this->valDumper->setDumpOpt('tagName', null);
        return $html;
    }

    /**
     * [dumpEncodedGetTabs description]
     *
     * @param Abstraction $abs full value abstraction
     *
     * @return array
     */
    private function buildTabsAndPanes(Abstraction $abs)
    {
        $tabs = array(
            'tabs' => array(),
            'panes' => array(),
        );
        $index = 1;
        do {
            $vals = $this->tabValues($abs);
            $tabs['tabs'][] = $this->buildTab($vals['labelRaw'], $index);
            $tabs['panes'][] = $this->buildTabPane($vals['valRaw'], $index);
            $index++;
            $abs = $abs['valueDecoded'];
        } while ($abs instanceof Abstraction && $this->htmlString->isEncoded($abs));
        $tabs['tabs'][] = $this->buildTab($vals['labelDecoded'], $index, true);
        $tabs['panes'][] = $this->buildTabPane($this->valDumper->dump($abs), $index, true);
        return $tabs;
    }

    /**
     * [buildTab description]
     *
     * @param string $tab      Tab label
     * @param int    $index    Tab index
     * @param bool   $isActive Is this the active tab?
     *
     * @return string HTML snippet
     */
    private function buildTab($tab, $index, $isActive = false)
    {
        return $this->debug->html->buildTag(
            'a',
            array(
                'class' => array(
                    'active' => $isActive,
                    'nav-link',
                ),
                'data-target' => '.tab-' . $index,
                'data-toggle' => 'tab',
                'role' => 'tab',
            ),
            $tab
        );
    }

    /**
     * [buildTabPane description]
     *
     * @param string $tabBody  Tab body
     * @param int    $index    Tab index
     * @param bool   $isActive Is this the active tab?
     *
     * @return string HTML snippet
     */
    private function buildTabPane($tabBody, $index, $isActive = false)
    {
        return $this->debug->html->buildTag(
            'div',
            array(
                'class' => array(
                    'active' => $isActive,
                    'tab-' . $index,
                    'tab-pane',
                ),
                'role' => 'tabpanel',
            ),
            $tabBody
        ) . "\n";
    }

    /**
     * Dump encoded string (base64, json, serialized)
     *
     * @param Abstraction $abs full value abstraction
     *
     * @return string
     */
    private function tabValues(Abstraction $abs)
    {
        $attribs = $this->valDumper->getDumpOpt('attribs');
        $attribs['class'][] = 'no-quotes';
        $attribs['class'][] = 't_' . $abs['type'];
        if ($abs['typeMore'] === Abstracter::TYPE_STRING_BASE64 && $abs['brief']) {
            $this->valDumper->setDumpOpt('postDump', static function ($dumped) {
                return '<span class="t_keyword">string</span><span class="text-muted">(base64)</span><span class="t_punct colon">:</span> ' . $dumped;
            });
        }
        $vals = array(
            'labelDecoded' => 'decoded',
            'labelRaw' => 'raw',
            'valRaw' => $this->debug->html->buildTag(
                'span',
                $attribs,
                $this->valDumper->dump($abs['value'], array('tagName' => null))
            ),
        );
        return $this->tabValuesFinish($vals, $abs);
    }

    /**
     * Set string interpolation context values
     *
     * @param array       $vals context values for string interpolation
     * @param Abstraction $abs  full value abstraction
     *
     * @return array
     */
    private function tabValuesFinish($vals, Abstraction $abs)
    {
        switch ($abs['typeMore']) {
            case Abstracter::TYPE_STRING_BASE64:
                $vals['labelRaw'] = 'base64';
                if ($abs['strlen']) {
                    $vals['valRaw'] .= '<span class="maxlen">&hellip; ' . ($abs['strlen'] - \strlen($abs['value'])) . ' more bytes (not logged)</span>';
                }
                break;
            case Abstracter::TYPE_STRING_JSON:
                $vals['labelRaw'] = 'json';
                if ($abs['prettified'] || $abs['strlen']) {
                    $abs['typeMore'] = null; // unset typeMore to prevent loop
                    $vals['valRaw'] = $this->valDumper->dump($abs);
                    $abs['typeMore'] = 'json';
                }
                break;
            case Abstracter::TYPE_STRING_SERIALIZED:
                $vals['labelDecoded'] = 'unserialized';
                $vals['labelRaw'] = 'serialized';
                break;
        }
        return $vals;
    }
}
