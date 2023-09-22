<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\ObjectAbstraction;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Base class for dumping object constants/enum-cases/properties/methods
 */
abstract class AbstractObjectSection
{
    protected $helper;
    protected $html;
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
        $this->valDumper = $valDumper;
        $this->helper = $helper;
        $this->html = $html;
    }

    /**
     * Iterate over cases, constants, properties, or methods
     *
     * @param ObjectAbstraction $abs  Object abstraction
     * @param string            $what 'cases', 'constants', 'properties', or 'methods'
     * @param array             $cfg  config options
     *
     * @return string
     */
    public function dumpItems(ObjectAbstraction $abs, $what, array $cfg)
    {
        $html = '';
        $items = $abs->sort($abs[$what], $abs['sort']);
        foreach ($items as $name => $info) {
            $info = \array_merge(array(
                'className' => $abs['className'],
                'declaredLast' => null,
                'declaredPrev' => null,
            ), $info);
            $info['isInherited'] = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
            $html .= $this->dumpItem($name, $info, $cfg) . "\n";
        }
        return $html;
    }

    /**
     * Dump Property or Constant info as HTML
     *
     * @param string $name Property/costant name
     * @param array  $info Property/constant info
     * @param array  $cfg  config options
     *
     * @return string html fragment
     */
    protected function dumpItem($name, array $info, array $cfg)
    {
        $vis = (array) $info['visibility'];
        $info['isPrivateAncestor'] = \in_array('private', $vis, true) && $info['isInherited'];
        if ($info['isPrivateAncestor']) {
            $info['isInherited'] = false;
        }
        return $this->html->buildTag(
            'dd',
            $this->getAttribs($info, $cfg),
            $this->dumpItemInner($name, $info, $cfg)
        );
    }

    /**
     * Build property/constant/cate inner html
     *
     * @param string $name Property name
     * @param array  $info Property info
     * @param array  $cfg  options (currently just attributeOutput)
     *
     * @return string html fragment
     */
    protected function dumpItemInner($name, array $info, array $cfg)
    {
        $name = \str_replace('debug.', '', $name);
        $title = $cfg['phpDocOutput']
            ? (string) $info['desc']
            : '';
        $parts = \array_filter(array(
            '1_modifiers' => $this->dumpModifiers($info),
            '2_type' => isset($info['type'])
                ? $this->helper->markupType($info['type'])
                : '',
            '3_name' => $this->valDumper->dump($name, array(
                'addQuotes' => \preg_match('#[\s\r\n]#u', $name) === 1 || $name === '',
                'attribs' => array(
                    'class' => array('t_identifier'),
                    'title' => $title,
                ),
            )),
            '4_value' => $info['value'] !== Abstracter::UNDEFINED
                ? '<span class="t_operator">=</span> ' . $this->valDumper->dump($info['value'])
                : '',
        ));
        return \implode(' ', $parts);
    }

    /**
     * Dump "modifiers"
     *
     * @param array $info Abstraction info
     *
     * @return string html fragment
     */
    protected function dumpModifiers(array $info)
    {
        $modifiers = $this->getModifiers($info);
        return \implode(' ', \array_map(static function ($modifier) {
            return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
        }, $modifiers));
    }

    /**
     * Get html attributes
     *
     * @param array $info Abstraction info
     * @param array $cfg  config options
     *
     * @return array
     */
    protected function getAttribs(array $info, array $cfg = array())
    {
        $attribs = array(
            'class' => $this->getClasses($info),
            'data-attributes' => $info['attributes'],
            'data-declared-prev' => $info['declaredPrev'],
            'data-inherited-from' => $info['declaredLast'],
        );
        $filter = \array_filter(array(
            'class' => true,
            'data-attributes' => $cfg['attributeOutput'] && $info['attributes'],
            'data-declared-prev' => empty($info['isInherited']) && !empty($info['declaredPrev']),
            'data-inherited-from' => !empty($info['isInherited']) || $info['isPrivateAncestor'],
        ));
        return \array_intersect_key($attribs, $filter);
    }

    /**
     * Get classes
     *
     * @param array $info Abstraction info
     *
     * @return string[]
     */
    abstract protected function getClasses(array $info);

    /**
     * Get "modifiers" (final, readonly, static)
     *
     * @param array $info Abstraction info
     *
     * @return string[]
     */
    abstract protected function getModifiers(array $info);

    /**
     * Generate some info regarding the given method names
     *
     * @param array $methods method names
     *
     * @return string html fragment
     */
    protected function magicMethodInfo($methods)
    {
        if (!$methods) {
            return '';
        }
        $methods = \array_map(static function ($method) {
            return '<code>' . $method . '</code>';
        }, $methods);
        $methods = \count($methods) === 1
            ? 'a ' . $methods[0] . ' method'
            : \implode(' and ', $methods) . ' methods';
        return '<dd class="info magic">This object has ' . $methods . '</dd>' . "\n";
    }
}
