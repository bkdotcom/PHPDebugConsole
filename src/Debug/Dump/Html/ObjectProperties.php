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
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\HtmlObject;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Dump object properties as HTML
 */
class ObjectProperties
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
     * Dump object properties as HTML
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        $opts = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::PROP_ATTRIBUTE_OUTPUT,
            'phpDocOutput' => $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT,
        );
        $magicMethods = \array_intersect(array('__get', '__set'), \array_keys($abs['methods']));
        $html = '<dt class="properties">' . $this->dumpPropertiesLabel($abs) . '</dt>' . "\n";
        $html .= $this->dumpObject->magicMethodInfo($magicMethods);
        $properties = $abs->sort($abs['properties'], $abs['sort']);
        foreach ($properties as $name => $info) {
            $info['className'] = $abs['className'];
            $info['isInherited'] = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
            $html .= $this->dumpProperty($name, $info, $opts) . "\n";
        }
        return $html;
    }

    /**
     * get property "header"
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string plain text
     */
    protected function dumpPropertiesLabel(Abstraction $abs)
    {
        if (\count($abs['properties']) === 0) {
            return 'no properties';
        }
        $label = 'properties';
        if ($abs['viaDebugInfo']) {
            $label .= ' <span class="text-muted">(via __debugInfo)</span>';
        }
        return $label;
    }

    /**
     * Dump property as HTML
     *
     * @param string $name property name
     * @param array  $info property info
     * @param array  $opts options
     *
     * @return string html fragment
     */
    protected function dumpProperty($name, array $info, array $opts)
    {
        $vis = (array) $info['visibility'];
        $info['isPrivateAncestor'] = \in_array('private', $vis, true) && $info['isInherited'];
        if ($info['isPrivateAncestor']) {
            $info['isInherited'] = false;
        }
        return $this->html->buildTag(
            'dd',
            $this->getAttribs($info, $opts),
            $this->dumpInner($name, $info, $opts)
        );
    }

    /**
     * Build property inner html
     *
     * @param string $name Property name
     * @param array  $info Property info
     * @param array  $opts options (currently just attributeOutput)
     *
     * @return string html fragment
     */
    protected function dumpInner($name, array $info, array $opts)
    {
        $name = \str_replace('debug.', '', $name);
        $parts = \array_filter(array(
            $this->dumpModifiers($info),
            $info['type']
                ? $this->helper->markupType($info['type'])
                : '',
            $this->valDumper->dump($name, array(
                'addQuotes' => \preg_match('#[\s\r\n]#u', $name) === 1 || $name === '',
                'attribs' => array(
                    'class' => array('t_identifier'),
                    'title' => $opts['phpDocOutput']
                        ? $info['desc']
                        : '',
                ),
            )),
            $info['value'] !== Abstracter::UNDEFINED
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
     * @param array $opts options
     *
     * @return array
     */
    protected function getAttribs(array $info, array $opts)
    {
        $visClasses = \array_diff((array) $info['visibility'], array('debug'));
        $overrides = $info['isInherited'] === false && $info['declaredPrev'];
        $classes = \array_keys(\array_filter(array(
            'debug-value' => $info['valueFrom'] === 'debug',
            'debuginfo-excluded' => $info['debugInfoExcluded'],
            'debuginfo-value' => $info['valueFrom'] === 'debugInfo',
            'forceShow' => $info['forceShow'],
            'inherited' => $info['isInherited'],
            'isDynamic' => $info['declaredLast'] === null
                && $info['valueFrom'] === 'value'
                && $info['className'] !== 'stdClass',
            'isPromoted' => $info['isPromoted'],
            'isReadOnly' => $info['isReadOnly'],
            'isStatic' => $info['isStatic'],
            'overrides' => $overrides,
            'private-ancestor' => $info['isPrivateAncestor'],
            'property' => true,
        )));
        return array(
            'class' => \array_merge($classes, $visClasses),
            'data-attributes' => $opts['attributeOutput'] && $info['attributes']
                ? $info['attributes']
                : null,
            'data-declared-prev' => $overrides
                ? $info['declaredPrev']
                : null,
            'data-inherited-from' => $info['isInherited'] || $info['isPrivateAncestor']
                ? $info['declaredLast']
                : null,
        );
    }

    /**
     * Get "modifiers"
     *
     * @param array $info Abstraction info
     *
     * @return string[]
     */
    protected function getModifiers(array $info)
    {
        $modifiers = (array) $info['visibility'];
        return \array_merge($modifiers, \array_keys(\array_filter(array(
            'readonly' => $info['isReadOnly'],
            'static' => $info['isStatic'],
        ))));
    }
}
