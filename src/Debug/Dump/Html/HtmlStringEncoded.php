<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.2
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\HtmlString;
use bdk\Debug\Dump\Html\Value as ValDumper;

/**
 * Output "encoded" string (ie base64, json, or serialized)
 */
class HtmlStringEncoded
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
     * Dump encoded string (base64, json, serialized)
     *
     * @param Abstraction $abs Encoded string abstraction
     *
     * @return string
     */
    public function dump(Abstraction $abs)
    {
        if ($abs['brief']) {
            $vals = $this->tabValues($abs);
            return $vals['valRaw'];
        }
        $tabs = $this->buildTabsAndPanes($abs);
        $html = $this->debug->html->buildTag(
            $this->valDumper->optionGet('tagName'),
            array(
                'class' => 'string-encoded tabs-container',
                'data-type-more' => $abs['typeMore'],
            ),
            "\n"
            . '<nav role="tablist">'
                . \implode('', $tabs['tabs'])
            . '</nav>' . "\n"
            . \implode('', $tabs['panes'])
        );
        $this->valDumper->optionSet('tagName', null);
        return $html;
    }

    /**
     * Add prefix to brief output
     *
     * @param Abstraction $abs  Abstraction
     * @param array       $vals context values for string interpolation
     *
     * @return void
     */
    private function briefPrefix(Abstraction $abs, array $vals)
    {
        $labelFinal = '';
        while ($this->htmlString->isEncoded($abs) && $abs['valueDecoded'] instanceof Abstraction) {
            $abs = $abs['valueDecoded'];
            $labelFinal = '⇢' . ($abs['contentType'] ?: $abs['typeMore']);
        }
        $label = $vals['labelRaw'] . $labelFinal;
        $this->valDumper->optionSet('postDump', static function ($dumped) use ($label) {
            return '<span class="t_keyword">string</span><span class="text-muted">(' . $label . ')</span><span class="t_punct colon">:</span> ' . $dumped;
        });
    }

    /**
     * Build tabs and panes
     *
     * @param Abstraction $abs full value abstraction
     *
     * @return array
     */
    private function buildTabsAndPanes(Abstraction $abs)
    {
        $tabs = array(
            'panes' => [],
            'tabs' => [],
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
        $tabs['panes'][] = $this->buildTabPane($this->valDumper->dump($abs, array('addQuotes' => true)), $index, true);
        return $tabs;
    }

    /**
     * Built tab link
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
                    'nav-link' => true,
                ),
                'data-target' => '.tab-' . $index,
                'data-toggle' => 'tab',
                'role' => 'tab',
            ),
            $tab
        );
    }

    /**
     * Build tab pane
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
                    'tab-' . $index => true,
                    'tab-pane' => true,
                ),
                'role' => 'tabpanel',
            ),
            $tabBody
        ) . "\n";
    }

    /**
     * Get tab labels
     *
     * @param string $typeMore typeMore
     *
     * @return array
     */
    private function tabLabels($typeMore)
    {
        $labelsRaw = array(
            Type::TYPE_STRING_BASE64 => 'base64',
            Type::TYPE_STRING_FORM => 'form',
            Type::TYPE_STRING_JSON => 'json',
            Type::TYPE_STRING_SERIALIZED => 'serialized',
        );
        $labelsDecoded = array(
            Type::TYPE_STRING_BASE64 => 'decoded',
            Type::TYPE_STRING_FORM => 'parsed',
            Type::TYPE_STRING_JSON => 'parsed',
            Type::TYPE_STRING_SERIALIZED => 'unserialized',
        );
        return array(
            'decoded' => isset($labelsDecoded[$typeMore])
                ? $labelsDecoded[$typeMore]
                : 'decoded',
            'raw' =>  isset($labelsRaw[$typeMore])
                ? $labelsRaw[$typeMore]
                : 'raw',
        );
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
        $attribs = $this->valDumper->optionGet('attribs');
        $attribs['class'][] = 'no-quotes';
        $attribs['class'][] = 't_' . $abs['type'];
        $labels = $this->tabLabels($abs['typeMore']);
        $vals = array(
            'labelDecoded' => $labels['decoded'],
            'labelRaw' => $labels['raw'],
            'valRaw' => $this->debug->html->buildTag(
                'span',
                $attribs,
                $this->valDumper->dump($abs['value'], array(
                    'tagName' => null,
                    'type' => $abs['type'],
                ))
            ),
        );
        $vals = $this->tabValuesFinish($vals, $abs);
        if ($abs['brief']) {
            $this->briefPrefix($abs, $vals);
        }
        return $vals;
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
        $strLenDiff = $abs['strlen'] - $abs['strlenValue'];
        if ($strLenDiff) {
            $vals['valRaw'] .= '<span class="maxlen">&hellip; ' . $strLenDiff . ' more bytes (not logged)</span>';
        }
        $method = 'tabValuesFinish' . \ucfirst($abs['typeMore']);
        if (\method_exists($this, $method)) {
            $vals = $this->$method($vals, $abs);
        }
        return $vals;
    }

    /**
     * Finish json abstraction
     *
     * @param array       $vals context values for string interpolation
     * @param Abstraction $abs  full value abstraction
     *
     * @return array
     *
     * @disregard P1003 Symbol declared but not used
     */
    private function tabValuesFinishJson($vals, Abstraction $abs)
    {
        if ($abs['brief']) {
            return $vals;
        }
        $strLenDiff = $abs['strlen'] - $abs['strlenValue'];
        if ($abs['prettified'] || $strLenDiff) {
            $abs['typeMore'] = null; // unset typeMore to prevent loop
            $vals['valRaw'] = $this->valDumper->dump($abs);
            $abs['typeMore'] = Type::TYPE_STRING_JSON;
        }
        return $vals;
    }
}
