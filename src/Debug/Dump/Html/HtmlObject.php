<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.1
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Object\Cases;
use bdk\Debug\Dump\Html\Object\Constants;
use bdk\Debug\Dump\Html\Object\Enum;
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

    /** @var Debug */
    protected $debug;

    /** @var Enum */
    protected $enum;

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
        $this->debug = $valDumper->debug;
        $this->valDumper = $valDumper;
        $this->helper = $helper;
        $this->html = $html;
        $this->cases = new Cases($valDumper, $helper, $html);
        $this->constants = new Constants($valDumper, $helper, $html);
        $this->enum = new Enum($valDumper, $helper, $html);
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
        $html = $this->dumpSpecialCases($abs, $className);
        if ($html) {
            return $this->cleanup($html);
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
        return \str_replace([
            ' data-attributes="null"',
            ' data-chars="[]"',
            ' data-declared-prev="null"',
            ' data-inherited-from="null"',
        ], '', $html);
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
        return $this->isAsArray($abs)
            ? '<span class="t_punct">(</span>' . "\n" . $html . '<span class="t_punct">)</span>' . "\n"
            : $html;
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
        return '<dt class="attributes">' . $this->debug->i18n->trans('object.attributes') . '</dt>' . "\n"
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
        if ($abs->isEnum()) {
            return $this->enum->dumpClassName($abs);
        }
        $phpDocOutput = $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT;
        $title = '';
        if ($phpDocOutput) {
            $phpDoc = \trim($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc']);
            $title = $this->helper->dumpPhpDoc($phpDoc);
        }
        $absTemp = new Abstraction(Type::TYPE_IDENTIFIER, array(
            'attribs' => array(
                'title' => \trim($title),
            ),
            'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
            'value' => $abs['className'],
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
            : '<dt class="modifiers">' . $this->debug->i18n->trans('object.modifiers') . '</dt>' . "\n"
                . \implode('', \array_map(static function ($modifier) {
                    return '<dd class="t_modifier_' . $modifier . '">' . $modifier . '</dd>' . "\n";
                }, $modifiers));
    }

    /**
     * Handle special cases
     *
     * @param ObjectAbstraction $abs       Object Abstraction instance
     * @param string            $className Dumped class name
     *
     * @return string
     */
    protected function dumpSpecialCases(ObjectAbstraction $abs, $className)
    {
        if ($abs['isRecursion']) {
            return $className . "\n" . '<span class="t_recursion">*' . $this->debug->i18n->trans('abs.recursion') . '*</span>';
        }
        if ($abs['isMaxDepth']) {
            return $className . "\n" . '<span class="t_maxDepth">*' . $this->debug->i18n->trans('abs.max-depth') . '*</span>';
        }
        if ($abs['isExcluded']) {
            return $this->dumpToString($abs)
                . $className . "\n" . '<span class="excluded">' . $this->debug->i18n->trans('abs.not-inspected') . '</span>';
        }
        if (empty($abs['properties']) && $this->isAsArray($abs)) {
            return $className . '<span class="t_punct">()</span>';
        }
        if (($abs['cfgFlags'] & AbstractObject::BRIEF) && $abs->isEnum()) {
            return $this->enum->dumpBrief($abs);
        }
        return '';
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
            $valAppend = '&hellip; <i>(' . $this->debug->i18n->trans('string.more-bytes', array('bytes' => $len - 100)) . ')</i>';
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

    /**
     * Determine if object should be output as an array (condensed prop-only output)
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return bool
     */
    private function isAsArray(ObjectAbstraction $abs)
    {
        return $abs['className'] === 'stdClass'
            && ($abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT) === 0
            && ($abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_OUTPUT) === 0;
    }
}
