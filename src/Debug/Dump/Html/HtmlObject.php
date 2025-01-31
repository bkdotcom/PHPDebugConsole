<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.1
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Object\Cases;
use bdk\Debug\Dump\Html\Object\Constants;
use bdk\Debug\Dump\Html\Object\ExtendsImplements;
use bdk\Debug\Dump\Html\Object\Methods;
use bdk\Debug\Dump\Html\Object\PhpDoc;
use bdk\Debug\Dump\Html\Object\Properties;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Output object as HTML
 */
class HtmlObject
{
    /** @var ValDumper */
    public $valDumper;

    /** @var Cases */
    protected $cases;

    /** @var Constants */
    protected $constants;

    /** @var ExtendsImplements */
    protected $extendsImplements;

    /** @var Helper */
    protected $helper;

    /** @var HtmlUtil */
    protected $html;

    /** @var Methods */
    protected $methods;

    /** @var PhpDoc */
    protected $phpDoc;

    /** @var Properties */
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
        $this->cases = new Cases($valDumper, $helper, $html);
        $this->constants = new Constants($valDumper, $helper, $html);
        $this->extendsImplements = new ExtendsImplements($valDumper, $helper, $html);
        $this->methods = new Methods($valDumper, $helper, $html);
        $this->phpDoc = new PhpDoc($valDumper, $helper);
        $this->properties = new Properties($valDumper, $helper, $html);
        $this->sectionCallables = array(
            'attributes' => [$this, 'dumpAttributes'],
            'cases' => [$this->cases, 'dump'],
            'constants' => [$this->constants, 'dump'],
            'extends' => [$this->extendsImplements, 'dumpExtends'],
            'implements' => [$this->extendsImplements, 'dumpImplements'],
            'methods' => [$this->methods, 'dump'],
            'phpDoc' => [$this->phpDoc, 'dump'],
            'properties' => [$this->properties, 'dump'],
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
            return $this->dumpEnumBrief($abs);
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
     * Remove empty tags and unneeded attributes
     *
     * @param string $html html fragment
     *
     * @return string html fragment
     */
    private function cleanup($html)
    {
        // remove <dt>'s that have no <dd>'
        $html = \preg_replace('#(?:<dt>(?:phpDoc)</dt>\n)+(<dt|</dl)#', '$1', $html);
        $html = \str_replace([
            ' data-attributes="null"',
            ' data-chars="[]"',
            ' data-declared-prev="null"',
            ' data-inherited-from="null"',
        ], '', $html);
        return $html;
    }

    /**
     * Dump "brief" output for Enum
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    private function dumpEnumBrief(ObjectAbstraction $abs)
    {
        $className = $this->dumpClassName($abs);
        $parsed = $this->html->parseTag($className);
        $attribs = $this->valDumper->debug->arrayUtil->mergeDeep(
            $this->valDumper->optionGet('attribs'),
            $parsed['attribs']
        );
        if ($this->valDumper->optionGet('tagName') !== 'td') {
            $this->valDumper->optionSet('tagName', 'span');
        }
        $this->valDumper->optionSet('type', null); // exclude t_object classname
        $this->valDumper->optionSet('attribs', $attribs);
        return $parsed['innerhtml'];
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
        $attributes = $abs->sort($attributes, $abs['sort']);
        return '<dt class="attributes">attributes</dt>' . "\n"
            . \implode(\array_map(function ($info) {
                return '<dd class="attribute">'
                    . $this->valDumper->markupIdentifier($info['name'], 'className')
                    . $this->dumpAttributeArgs($info['arguments'])
                    . '</dd>' . "\n";
            }, $attributes));
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
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpClassName(ObjectAbstraction $abs)
    {
        $isEnum = \strpos(\json_encode($abs['implements']), '"UnitEnum"') !== false;
        $phpDocOutput = $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT;
        $title = $isEnum && isset($abs['properties']['value'])
            ? 'value: ' . $this->valDumper->debug->getDump('text')->valDumper->dump($abs['properties']['value']['value'])
            : '';
        if ($phpDocOutput) {
            $phpDoc = \trim($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc']);
            $title .= "\n\n" . $this->helper->dumpPhpDoc($phpDoc);
        }
        $absTemp = new Abstraction(Type::TYPE_IDENTIFIER, array(
            'attribs' => array(
                'title' => \trim($title),
            ),
            'typeMore' => $isEnum
                ? Type::TYPE_IDENTIFIER_CONST
                : Type::TYPE_IDENTIFIER_CLASSNAME,
            'value' => $isEnum
                ? $abs['className'] . '::' . $abs['properties']['name']['value']
                : $abs['className'],
        ));
        return $this->valDumper->dump($absTemp);
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
            'lazy' => $abs['isLazy'],
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
        $classes = ['t_stringified'];
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
                'title' => \implode(' : ', \array_filter([
                    !$abs['stringified'] ? '__toString()' : null,
                    // ie a timestamp will have a human readable date in title
                    isset($parsed['attribs']['title']) ? $parsed['attribs']['title'] : null,
                ])),
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
