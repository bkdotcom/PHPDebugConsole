<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.5
 */

namespace bdk\Debug\Dump\Html\Object;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Helper for enum objects
 */
class Enum
{
    /** @var Debug */
    protected $debug;

    /** @var Helper */
    protected $helper;

    /** @var HtmlUtil */
    protected $html;

    /** @var ValDumper */
    protected $valDumper;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Html dumper
     * @param Helper    $helper    Html dump helpers
     * @param HtmlUtil  $html      Html methods
     */
    public function __construct(ValDumper $valDumper, Helper $helper, HtmlUtil $html)
    {
        $this->debug = $valDumper->debug;
        $this->valDumper = $valDumper;
        $this->helper = $helper;
        $this->html = $html;
    }

    /**
     * Dump "brief" output for Enum
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dumpBrief(ObjectAbstraction $abs)
    {
        $className = $this->dumpClassName($abs);
        $parsed = $this->html->parseTag($className);
        $attribs = $this->debug->arrayUtil->mergeDeep(
            $this->valDumper->optionGet('attribs'),
            $parsed['attribs']
        );
        $this->valDumper->optionSet('dumpType', false); // exclude t_object classname
        $this->valDumper->optionSet('attribs', $attribs);
        return $parsed['innerhtml'];
    }

    /**
     * Dump className of object
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dumpClassName(ObjectAbstraction $abs)
    {
        $phpDocOutput = $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT;
        $title = isset($abs['properties']['value'])
            ? $this->debug->i18n->trans('word.value') . ': ' . $this->debug->getDump('text')->valDumper->dump($abs['properties']['value']['value'])
            : '';
        if ($phpDocOutput) {
            $phpDoc = \trim($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc']);
            $title .= "\n\n" . $this->helper->dumpPhpDoc($phpDoc);
        }
        $absTemp = new Abstraction(Type::TYPE_IDENTIFIER, array(
            'attribs' => array(
                'title' => \trim($title),
            ),
            'typeMore' => Type::TYPE_IDENTIFIER_CONST,
            'value' => $abs['className'] . '::' . $abs['properties']['name']['value'],
        ));
        return $this->valDumper->dump($absTemp);
    }
}
