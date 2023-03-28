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

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\HtmlObject;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Dump object constants and Enum cases as HTML
 */
class ObjectConstants
{
    protected $dumpObject;
    protected $valDumper;
    protected $helper;
    protected $html;

    /**
     * Constructor
     *
     * @param HtmlObject $dumpObj Html dumper
     * @param Helper     $helper  Html dump helpers
     * @param HtmlUtil   $html    Html methods
     */
    public function __construct(HtmlObject $dumpObj, Helper $helper, HtmlUtil $html)
    {
        $this->dumpObject = $dumpObj;
        $this->valDumper = $dumpObj->valDumper;
        $this->helper = $helper;
        $this->html = $html;
    }

    /**
     * Dump enum cases
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dumpCases(Abstraction $abs)
    {
        if (\in_array('UnitEnum', $abs['implements'], true) === false) {
            return '';
        }
        $cfg = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::CASE_ATTRIBUTE_OUTPUT,
            'caseCollect' => $abs['cfgFlags'] & AbstractObject::CASE_COLLECT,
            'caseOutput' => $abs['cfgFlags'] & AbstractObject::CASE_OUTPUT,
            'phpDocOutput' => $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT,
        );
        if (!$cfg['caseOutput']) {
            return '';
        }
        if (!$cfg['caseCollect']) {
            return '<dt class="cases">cases <i>not collected</i></dt>' . "\n";
        }
        $cases = $abs['cases'];
        if (!$cases) {
            return '<dt class="cases"><i>no cases!</i></dt>' . "\n";
        }
        $html = '<dt class="cases">cases</dt>' . "\n";
        foreach ($cases as $name => $info) {
            $html .= $this->dumpCase($name, $info, $cfg);
        }
        return $html;
    }

    /**
     * Dump object constants
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dumpConstants(Abstraction $abs)
    {
        $constants = $abs['constants'];
        $cfg = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::CONST_ATTRIBUTE_OUTPUT,
            'constCollect' => $abs['cfgFlags'] & AbstractObject::CONST_COLLECT,
            'constOutput' => $abs['cfgFlags'] & AbstractObject::CONST_OUTPUT,
            'phpDocOutput' => $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT,
        );
        if (!$cfg['constOutput']) {
            return '';
        }
        if (!$cfg['constCollect']) {
            return '<dt class="constants">constants <i>not collected</i></dt>' . "\n";
        }
        if (!$constants) {
            return '';
        }
        $html = '<dt class="constants">constants</dt>' . "\n";
        foreach ($constants as $name => $info) {
            $html .= $this->dumpConstant($name, $info, $cfg);
        }
        return $html;
    }

    /**
     * Dump Case
     *
     * @param string $name Case's name
     * @param array  $info Case info
     * @param array  $cfg  Case config vals
     *
     * @return string html fragment
     */
    protected function dumpCase($name, $info, $cfg)
    {
        $title = $cfg['phpDocOutput']
            ? (string) $info['desc']
            : '';
        return $this->html->buildTag(
            'dd',
            array(
                'class' => array('case'),
                'data-attributes' => $cfg['attributeOutput'] && $info['attributes']
                    ? $info['attributes']
                    : null,
            ),
            '<span class="t_identifier" title="' . \htmlspecialchars($title) . '">' . $name . '</span>'
                . ($info['value'] !== null
                    ? ' <span class="t_operator">=</span> '
                        . $this->valDumper->dump($info['value'])
                    : '')
        ) . "\n";
    }

    /**
     * Dump Constant
     *
     * @param string $name Constant's name
     * @param array  $info Constant info
     * @param array  $cfg  Constant config vals
     *
     * @return string html fragment
     */
    protected function dumpConstant($name, $info, $cfg)
    {
        $modifiers = \array_keys(\array_filter(array(
            $info['visibility'] => true,
            'final' => $info['isFinal'],
        )));
        $title = $cfg['phpDocOutput']
            ? (string) $info['desc']
            : '';
        return $this->html->buildTag(
            'dd',
            array(
                'class' => \array_merge(
                    array('constant'),
                    $modifiers
                ),
                'data-attributes' => $cfg['attributeOutput'] && $info['attributes']
                    ? $info['attributes']
                    : null,
            ),
            \implode(' ', \array_map(static function ($modifier) {
                return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
            }, $modifiers))
            . ' <span class="t_identifier" title="' . \htmlspecialchars($title) . '">' . $name . '</span>'
            . ' <span class="t_operator">=</span> '
            . $this->valDumper->dump($info['value'])
        ) . "\n";
    }
}
