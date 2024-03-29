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

    /** @var array<string, callable> */
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
        $this->phpDoc = new ObjectPhpDoc($valDumper);
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
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        $classname = $this->dumpClassname($abs);
        if ($abs['isRecursion']) {
            return $classname . "\n" . '<span class="t_recursion">*RECURSION*</span>';
        }
        if ($abs['isMaxDepth']) {
            return $classname . "\n" . '<span class="t_maxDepth">*MAX DEPTH*</span>';
        }
        if ($abs['isExcluded']) {
            return $this->dumpToString($abs)
                . $classname . "\n" . '<span class="excluded">NOT INSPECTED</span>';
        }
        if (($abs['cfgFlags'] & AbstractObject::BRIEF) && \strpos(\json_encode($abs['implements']), '"UnitEnum"') !== false) {
            return $classname;
        }
        if (\strpos($abs['sort'], 'inheritance') === 0) {
            $this->valDumper->setDumpOpt('attribs.class.__push__', 'groupByInheritance');
        }
        $html = $this->dumpToString($abs)
            . $classname . "\n"
            . '<dl class="object-inner">' . "\n"
            . $this->dumpInner($abs)
            . '</dl>' . "\n";
        return $this->cleanup($html);
    }

    /**
     * Build implements tree
     *
     * @param array $implements         Implements structure
     * @param array $interfacesCollapse Interfaces that should initially be hidden
     *
     * @return string
     */
    private function buildImplementsTree(array $implements, array $interfacesCollapse)
    {
        $str = '<ul class="list-unstyled">' . "\n";
        foreach ($implements as $k => $v) {
            $classname = \is_array($v)
                ? $k
                : $v;
            $str .= '<li>'
                . $this->html->buildTag(
                    'span',
                    array(
                        'class' => array(
                            'interface' => true,
                            'toggle-off' => \in_array($classname, $interfacesCollapse, true),
                        ),
                    ),
                    $this->valDumper->markupIdentifier($classname)
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
            ' data-declared-prev="null"',
            ' data-inherited-from="null"',
            ' title=""',
        ), '', $html);
        return $html;
    }

    /**
     * Dump the object's details
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpInner(Abstraction $abs)
    {
        $html = $this->dumpModifiers($abs);
        foreach ($abs['sectionOrder'] as $sectionName) {
            $html .= $this->sectionCallables[$sectionName]($abs);
        }
        return $html;
    }

    /**
     * Dump object attributes
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpAttributes(Abstraction $abs)
    {
        $attributes = $abs['attributes'];
        if (!$attributes || !($abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_OUTPUT)) {
            return '';
        }
        $str = '<dt class="attributes">attributes</dt>' . "\n";
        $attributes = $abs->sort($attributes, $abs['sort']);
        foreach ($attributes as $info) {
            $str .= '<dd class="attribute">'
                . $this->valDumper->markupIdentifier($info['name'])
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
                $arg .= '<span class="t_parameter-name">' . \htmlspecialchars($name) . '</span>'
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
     * Dump classname of object
     * Classname may be wrapped in a span that includes phpDoc summary / desc
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpClassname(Abstraction $abs)
    {
        $phpDocOutput = $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT;
        $title = $phpDocOutput
            ? \trim($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc'])
            : null;
        $title = $title ?: null;
        if (\strpos(\json_encode($abs['implements']), '"UnitEnum"') !== false) {
            return $this->html->buildTag(
                'span',
                \array_filter(array(
                    'class' => 't_const',
                    'title' => $title,
                )),
                $this->valDumper->markupIdentifier($abs['className'] . '::' . $abs['properties']['name']['value'])
            );
        }
        return $this->valDumper->markupIdentifier($abs['className'], false, 'span', array(
            'title' => $title,
        ));
    }

    /**
     * Dump classnames of classes obj extends
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpExtends(Abstraction $abs)
    {
        return '<dt>extends</dt>' . "\n"
            . \implode(\array_map(function ($classname) {
                return '<dd class="extends">' . $this->valDumper->markupIdentifier($classname) . '</dd>' . "\n";
            }, $abs['extends']));
    }

    /**
     * Dump classnames of interfaces obj extends
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpImplements(Abstraction $abs)
    {
        if (empty($abs['implements'])) {
            return '';
        }
        return '<dt>implements</dt>' . "\n"
            . '<dd>' . $this->buildImplementsTree($abs['implements'], $abs['interfacesCollapse']) . '</dd>' . "\n";
    }

    /**
     * Dump modifiers (final & readonly)
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpModifiers(Abstraction $abs)
    {
        $modifiers = \array_keys(\array_filter(array(
            'final' => $abs['isFinal'],
            'readonly' => $abs['isReadOnly'],
        )));
        if (empty($modifiers)) {
            return '';
        }
        return '<dt class="modifiers">modifiers</dt>' . "\n"
            . \implode('', \array_map(static function ($modifier) {
                return '<dd class="t_modifier_' . $modifier . '">' . $modifier . '</dd>' . "\n";
            }, $modifiers));
    }

    /**
     * Dump object's __toString or stringified value
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpToString(Abstraction $abs)
    {
        $len = 0;
        $val = $this->getToStringVal($abs, $len);
        if ($val === $abs['className']) {
            return '';
        }
        $valAppend = '';
        $classes = array('t_stringified');
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
     * @param Abstraction $abs    Object Abstraction instance
     * @param int         $strlen updated to length of non-truncated value
     *
     * @return string|Abstraction
     */
    private function getToStringVal(Abstraction $abs, &$strlen)
    {
        $val = $abs['className'];
        if ($abs['stringified']) {
            $val = $abs['stringified'];
        } elseif (($abs['cfgFlags'] & AbstractObject::TO_STRING_OUTPUT) && isset($abs['methods']['__toString']['returnValue'])) {
            $val = $abs['methods']['__toString']['returnValue'];
        }
        if ($val instanceof Abstraction) {
            $strlen = $val['strlen'];
            $val = $val['value'];
        } elseif (\is_string($val)) {
            $strlen = \strlen($val);
        }
        return $val;
    }
}
