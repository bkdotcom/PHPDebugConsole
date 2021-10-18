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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Dump\Html;

/**
 * Output object as HTML
 */
class HtmlObject
{

    protected $debug;
    protected $html;

	/**
     * Constructor
     *
     * @param Html $html Dump\Html instance
     */
	public function __construct(Html $html)
	{
		$this->debug = $html->debug;
        $this->html = $html;
	}

    /**
     * Dump object as html
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
                . $this->dumpProperties($abs)
                . $this->dumpMethods($abs)
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
     * Dump object attributes as html
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
            $str .= '<dd class="attribute">';
            $str .= $this->html->markupIdentifier($info['name']);
            if ($info['arguments']) {
                $str .= '<span class="t_punct">(</span>';
                $args = array();
                foreach ($info['arguments'] as $k => $v) {
                    $arg = '';
                    if (\is_string($k)) {
                        $arg .= '<span class="t_parameter-name">' . \htmlspecialchars($k) . '</span>'
                            . '<span class="t_punct">:</span>';
                    }
                    $arg .= $this->html->dump($v);
                    $args[] = $arg;
                }
                $str .= \implode('<span class="t_punct">,</span> ', $args);
                $str .= '<span class="t_punct">)</span>';
            }
            $str .= '</dd>' . "\n";
        }
        return $str;
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
        return $this->html->markupIdentifier($abs['className'], 'span', array(
            'title' => $outPhpDoc
                ? $title
                : null,
        ));
    }

    /**
     * Dump object constants as html
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
        $str = '<dt class="constants">constants</dt>' . "\n";
        foreach ($constants as $k => $info) {
            $modifiers = \array_keys(\array_filter(array(
                $info['visibility'] => true,
                'final' => $info['isFinal'],
            )));
            $str .= '<dd' . $this->debug->html->buildAttribString(array(
                'class' => 'constant ' . $info['visibility'],
                'data-attributes' => $outAttributes
                    ? ($info['attributes'] ?: null)
                    : null,
            )) . '>'
                . \implode(' ', \array_map(function ($modifier) {
                    return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
                }, $modifiers))
                . ' <span class="t_identifier" title="' . \htmlspecialchars($outPhpDoc ? $info['desc'] : '') . '">' . $k . '</span>'
                . ' <span class="t_operator">=</span> '
                . $this->html->dump($info['value'])
                . '</dd>' . "\n";
        }
        return $str;
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
                return '<dd class="extends">' . $this->html->markupIdentifier($classname) . '</dd>' . "\n";
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
                return '<dd class="interface">' . $this->html->markupIdentifier($classname) . '</dd>' . "\n";
            }, $abs['implements']));
    }

    /**
     * Dump object methods as html
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html fragment
     */
    protected function dumpMethods(Abstraction $abs)
    {
        if (!($abs['cfgFlags'] & AbstractObject::OUTPUT_METHODS)) {
            // we're not outputting methods
            return '';
        }
        $str = $this->dumpMethodsLabel($abs);
        if (!($abs['cfgFlags'] & AbstractObject::COLLECT_METHODS)) {
            return $str;
        }
        $opts = array(
            'outAttributesMethod' => $abs['cfgFlags'] & AbstractObject::OUTPUT_ATTRIBUTES_METHOD,
            'outAttributesParam' => $abs['cfgFlags'] & AbstractObject::OUTPUT_ATTRIBUTES_PARAM,
            'outMethodDesc' => $abs['cfgFlags'] & AbstractObject::OUTPUT_METHOD_DESC,
            'outPhpDoc' => $abs['cfgFlags'] & AbstractObject::OUTPUT_PHPDOC,
        );
        $methods = $abs['methods'];
        $magicMethods = \array_intersect(array('__call','__callStatic'), \array_keys($methods));
        $str .= $this->magicMethodInfo($magicMethods);
        foreach ($methods as $methodName => $info) {
            $str .= $this->dumpMethod($methodName, $info, $opts);
        }
        $str = \str_replace(array(
            ' data-deprecated-desc="null"',
            ' data-implements="null"',
            ' <span class="t_type"></span>',
        ), '', $str);
        return $str;
    }

    /**
     * Returns <dt class="methods">methods</dt>
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html fragment
     */
    protected function dumpMethodsLabel(Abstraction $abs)
    {
        $label = \count($abs['methods']) > 0
            ? 'methods'
            : 'no methods';
        if (!($abs['cfgFlags'] & AbstractObject::COLLECT_METHODS)) {
            $label = 'methods not collected';
        }
        return $this->debug->html->buildTag(
            'dt',
            array(
                'class' => 'methods',
            ),
            $label
        ) . "\n";
    }

    /**
     * Dump Method
     *
     * @param string $methodName method name
     * @param array  $info       method info
     * @param array  $opts       dump options
     *
     * @return string html fragment
     */
    protected function dumpMethod($methodName, $info, $opts)
    {
        $classes = \array_keys(\array_filter(array(
            'deprecated' => $info['isDeprecated'],
            'inherited' => $info['inheritedFrom'],
            'method' => true,
        )));
        $modifiers = \array_keys(\array_filter(array(
            'final' => $info['isFinal'],
            $info['visibility'] => true,
            'static' => $info['isStatic'],
        )));
        return $this->debug->html->buildTag(
            'dd',
            array(
                'class' => \array_merge($classes, $modifiers),
                'data-attributes' => $opts['outAttributesMethod']
                    ? ($info['attributes'] ?: null)
                    : null,
                'data-deprecated-desc' => isset($info['phpDoc']['deprecated'])
                    ? $info['phpDoc']['deprecated'][0]['desc']
                    : null,
                'data-implements' => $info['implements'],
                'data-inherited-from' => $info['inheritedFrom'],
            ),
            \implode(' ', \array_map(function ($modifier) {
                return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
            }, $modifiers)) . ' '
            . $this->html->markupType($info['return']['type'], array(
                'title' => $opts['outPhpDoc']
                    ? $info['return']['desc']
                    : '',
            )) . ' '
            . $this->dumpMethodName($methodName, $info, $opts)
            . $this->dumpMethodParams($info['params'], $opts)
            . ($methodName === '__toString'
                ? '<br />' . $this->html->dump($info['returnValue'])
                : '')
        ) . "\n";
    }

    /**
     * Dump method name with phpdoc summary & desc
     *
     * @param string $methodName method name
     * @param array  $info       method info
     * @param array  $opts       dump options
     *
     * @return string html fragment
     */
    protected function dumpMethodName($methodName, $info, $opts)
    {
        return $this->debug->html->buildTag(
            'span',
            array(
                'class' => 't_identifier',
                'title' => $opts['outPhpDoc']
                    ? \trim($info['phpDoc']['summary']
                        . ($opts['outMethodDesc']
                            ? "\n\n" . $info['phpDoc']['desc']
                            : ''))
                    : '',
            ),
            $methodName
        );
    }

    /**
     * Dump method parameters as HTML
     *
     * @param array $params params as returned from getParams()
     * @param array $opts   options
     *
     * @return string html fragment
     */
    protected function dumpMethodParams($params, $opts)
    {
        foreach ($params as $i => $info) {
            $paramStr = '<span' . $this->debug->html->buildAttribString(array(
                'class' => \array_keys(\array_filter(array(
                    'isPromoted' => $info['isPromoted'],
                    'parameter' => true,
                ))),
                'data-attributes' => $opts['outAttributesParam']
                    ? ($info['attributes'] ?: null)
                    : null,
            )) . '>';
            if (!empty($info['type'])) {
                $paramStr .= $this->html->markupType($info['type']) . ' ';
            }
            $paramStr .= $this->debug->html->buildTag(
                'span',
                array(
                    'class' => 't_parameter-name',
                    'title' => $opts['outPhpDoc']
                        ? $info['desc']
                        : '',
                ),
                \htmlspecialchars($info['name'])
            );
            if ($info['defaultValue'] !== Abstracter::UNDEFINED) {
                $paramStr .= ' <span class="t_operator">=</span> ';
                $parsed = $this->debug->html->parseTag($this->html->dump($info['defaultValue']));
                $parsed['attribs']['class'][] = 't_parameter-default';
                $paramStr .= $this->debug->html->buildTag(
                    'span',
                    $parsed['attribs'],
                    $parsed['innerhtml']
                );
            }
            $paramStr .= '</span>';
            $params[$i] = $paramStr;
        }
        return '<span class="t_punct">(</span>'
            . \implode('<span class="t_punct">,</span> ', $params)
            . '<span class="t_punct">)</span>';
    }

    /**
     * Dump object's phpDoc info as html
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
     * @param array  $value   parsed tag
     *
     * @return string html fragment
     */
    private function dumpPhpDocValue($tagName, $value)
    {
        if ($tagName === 'author') {
            $html = $value['name'];
            if ($value['email']) {
                $html .= ' &lt;<a href="mailto:' . $value['email'] . '">' . $value['email'] . '</a>&gt;';
            }
            if ($value['desc']) {
                $html .= ' ' . \htmlspecialchars($value['desc']);
            }
            return $html;
        }
        if ($tagName === 'link') {
            return '<a href="' . $value['uri'] . '" target="_blank">'
                . \htmlspecialchars($value['desc'] ?: $value['uri'])
                . '</a>';
        }
        if ($tagName === 'see' && $value['uri']) {
            return '<a href="' . $value['uri'] . '" target="_blank">'
                . \htmlspecialchars($value['desc'] ?: $value['uri'])
                . '</a>';
        }
        return \htmlspecialchars(\implode(' ', $value));
    }

    /**
     * Dump object properties as HTML
     *
     * @param Abstraction $abs object abstraction
     *
     * @return string html fragment
     */
    protected function dumpProperties(Abstraction $abs)
    {
        $label = 'no properties';
        if (\count($abs['properties'])) {
            $label = 'properties';
            if ($abs['viaDebugInfo']) {
                $label .= ' <span class="text-muted">(via __debugInfo)</span>';
            }
        }
        $opts = array(
            'outAttributes' => $abs['cfgFlags'] & AbstractObject::OUTPUT_ATTRIBUTES_PROP,
            'outPhpDoc' => $abs['cfgFlags'] & AbstractObject::OUTPUT_PHPDOC,
        );
        $magicMethods = \array_intersect(array('__get','__set'), \array_keys($abs['methods']));
        $str = '<dt class="properties">' . $label . '</dt>' . "\n";
        $str .= $this->magicMethodInfo($magicMethods);
        foreach ($abs['properties'] as $name => $info) {
            $info['name'] = $name;
            $str .= $this->dumpProperty($info, $opts) . "\n";
        }
        return $str;
    }

    /**
     * Dump object property as HTML
     *
     * @param array $info property info
     * @param array $opts options (currently just outputAttributes)
     *
     * @return string html fragment
     */
    private function dumpProperty($info, $opts)
    {
        $vis = (array) $info['visibility'];
        $info['isPrivateAncestor'] = \in_array('private', $vis) && $info['inheritedFrom'];
        $info['modifiers'] = $vis;
        if ($info['isStatic']) {
            $info['modifiers'][] = 'static';
        }
        $classes = \array_keys(\array_filter(array(
            'debug-value' => $info['valueFrom'] === 'debug',
            'debuginfo-excluded' => $info['debugInfoExcluded'],
            'debuginfo-value' => $info['valueFrom'] === 'debugInfo',
            'forceShow' => $info['forceShow'],
            'inherited' => $info['inheritedFrom'],
            'isPromoted' => $info['isPromoted'],
            'private-ancestor' => $info['isPrivateAncestor'],
            'property' => true,
        )));
        $classes = \array_merge($classes, $vis);
        $classes = \array_diff($classes, array('debug'));
        return $this->debug->html->buildTag(
            'dd',
            array(
                'class' => $classes, // pass as array
                'data-attributes' => $opts['outAttributes']
                    ? ($info['attributes'] ?: null)
                    : null,
                'data-inherited-from' => $info['inheritedFrom'],
            ),
            $this->dumpPropertyInner($info, $opts)
        );
    }

    /**
     * Build property inner html
     *
     * @param array $info property info
     * @param array $opts options (currently just outputAttributes)
     *
     * @return string html fragment
     */
    private function dumpPropertyInner($info, $opts)
    {
        $name = \str_replace('debug.', '', $info['name']);
        $modifiers = \array_map(function ($modifier) {
            return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
        }, $info['modifiers']);
        return \implode(' ', $modifiers)
            . ($info['isPrivateAncestor']
                // wrapped in span for css rule `.private-ancestor > *`
                ? ' <span>(' . $this->html->markupIdentifier($info['inheritedFrom'], 'i') . ')</span>'
                : '')
            . ($info['type']
                ? ' ' . $this->html->markupType($info['type'])
                : '')
            . ' <span class="t_identifier"'
                . ' title="' . ($opts['outPhpDoc'] ? \htmlspecialchars($info['desc']) : '') . '"'
                . '>' . $name . '</span>'
            . ($info['value'] !== Abstracter::UNDEFINED
                ? ' <span class="t_operator">=</span> '
                    . $this->html->dump($info['value'])
                : '');
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
        $toStringDump = $this->html->dump($val);
        $parsed = $this->debug->html->parseTag($toStringDump);
        $attribs['class'] = \array_merge($attribs['class'], $parsed['attribs']['class']);
        if (isset($parsed['attribs']['title'])) {
            // ie a timestamp will have a human readable date in title
            $attribs['title'] = ($attribs['title'] ? $attribs['title'] . ' : ' : '')
                . $parsed['attribs']['title'];
        }
        return $this->debug->html->buildTag(
            'span',
            $attribs,
            $parsed['innerhtml'] . $valAppend
        ) . "\n";
    }

    /**
     * Generate some info regarding the given method names
     *
     * @param array $methods method names
     *
     * @return string html fragment
     */
    private function magicMethodInfo($methods)
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
}
