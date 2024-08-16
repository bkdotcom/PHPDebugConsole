<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\ObjectCases;
use bdk\Debug\Dump\Html\ObjectConstants;
use bdk\Debug\Dump\Html\ObjectMethods;
use bdk\Debug\Dump\Html\ObjectPhpDoc;
use bdk\Debug\Dump\Html\ObjectProperties;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Output object as HTML
 */
class HtmlObject
{
    /** @var ValDumper */
    public $valDumper;

    /** @var ObjectCases */
    protected $cases;

    /** @var ObjectConstants */
    protected $constants;

    /** @var Helper */
    protected $helper;

    /** @var HtmlUtil */
    protected $html;

    /** @var ObjectMethods */
    protected $methods;

    /** @var ObjectPhpDoc */
    protected $phpDoc;

    /** @var ObjectProperties */
    protected $properties;

    /** @var array<string,callable> */
    protected $sectionCallables;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Dump\Html instance
     * @param Helper    $helper    Html dump helpers
     * @param HtmlUtil  $html      Html methods
     */
    public function __construct(ValDumper $valDumper, Helper $helper, HtmlUtil $html)
    {
        $this->valDumper = $valDumper;
        $this->helper = $helper;
        $this->html = $html;
        $this->cases = new ObjectCases($valDumper, $helper, $html);
        $this->constants = new ObjectConstants($valDumper, $helper, $html);
        $this->methods = new ObjectMethods($valDumper, $helper, $html);
        $this->phpDoc = new ObjectPhpDoc($valDumper, $helper);
        $this->properties = new ObjectProperties($valDumper, $helper, $html);
        $this->sectionCallables = array(
            'attributes' => array($this, 'dumpAttributes'),
            'cases' => array($this->cases, 'dump'),
            'constants' => array($this->constants, 'dump'),
            'extends' => array($this, 'dumpExtends'),
            'implements' => array($this, 'dumpImplements'),
            'methods' => array($this->methods, 'dump'),
            'phpDoc' => array($this->phpDoc, 'dump'),
            'properties' => array($this->properties, 'dump'),
        );
    }

    /**
     * Dump object
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(ObjectAbstraction $abs)
    {
        $className = $this->dumpClassName($abs);
        if ($abs['isRecursion']) {
            return $className . "\n" . '<span class="t_recursion">*RECURSION*</span>';
        }
        if ($abs['isMaxDepth']) {
            return $className . "\n" . '<span class="t_maxDepth">*MAX DEPTH*</span>';
        }
        if ($abs['isExcluded']) {
            return $this->dumpToString($abs)
                . $className . "\n" . '<span class="excluded">NOT INSPECTED</span>';
        }
        if (($abs['cfgFlags'] & AbstractObject::BRIEF) && \strpos(\json_encode($abs['implements']), '"UnitEnum"') !== false) {
            $this->valDumper->optionSet('tagName', null);
            return $className;
        }
        $html = $this->dumpToString($abs)
            . $className . "\n"
            . $this->dumpInner($abs);
        if (\strpos($abs['sort'], 'inheritance') === 0) {
            $this->valDumper->optionSet('attribs.class.__push__', 'groupByInheritance');
        }
        // Were we debugged from inside or outside of the object?
        $this->valDumper->optionSet('attribs.data-accessible', $abs['scopeClass'] === $abs['className']
            ? 'private'
            : 'public');
        return $this->cleanup($html);
    }

    /**
     * Build implements tree
     *
     * @param list<string> $implements         Implements structure
     * @param list<string> $interfacesCollapse Interfaces that should initially be hidden
     *
     * @return string
     */
    private function buildImplementsTree(array $implements, array $interfacesCollapse)
    {
        $str = '<ul class="list-unstyled">' . "\n";
        foreach ($implements as $k => $v) {
            $className = \is_array($v)
                ? $k
                : $v;
            $str .= '<li>'
                . $this->html->buildTag(
                    'span',
                    array(
                        'class' => array(
                            'interface' => true,
                            'toggle-off' => \in_array($className, $interfacesCollapse, true),
                        ),
                    ),
                    $this->valDumper->markupIdentifier($className, 'classname')
                )
                . (\is_array($v) ? "\n" . self::buildImplementsTree($v, $interfacesCollapse) : '')
                . '</li>' . "\n";
        }
        $str .= '</ul>' . "\n";
        return $str;
    }

    /**
     * Remove empty tags and unneeded attributes
     *
     * @param string $html html fragment
     *
     * @return string html fragment
     */
    private function cleanup($html)
    {
        // remove <dt>'s that have no <dd>'
        $html = \preg_replace('#(?:<dt>(?:extends|implements|phpDoc)</dt>\n)+(<dt|</dl)#', '$1', $html);
        $html = \str_replace(array(
            ' data-attributes="null"',
            ' data-chars="[]"',
            ' data-declared-prev="null"',
            ' data-inherited-from="null"',
        ), '', $html);
        return $html;
    }

    /**
     * Dump the object's details
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpInner(ObjectAbstraction $abs)
    {
        $html = '<dl class="object-inner">' . "\n"
            . $this->dumpModifiers($abs);
        foreach ($abs['sectionOrder'] as $sectionName) {
            $html .= $this->sectionCallables[$sectionName]($abs);
        }
        $html .= '</dl>' . "\n";
        return $html;
    }

    /**
     * Dump object attributes
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpAttributes(ObjectAbstraction $abs)
    {
        $attributes = $abs['attributes'];
        if (!$attributes || !($abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_OUTPUT)) {
            return '';
        }
        $str = '<dt class="attributes">attributes</dt>' . "\n";
        $attributes = $abs->sort($attributes, $abs['sort']);
        foreach ($attributes as $info) {
            $str .= '<dd class="attribute">'
                . $this->valDumper->markupIdentifier($info['name'], 'classname')
                . $this->dumpAttributeArgs($info['arguments'])
                . '</dd>' . "\n";
        }
        return $str;
    }

    /**
     * Dump attribute arguments
     *
     * @param array $args Attribute arguments
     *
     * @return string html fragment
     */
    protected function dumpAttributeArgs($args)
    {
        if (!$args) {
            return '';
        }
        foreach ($args as $name => $value) {
            $arg = '';
            if (\is_string($name)) {
                $arg .= '<span class="t_parameter-name">' . $this->valDumper->dump($name, array(
                    'tagName' => null,
                    'type' => Type::TYPE_STRING,
                )) . '</span>'
                    . '<span class="t_punct t_colon">:</span>';
            }
            $arg .= $this->valDumper->dump($value);
            $args[$name] = $arg;
        }
        return '<span class="t_punct">(</span>'
            . \implode('<span class="t_punct">,</span> ', $args)
            . '<span class="t_punct">)</span>';
    }

    /**
     * Dump className of object
     * ClassName may be wrapped in a span that includes phpDoc summary / desc
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpClassName(ObjectAbstraction $abs)
    {
        $phpDocOutput = $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT;
        $title = $phpDocOutput
            ? $this->helper->dumpPhpDoc($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc'])
            : null;
        if (\strpos(\json_encode($abs['implements']), '"UnitEnum"') !== false) {
            return $this->html->buildTag(
                'span',
                \array_filter(array(
                    'class' => \array_merge($this->valDumper->optionGet('attribs')['class'], array('t_const')),
                    'title' => $title,
                )),
                $this->valDumper->markupIdentifier($abs['className'] . '::' . $abs['properties']['name']['value'])
            );
        }
        return $this->valDumper->markupIdentifier($abs['className'], 'classname', 'span', array(
            'title' => $title,
        ));
    }

    /**
     * Dump classNames of classes obj extends
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpExtends(ObjectAbstraction $abs)
    {
        return '<dt>extends</dt>' . "\n"
            . \implode(\array_map(function ($className) {
                return '<dd class="extends">' . $this->valDumper->markupIdentifier($className, 'classname') . '</dd>' . "\n";
            }, $abs['extends']));
    }

    /**
     * Dump classNames of interfaces obj extends
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpImplements(ObjectAbstraction $abs)
    {
        return empty($abs['implements'])
            ? ''
            : '<dt>implements</dt>' . "\n"
                . '<dd class="implements">' . $this->buildImplementsTree($abs['implements'], $abs['interfacesCollapse']) . '</dd>' . "\n";
    }

    /**
     * Dump modifiers (final & readonly)
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpModifiers(ObjectAbstraction $abs)
    {
        $modifiers = \array_keys(\array_filter(array(
            'abstract' => $abs['isAbstract'],
            'final' => $abs['isFinal'],
            'interface' => $abs['isInterface'],
            'readonly' => $abs['isReadOnly'],
            'trait' => $abs['isTrait'],
        )));
        return empty($modifiers)
            ? ''
            : '<dt class="modifiers">modifiers</dt>' . "\n"
                . \implode('', \array_map(static function ($modifier) {
                    return '<dd class="t_modifier_' . $modifier . '">' . $modifier . '</dd>' . "\n";
                }, $modifiers));
    }

    /**
     * Dump object's __toString or stringified value
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpToString(ObjectAbstraction $abs)
    {
        $len = 0;
        $val = $this->getToStringVal($abs, $len);
        $valAppend = '';
        $classes = array('t_stringified');
        if ($val === $abs['className']) {
            return '';
        }
        if ($len > 100) {
            $classes[] = 't_string_trunc';   // truncated
            $val = \substr($val, 0, 100);
            $valAppend = '&hellip; <i>(' . ($len - 100) . ' more bytes)</i>';
        }
        $valDumped = $this->valDumper->dump($val);
        $parsed = $this->html->parseTag($valDumped);
        return $this->html->buildTag(
            'span',
            array(
                'class' => \array_merge($classes, $parsed['attribs']['class']),
                'title' => \implode(' : ', \array_filter(array(
                    !$abs['stringified'] ? '__toString()' : null,
                    // ie a timestamp will have a human readable date in title
                    isset($parsed['attribs']['title']) ? $parsed['attribs']['title'] : null,
                ))),
            ),
            $parsed['innerhtml'] . $valAppend
        ) . "\n";
    }

    /**
     * Get object's "string" representation
     *
     * @param ObjectAbstraction $abs    Object Abstraction instance
     * @param int               $strlen updated to length of non-truncated value
     *
     * @return string|Abstraction
     */
    private function getToStringVal(ObjectAbstraction $abs, &$strlen)
    {
        $val = $abs['className'];
        if ($abs['stringified']) {
            $val = $abs['stringified'];
        } elseif (($abs['cfgFlags'] & AbstractObject::TO_STRING_OUTPUT) && isset($abs['methods']['__toString']['returnValue'])) {
            $val = $abs['methods']['__toString']['returnValue'];
        }
        $strlen = $val instanceof Abstraction && $val['strlen']
            ? $val['strlen']
            : \strlen((string) $val);
        return $val;
    }
}
