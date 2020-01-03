<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
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
     * @return string
     */
	public function dump(Abstraction $abs)
	{
        $title = \trim($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc']);
        $strClassName = $this->html->markupIdentifier($abs['className'], 'span', array(
            'title' => $title ?: null,
        ));
        if ($abs['isRecursion']) {
            return $strClassName
                . ' <span class="t_recursion">*RECURSION*</span>';
        }
        if ($abs['isExcluded']) {
            return $this->dumpToString($abs)
                . $strClassName . "\n"
                . '<span class="excluded">NOT INSPECTED</span>';
        }
        $html = $this->dumpToString($abs)
            . $strClassName . "\n"
            . '<dl class="object-inner">' . "\n"
                . '<dt>extends</dt>' . "\n"
                    . \implode(\array_map(function ($classname) {
                        return '<dd class="extends">' . $this->html->markupIdentifier($classname) . '</dd>' . "\n";
                    }, $abs['extends']))
                . '<dt>implements</dt>' . "\n"
                    . \implode(\array_map(function ($classname) {
                        return '<dd class="interface">' . $this->html->markupIdentifier($classname) . '</dd>' . "\n";
                    }, $abs['implements']))
                . $this->dumpConstants($abs)
                . $this->dumpProperties($abs)
                . $this->dumpMethods($abs)
                . $this->dumpPhpDoc($abs['phpDoc'])
            . '</dl>' . "\n";
        // remove <dt>'s that have no <dd>'
        $html = \preg_replace('#(?:<dt>(?:extends|implements|phpDoc)</dt>\n)+(<dt|</dl)#', '$1', $html);
        $html = \str_replace(' title=""', '', $html);
        return $html;
    }

    /**
     * Dump object's __toString or stringified value
     *
     * @param Abstraction $abs object abstraction
     *
     * @return string html
     */
    protected function dumpToString(Abstraction $abs)
    {
        $val = AbstractObject::toString($abs);
        if (!$val) {
            return '';
        }
        $valAppend = '';
        $len = \strlen($val);
        if ($len > 100) {
            $val = \substr($val, 0, 100);
            $valAppend = '&hellip; <i>(' . ($len - 100) . ' more chars)</i>';
        }
        $toStringDump = $this->html->dump($val);
        $parsed = $this->debug->utilities->parseTag($toStringDump);
        $classArray = \explode(' ', $parsed['attribs']['class']);
        $classArray[] = 't_stringified';
        if ($len > 100) {
            $classArray[] = 't_string_trunc';   // truncated
        }
        $attribs = array(
            'class' => $classArray,
            'title' => isset($parsed['attribs']['title'])
                // ie a timestamp will have a human readable date in title
                ? (!$abs['stringified'] ? '__toString() : ' : '') . $parsed['attribs']['title']
                : (!$abs['stringified'] ? '__toString()' : null),
        );
        return $this->debug->utilities->buildTag(
            'span',
            $attribs,
            $parsed['innerhtml'] . $valAppend
        ) . "\n";
    }

    /**
     * dump object constants as html
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html
     */
    protected function dumpConstants(Abstraction $abs)
    {
        $constants = $abs['constants'];
        if (!$constants || !($abs['flags'] & AbstractObject::OUTPUT_CONSTANTS)) {
            return '';
        }
        $str = '<dt class="constants">constants</dt>' . "\n";
        foreach ($constants as $k => $value) {
            $str .= '<dd class="constant">'
                . '<span class="t_identifier">' . $k . '</span>'
                . ' <span class="t_operator">=</span> '
                . $this->html->dump($value)
                . '</dd>' . "\n";
        }
        return $str;
    }

    /**
     * Dump object methods as html
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html
     */
    protected function dumpMethods(Abstraction $abs)
    {
        $collectMethods = $abs['flags'] & AbstractObject::COLLECT_METHODS;
        $outputMethods = $abs['flags'] & AbstractObject::OUTPUT_METHODS;
        if (!$collectMethods || !$outputMethods) {
            return '';
        }
        $methods = $abs['methods'];
        $label = \count($methods)
            ? 'methods'
            : 'no methods';
        $str = '<dt class="methods">' . $label . '</dt>' . "\n";
        $magicMethods = \array_intersect(array('__call','__callStatic'), \array_keys($methods));
        $str .= $this->magicMethodInfo($magicMethods);
        foreach ($methods as $methodName => $info) {
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
            $str .= $this->debug->utilities->buildTag(
                'dd',
                array(
                    'class' => \array_merge($classes, $modifiers),
                    'data-implements' => $info['implements'],
                ),
                \implode(' ', \array_map(function ($modifier) {
                    return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
                }, $modifiers))
                . ' ' . $this->html->markupType($info['return']['type'], array(
                    'title' => $info['return']['desc'],
                ))
                . ' ' . $this->debug->utilities->buildTag(
                    'span',
                    array(
                        'class' => 't_identifier',
                        'title' => \trim($info['phpDoc']['summary']
                            . ($abs['flags'] & AbstractObject::OUTPUT_METHOD_DESC
                                ? "\n\n" . $info['phpDoc']['desc']
                                : '')),
                    ),
                    $methodName
                )
                . '<span class="t_punct">(</span>'
                . $this->dumpMethodParams($info['params'])
                . '<span class="t_punct">)</span>'
                . ($methodName == '__toString'
                    ? '<br />' . $this->html->dump($info['returnValue'])
                    : '')
            ) . "\n";
        }
        $str = \str_replace(' data-implements="null"', '', $str);
        $str = \str_replace(' <span class="t_type"></span>', '', $str);
        return $str;
    }

    /**
     * Dump method parameters as HTML
     *
     * @param array $params params as returned from getParams()
     *
     * @return string html
     */
    protected function dumpMethodParams($params)
    {
        $paramStr = '';
        foreach ($params as $info) {
            $paramStr .= '<span class="parameter">';
            if (!empty($info['type'])) {
                $paramStr .= $this->html->markupType($info['type']) . ' ';
            }
            $paramStr .= $this->debug->utilities->buildTag(
                'span',
                array(
                    'class' => 't_parameter-name',
                    'title' => $info['desc'],
                ),
                \htmlspecialchars($info['name'])
            );
            if ($info['defaultValue'] !== Abstracter::UNDEFINED) {
                $paramStr .= ' <span class="t_operator">=</span> ';
                $parsed = $this->debug->utilities->parseTag($this->html->dump($info['defaultValue']));
                $parsed['attribs']['class'] .= ' t_parameter-default';
                $paramStr .= $this->debug->utilities->buildTag(
                    'span',
                    $parsed['attribs'],
                    $parsed['innerhtml']
                );
            }
            $paramStr .= '</span>, ';
        }
        $paramStr = \trim($paramStr, ', ');
        return $paramStr;
    }

    /**
     * Dump object's phpDoc info as html
     *
     * @param array $phpDoc parsed phpDoc
     *
     * @return string html
     */
    protected function dumpPhpDoc($phpDoc)
    {
        $str = '<dt>phpDoc</dt>' . "\n";
        foreach ($phpDoc as $k => $values) {
            if (!\is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                if ($k == 'author') {
                    $html = $value['name'];
                    if ($value['email']) {
                        $html .= ' &lt;<a href="mailto:' . $value['email'] . '">' . $value['email'] . '</a>&gt;';
                    }
                    if ($value['desc']) {
                        $html .= ' ' . \htmlspecialchars($value['desc']);
                    }
                    $value = $html;
                } elseif ($k == 'link') {
                    $value = '<a href="' . $value['uri'] . '" target="_blank">'
                        . \htmlspecialchars($value['desc'] ?: $value['uri'])
                        . '</a>';
                } elseif ($k == 'see' && $value['uri']) {
                    $value = '<a href="' . $value['uri'] . '" target="_blank">'
                        . \htmlspecialchars($value['desc'] ?: $value['uri'])
                        . '</a>';
                } else {
                    $value = \htmlspecialchars(\implode(' ', $value));
                }
                $str .= '<dd class="phpdoc phpdoc-' . $k . '">'
                    . '<span class="phpdoc-tag">' . $k . '</span>'
                    . '<span class="t_operator">:</span> '
                    . $value
                    . '</dd>' . "\n";
            }
        }
        return $str;
    }

    /**
     * Dump object properties as HTML
     *
     * @param Abstraction $abs object abstraction
     *
     * @return string
     */
    protected function dumpProperties(Abstraction $abs)
    {
        $label = \count($abs['properties'])
            ? 'properties'
            : 'no properties';
        if ($abs['viaDebugInfo']) {
            $label .= ' <span class="text-muted">(via __debugInfo)</span>';
        }
        $str = '<dt class="properties">' . $label . '</dt>' . "\n";
        $magicMethods = \array_intersect(array('__get','__set'), \array_keys($abs['methods']));
        $str .= $this->magicMethodInfo($magicMethods);
        foreach ($abs['properties'] as $k => $info) {
            $vis = (array) $info['visibility'];
            $isPrivateAncestor = \in_array('private', $vis) && $info['inheritedFrom'];
            $classes = \array_keys(\array_filter(array(
                'debuginfo-value' => $info['valueFrom'] == 'debugInfo',
                'debuginfo-excluded' => $info['debugInfoExcluded'],
                'forceShow' => $info['forceShow'],
                'debug-value' => $info['valueFrom'] == 'debug',
                'private-ancestor' => $isPrivateAncestor,
                'property' => true,
                \implode(' ', $vis) => $info['visibility'] !== 'debug',
            )));
            $modifiers = $vis;
            if ($info['isStatic']) {
                $modifiers[] = 'static';
            }
            $str .= '<dd class="' . \implode(' ', $classes) . '">'
                . \implode(' ', \array_map(function ($modifier) {
                    return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
                }, $modifiers))
                . ($isPrivateAncestor
                    // wrapped in span for css rule `.private-ancestor > *`
                    ? ' <span>(' . $this->html->markupIdentifier($info['inheritedFrom'], 'i') . ')</span>'
                    : '')
                . ($info['type']
                    ? ' ' . $this->html->markupType($info['type'])
                    : '')
                . ' <span class="t_identifier"'
                    . ' title="' . \htmlspecialchars($info['desc']) . '"'
                    . '>' . $k . '</span>'
                . ($info['value'] !== Abstracter::UNDEFINED
                    ? ' <span class="t_operator">=</span> '
                        . $this->html->dump($info['value'])
                    : '')
                . '</dd>' . "\n";
        }
        return $str;
    }

    /**
     * Generate some info regarding the given method names
     *
     * @param string[] $methods method names
     *
     * @return string
     */
    private function magicMethodInfo($methods)
    {
        if (!$methods) {
            return '';
        }
        foreach ($methods as $i => $method) {
            $methods[$i] = '<code>' . $method . '</code>';
        }
        $methods = $i == 0
            ? 'a ' . $methods[0] . ' method'
            : \implode(' and ', $methods) . ' methods';
        return '<dd class="magic info">This object has ' . $methods . '</dd>' . "\n";
    }
}
