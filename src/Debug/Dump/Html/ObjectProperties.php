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
 * Dump object methods as HTML
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
     * @param HtmlHelper $helper  Html dump helpers
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
        $magicMethods = \array_intersect(array('__get','__set'), \array_keys($abs['methods']));
        $str = $this->dumpPropertiesLabel($abs);
        $str .= $this->dumpObject->magicMethodInfo($magicMethods);
        foreach ($abs['properties'] as $name => $info) {
            $str .= $this->dumpProperty($name, $info, $opts) . "\n";
        }
        return $str;
    }

    /**
     * Returns <dt class="properties">properties</dt>
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpPropertiesLabel(Abstraction $abs)
    {
        $label = 'no properties';
        if (\count($abs['properties'])) {
            $label = 'properties';
            if ($abs['viaDebugInfo']) {
                $label .= ' <span class="text-muted">(via __debugInfo)</span>';
            }
        }
        return '<dt class="properties">' . $label . '</dt>' . "\n";
    }

    /**
     * Dump object property as HTML
     *
     * @param string $name property name
     * @param array  $info property info
     * @param array  $opts options (currently just attributeOutput)
     *
     * @return string html fragment
     */
    protected function dumpProperty($name, $info, $opts)
    {
        $vis = (array) $info['visibility'];
        $info['isPrivateAncestor'] = \in_array('private', $vis, true) && $info['inheritedFrom'];
        return $this->html->buildTag(
            'dd',
            array(
                'class' => $this->propertyClasses($info),
                'data-attributes' => $opts['attributeOutput']
                    ? ($info['attributes'] ?: null)
                    : null,
                'data-inherited-from' => $info['inheritedFrom'],
            ),
            $this->dumpPropertyInner($name, $info, $opts)
        );
    }

    /**
     * Build property inner html
     *
     * @param string $name property name
     * @param array  $info property info
     * @param array  $opts options (currently just attributeOutput)
     *
     * @return string html fragment
     */
    private function dumpPropertyInner($name, $info, $opts)
    {
        $name = \str_replace('debug.', '', $name);
        return $this->dumpModifiers($info)
            . ($info['isPrivateAncestor']
                // wrapped in span for css rule `.private-ancestor > *`
                ? ' <span>(' . $this->valDumper->markupIdentifier($info['inheritedFrom'], false, 'i') . ')</span>'
                : '')
            . ($info['type']
                ? ' ' . $this->helper->markupType($info['type'])
                : '') . ' '
            . $this->html->buildTag('span', array(
                'class' => 't_identifier',
                'title' => $opts['phpDocOutput']
                    ? $info['desc']
                    : '',
            ), $name)
            . ($info['value'] !== Abstracter::UNDEFINED
                ? ' <span class="t_operator">=</span> '
                    . $this->valDumper->dump($info['value'])
                : '');
    }

    /**
     * Dump property modifiers
     *
     * @param array $info property info
     *
     * @return string html fragment
     */
    private function dumpModifiers($info)
    {
        $modifiers = (array) $info['visibility'];
        $modifiers = \array_merge($modifiers, \array_keys(\array_filter(array(
            'readonly' => $info['isReadOnly'],
            'static' => $info['isStatic'],
        ))));
        $modifiers = \array_map(static function ($modifier) {
            return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
        }, $modifiers);
        return \implode(' ', $modifiers);
    }

    /**
     * Get a list of css classnames for property markup
     *
     * @param array $info property info
     *
     * @return string[]
     */
    protected function propertyClasses($info)
    {
        $vis = (array) $info['visibility'];
        $classes = \array_keys(\array_filter(array(
            'debug-value' => $info['valueFrom'] === 'debug',
            'debuginfo-excluded' => $info['debugInfoExcluded'],
            'debuginfo-value' => $info['valueFrom'] === 'debugInfo',
            'forceShow' => $info['forceShow'],
            'inherited' => $info['inheritedFrom'],
            'isPromoted' => $info['isPromoted'],
            'isReadOnly' => $info['isReadOnly'],
            'isStatic' => $info['isStatic'],
            'private-ancestor' => $info['isPrivateAncestor'],
            'property' => true,
        )));
        $classes = \array_merge($classes, $vis);
        $classes = \array_diff($classes, array('debug'));
        return $classes;
    }
}
