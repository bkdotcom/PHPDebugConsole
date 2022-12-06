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
class ObjectMethods
{
    protected $dumpObject;
    protected $valDumper;
    protected $helper;
    protected $html;
    protected $opts = array();

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
     * Dump object methods as html
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        if (!($abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT)) {
            // we're not outputting methods
            return '';
        }
        $str = $this->dumpMethodsLabel($abs);
        if (!($abs['cfgFlags'] & AbstractObject::METHOD_COLLECT)) {
            return $str;
        }
        $this->opts = array(
            'methodAttributeOutput' => $abs['cfgFlags'] & AbstractObject::METHOD_ATTRIBUTE_OUTPUT,
            'methodDescOutput' => $abs['cfgFlags'] & AbstractObject::METHOD_DESC_OUTPUT,
            'paramAttributeOutput' => $abs['cfgFlags'] & AbstractObject::PARAM_ATTRIBUTE_OUTPUT,
            'phpDocOutput' => $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT,
        );
        $methods = $abs['methods'];
        $magicMethods = \array_intersect(array('__call','__callStatic'), \array_keys($methods));
        $str .= $this->dumpObject->magicMethodInfo($magicMethods);
        foreach ($methods as $methodName => $info) {
            $str .= $this->dumpMethod($methodName, $info);
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
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpMethodsLabel(Abstraction $abs)
    {
        $label = \count($abs['methods']) > 0
            ? 'methods'
            : 'no methods';
        if (!($abs['cfgFlags'] & AbstractObject::METHOD_COLLECT)) {
            $label = 'methods <i>not collected</i>';
        }
        return '<dt class="methods">' . $label . '</dt>' . "\n";
    }

    /**
     * Dump Method
     *
     * @param string $methodName method name
     * @param array  $info       method info
     *
     * @return string html fragment
     */
    protected function dumpMethod($methodName, $info)
    {
        return $this->html->buildTag(
            'dd',
            $this->methodAttribs($info),
            $this->dumpModifiers($info) . ' '
                . $this->dumpName($methodName, $info)
                . $this->dumpParams($info['params'])
                . $this->dumpReturnType($info)
                . ($methodName === '__toString'
                    ? '<br />' . "\n" . $this->valDumper->dump($info['returnValue'])
                    : '')
        ) . "\n";
    }

    /**
     * Dump method modifiers
     *
     * @param array $info method info
     *
     * @return string
     */
    protected function dumpModifiers($info)
    {
        $modifiers = \array_keys(\array_filter(array(
            'final' => $info['isFinal'],
            $info['visibility'] => true,
            'static' => $info['isStatic'],
        )));
        return ''
            . \implode(' ', \array_map(static function ($modifier) {
                    return '<span class="t_modifier_' . $modifier . '">' . $modifier . '</span>';
            }, $modifiers));
    }

    /**
     * Dump method name with phpdoc summary & desc
     *
     * @param string $methodName method name
     * @param array  $info       method info
     *
     * @return string html fragment
     */
    protected function dumpName($methodName, $info)
    {
        return $this->html->buildTag(
            'span',
            array(
                'class' => 't_identifier',
                'title' => $this->opts['phpDocOutput']
                    ? \trim($info['phpDoc']['summary']
                        . ($this->opts['methodDescOutput']
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
     *
     * @return string html fragment
     */
    protected function dumpParams($params)
    {
        foreach ($params as $i => $info) {
            $params[$i] = $this->dumpParam($info);
        }
        return '<span class="t_punct">(</span>'
            . \implode('<span class="t_punct">,</span> ', $params)
            . '<span class="t_punct">)</span>';
    }

    /**
     * Dump a single parameter
     *
     * @param array $info parameter info
     *
     * @return string html fragment
     */
    protected function dumpParam($info)
    {
        $html = '<span' . $this->html->buildAttribString(array(
            'class' => array(
                'isPromoted' => $info['isPromoted'],
                'parameter' => true,
            ),
            'data-attributes' => $this->opts['paramAttributeOutput']
                ? ($info['attributes'] ?: null)
                : null,
        )) . '>';
        if (!empty($info['type'])) {
            $html .= $this->helper->markupType($info['type']) . ' ';
        }
        $html .= $this->html->buildTag(
            'span',
            array(
                'class' => 't_parameter-name',
                'title' => $this->opts['phpDocOutput']
                    ? $info['desc']
                    : '',
            ),
            \htmlspecialchars($info['name'])
        );
        $html .= $this->dumpParamDefault($info['defaultValue']);
        $html .= '</span>';
        return $html;
    }

    /**
     * Dump defaultValue if defined
     *
     * @param mixed $defaultValue Default value
     *
     * @return string empty string or html snippet
     */
    protected function dumpParamDefault($defaultValue)
    {
        if ($defaultValue === Abstracter::UNDEFINED) {
            return '';
        }
        $parsed = $this->html->parseTag($this->valDumper->dump($defaultValue));
        $parsed['attribs']['class'][] = 't_parameter-default';
        return ' <span class="t_operator">=</span> '
            . $this->html->buildTag(
                'span',
                $parsed['attribs'],
                $parsed['innerhtml']
            );
    }

    /**
     * Dump method's return type
     *
     * @param array $info Method info
     *
     * @return string
     */
    protected function dumpReturnType($info)
    {
        if ($info['return']['type'] === null) {
            return '';
        }
        return '<span class="t_punct t_colon">:</span> '
            . $this->helper->markupType($info['return']['type'], array(
                'title' => $this->opts['phpDocOutput']
                    ? $info['return']['desc']
                    : '',
            ));
    }

    /**
     * Get attributes for method markup
     *
     * @param array $info method info
     *
     * @return array
     */
    private function methodAttribs($info)
    {
        return array(
            'class' => array(
                $info['visibility'] => true,
                'inherited' => (bool) $info['inheritedFrom'],
                'isDeprecated' => $info['isDeprecated'],
                'isFinal' => $info['isFinal'],
                'isStatic' => $info['isStatic'],
                'method' => true,
            ),
            'data-attributes' => $this->opts['methodAttributeOutput']
                ? ($info['attributes'] ?: null)
                : null,
            'data-deprecated-desc' => isset($info['phpDoc']['deprecated'])
                ? $info['phpDoc']['deprecated'][0]['desc']
                : null,
            'data-implements' => $info['implements'],
            'data-inherited-from' => $info['inheritedFrom'],
        );
    }
}
