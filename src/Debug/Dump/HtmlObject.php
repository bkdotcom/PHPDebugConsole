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

namespace bdk\Debug\Dump;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Dump\Html as Dumper;
use bdk\Debug\Dump\HtmlHelper;
use bdk\Debug\Dump\HtmlObjectMethods;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Output object as HTML
 */
class HtmlObject
{

    public $dumper;
    protected $helper;
    protected $html;
    protected $methods;
    protected $properties;

	/**
     * Constructor
     *
     * @param Dumper     $dumper     Dump\Html instance
     * @param HtmlHelper $htmlHelper Html dump helpers
     * @param HtmlUtil   $html       Html methods
     */
	public function __construct(Dumper $dumper, HtmlHelper $htmlHelper, HtmlUtil $html)
	{
        $this->dumper = $dumper;
        $this->helper = $htmlHelper;
		$this->html = $html;
        $this->methods = new HtmlObjectMethods($this, $htmlHelper, $html);
        $this->properties = new HtmlObjectProperties($this, $htmlHelper, $html);
	}

    /**
     * Dump object
     *
     * @param Abstraction $abs object abstraction
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
                . ($abs['isFinal']
                    ? '<dt class="t_modifier_final">final</dt>' . "\n"
                    : ''
                )
                . $this->dumpExtends($abs)
                . $this->dumpImplements($abs)
                . $this->dumpAttributes($abs)
                . $this->dumpConstants($abs)
                . $this->properties->dump($abs)
                . $this->methods->dump($abs)
                . $this->dumpPhpDoc($abs)
            . '</dl>' . "\n";
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
                . $this->dumper->markupIdentifier($info['name'])
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
            $arg .= $this->dumper->dump($value);
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
        return $this->dumper->markupIdentifier($abs['className'], 'span', array(
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
        if (!$constants || !($abs['cfgFlags'] & AbstractObject::OUTPUT_CONSTANTS)) {
            return '';
        }
        $outAttributes = $abs['cfgFlags'] & AbstractObject::OUTPUT_ATTRIBUTES_CONST;
        $outPhpDoc = $abs['cfgFlags'] & AbstractObject::OUTPUT_PHPDOC;
        $html = '<dt class="constants">constants</dt>' . "\n";
        foreach ($constants as $k => $info) {
            $modifiers = \array_keys(\array_filter(array(
                $info['visibility'] => true,
                'final' => $info['isFinal'],
            )));
            $html .= $this->html->buildTag(
                'dd',
                array(
                    'class' => 'constant ' . $info['visibility'],
                    'data-attributes' => $outAttributes
                        ? ($info['attributes'] ?: null)
                        : null,
                ),
                \implode(' ', \array_map(function ($modifier) {
                    return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
                }, $modifiers))
                . ' <span class="t_identifier" title="' . \htmlspecialchars($outPhpDoc ? $info['desc'] : '') . '">' . $k . '</span>'
                . ' <span class="t_operator">=</span> '
                . $this->dumper->dump($info['value'])
            ) . "\n";
        }
        return $html;
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
                return '<dd class="extends">' . $this->dumper->markupIdentifier($classname) . '</dd>' . "\n";
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
                return '<dd class="interface">' . $this->dumper->markupIdentifier($classname) . '</dd>' . "\n";
            }, $abs['implements']));
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
        foreach ($abs['phpDoc'] as $k => $values) {
            if (!\is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                $str .= '<dd class="phpdoc phpdoc-' . $k . '">'
                    . '<span class="phpdoc-tag">' . $k . '</span>'
                    . '<span class="t_operator">:</span> '
                    . $this->dumpPhpDocValue($k, $value)
                    . '</dd>' . "\n";
            }
        }
        return $str;
    }

    /**
     * Markup tag
     *
     * @param string $tagName PhpDoc tag name
     * @param array  $tagData parsed tag
     *
     * @return string html fragment
     */
    private function dumpPhpDocValue($tagName, $tagData)
    {
        if ($tagName === 'author') {
            $html = $tagData['name'];
            if ($tagData['email']) {
                $html .= ' &lt;<a href="mailto:' . $tagData['email'] . '">' . $tagData['email'] . '</a>&gt;';
            }
            if ($tagData['desc']) {
                $html .= ' ' . \htmlspecialchars($tagData['desc']);
            }
            return $html;
        }
        if (\in_array($tagName, array('link','see')) && $tagData['uri']) {
            return '<a href="' . $tagData['uri'] . '" target="_blank">'
                . \htmlspecialchars($tagData['desc'] ?: $tagData['uri'])
                . '</a>';
        }
        return \htmlspecialchars(\implode(' ', $tagData));
    }

    /**
     * Dump object's __toString or stringified value
     *
     * @param Abstraction $abs object abstraction
     *
     * @return string html fragment
     */
    protected function dumpToString(Abstraction $abs)
    {
        $val = (string) $abs;
        if ($val === $abs['className']) {
            return '';
        }
        $len = $val instanceof Abstraction
            ? $val['strlen']
            : \strlen($val);
        $val = $val instanceof Abstraction
            ? $val['value']
            : $val;
        $valAppend = '';
        $attribs = array(
            'class' => array('t_stringified'),
            'title' => !$abs['stringified'] ? '__toString()' : null
        );
        if ($len > 100) {
            $val = \substr($val, 0, 100);
            $valAppend = '&hellip; <i>(' . ($len - 100) . ' more bytes)</i>';
            $attribs['class'][] = 't_string_trunc';   // truncated
        }
        $toStringDump = $this->dumper->dump($val);
        $parsed = $this->html->parseTag($toStringDump);
        $attribs['class'] = \array_merge($attribs['class'], $parsed['attribs']['class']);
        if (isset($parsed['attribs']['title'])) {
            // ie a timestamp will have a human readable date in title
            $attribs['title'] = ($attribs['title'] ? $attribs['title'] . ' : ' : '')
                . $parsed['attribs']['title'];
        }
        return $this->html->buildTag(
            'span',
            $attribs,
            $parsed['innerhtml'] . $valAppend
        ) . "\n";
    }
}
