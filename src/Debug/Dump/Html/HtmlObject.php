<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\ObjectMethods;
use bdk\Debug\Dump\Html\ObjectProperties;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Output object as HTML
 */
class HtmlObject
{

    public $valDumper;
    protected $helper;
    protected $html;
    protected $methods;
    protected $properties;

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
        $this->methods = new ObjectMethods($this, $helper, $html);
        $this->properties = new ObjectProperties($this, $helper, $html);
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
            return $classname
                . ' <span class="t_recursion">*RECURSION*</span>';
        }
        if ($abs['isExcluded']) {
            return $this->dumpToString($abs)
                . $classname . "\n"
                . '<span class="excluded">NOT INSPECTED</span>';
        }
        $html = $this->dumpToString($abs)
            . $classname . "\n"
            . '<dl class="object-inner">' . "\n"
                . $this->dumpModifiers($abs)
                . $this->dumpExtends($abs)
                . $this->dumpImplements($abs)
                . $this->dumpAttributes($abs)
                . $this->dumpConstants($abs)
                . $this->properties->dump($abs)
                . $this->methods->dump($abs)
                . $this->dumpPhpDoc($abs)
            . '</dl>' . "\n";
        return $this->cleanup($html);
    }

    /**
     * Generate some info regarding the given method names
     *
     * @param array $methods method names
     *
     * @return string html fragment
     */
    public function magicMethodInfo($methods)
    {
        if (!$methods) {
            return '';
        }
        $methods = \array_map(function ($method) {
            return '<code>' . $method . '</code>';
        }, $methods);
        $methods = \count($methods) === 1
            ? 'a ' . $methods[0] . ' method'
            : \implode(' and ', $methods) . ' methods';
        return '<dd class="info magic">This object has ' . $methods . '</dd>' . "\n";
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
            ' data-inherited-from="null"',
            ' title=""',
        ), '', $html);
        return $html;
    }

    /**
     * Dump object attributes
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html fragment
     */
    protected function dumpAttributes(Abstraction $abs)
    {
        $attributes = $abs['attributes'];
        if (!$attributes || !($abs['cfgFlags'] & AbstractObject::OUTPUT_ATTRIBUTES_OBJ)) {
            return '';
        }
        $str = '<dt class="attributes">attributes</dt>' . "\n";
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
                    . '<span class="t_punct">:</span>';
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
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html fragment
     */
    protected function dumpClassname(Abstraction $abs)
    {
        $title = \trim($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc']);
        $outPhpDoc = $abs['cfgFlags'] & AbstractObject::OUTPUT_PHPDOC;
        return $this->valDumper->markupIdentifier($abs['className'], 'span', array(
            'title' => $outPhpDoc
                ? $title
                : null,
        ));
    }

    /**
     * Dump object constants
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html fragment
     */
    protected function dumpConstants(Abstraction $abs)
    {
        $constants = $abs['constants'];
        $opts = array(
            'outAttributes' => $abs['cfgFlags'] & AbstractObject::OUTPUT_ATTRIBUTES_CONST,
            'outConstants' => $abs['cfgFlags'] & AbstractObject::OUTPUT_CONSTANTS,
            'outPhpDoc' => $abs['cfgFlags'] & AbstractObject::OUTPUT_PHPDOC,
        );
        if (!$constants || !$opts['outConstants']) {
            return '';
        }
        $html = '<dt class="constants">constants</dt>' . "\n";
        foreach ($constants as $name => $info) {
            $html .= $this->dumpConstant($name, $info, $opts);
        }
        return $html;
    }

    /**
     * Dump Constant
     *
     * @param string $name Constant's name
     * @param array  $info Constant info
     * @param array  $opts Output options
     *
     * @return string html fragment
     */
    protected function dumpConstant($name, $info, $opts)
    {
        $modifiers = \array_keys(\array_filter(array(
            $info['visibility'] => true,
            'final' => $info['isFinal'],
        )));
        $title = $opts['outPhpDoc']
            ? (string) $info['desc']
            : '';
        return $this->html->buildTag(
            'dd',
            array(
                'class' => \array_merge(
                    array('constant'),
                    $modifiers
                ),
                'data-attributes' => $opts['outAttributes'] && $info['attributes']
                    ? $info['attributes']
                    : null,
            ),
            \implode(' ', \array_map(function ($modifier) {
                return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
            }, $modifiers))
            . ' <span class="t_identifier" title="' . \htmlspecialchars($title) . '">' . $name . '</span>'
            . ' <span class="t_operator">=</span> '
            . $this->valDumper->dump($info['value'])
        ) . "\n";
    }

    /**
     * Dump classnames of classes obj extends
     *
     * @param Abstraction $abs object "abstraction"
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
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html fragment
     */
    protected function dumpImplements(Abstraction $abs)
    {
        return '<dt>implements</dt>' . "\n"
            . \implode(\array_map(function ($classname) {
                return '<dd class="interface">' . $this->valDumper->markupIdentifier($classname) . '</dd>' . "\n";
            }, $abs['implements']));
    }

    /**
     * Dump method modifiers (final)
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpModifiers(Abstraction $abs)
    {
        return $abs['isFinal']
            ? '<dt class="t_modifier_final">final</dt>' . "\n"
            : '';
    }

    /**
     * Dump object's phpDoc info
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html fragment
     */
    protected function dumpPhpDoc(Abstraction $abs)
    {
        $str = '<dt>phpDoc</dt>' . "\n";
        foreach ($abs['phpDoc'] as $tagName => $values) {
            if (!\is_array($values)) {
                continue;
            }
            foreach ($values as $tagData) {
                $tagData['tagName'] = $tagName;
                $str .= $this->dumpPhpDocTag($tagData);
            }
        }
        return $str;
    }

    /**
     * Markup tag
     *
     * @param array $tagData parsed tag
     *
     * @return string html fragment
     */
    private function dumpPhpDocTag($tagData)
    {
        $tagName = $tagData['tagName'];
        switch ($tagName) {
            case 'author':
                $info = $this->dumpPhpDocTagAuthor($tagData);
                break;
            case 'link':
            case 'see':
                $info = '<a href="' . $tagData['uri'] . '" target="_blank">'
                    . \htmlspecialchars($tagData['desc'] ?: $tagData['uri'])
                    . '</a>';
                break;
            default:
                unset($tagData['tagName']);
                $info = \htmlspecialchars(\implode(' ', $tagData));
        }
        return '<dd class="phpdoc phpdoc-' . $tagName . '">'
            . '<span class="phpdoc-tag">' . $tagName . '</span>'
            . '<span class="t_operator">:</span> '
            . $info
            . '</dd>' . "\n";
    }

    /**
     * Dump PhpDoc author tag value
     *
     * @param array $tagData parsed tag
     *
     * @return string html partial
     */
    private function dumpPhpDocTagAuthor($tagData)
    {
        $html = $tagData['name'];
        if ($tagData['email']) {
            $html .= ' &lt;<a href="mailto:' . $tagData['email'] . '">' . $tagData['email'] . '</a>&gt;';
        }
        if ($tagData['desc']) {
            $html .= ' ' . \htmlspecialchars($tagData['desc']);
        }
        return $html;
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
                )))
            ),
            $parsed['innerhtml'] . $valAppend
        ) . "\n";
    }

    /**
     * Get object's "string" representation
     *
     * @param Abstraction $abs    Object Abstraction instance
     * @param int         $strlen updated to ength of non-truncated value
     *
     * @return string
     */
    private function getToStringVal(Abstraction $abs, &$strlen)
    {
        $val = $abs['className'];
        if ($abs['stringified']) {
            $val = $abs['stringified'];
        } elseif (isset($abs['methods']['__toString']['returnValue'])) {
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
