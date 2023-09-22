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

/**
 * Dump object methods as HTML
 */
class ObjectMethods extends AbstractObjectSection
{
    protected $opts = array();

    /**
     * Dump object methods as html
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        $this->opts = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::METHOD_ATTRIBUTE_OUTPUT,
            'collect' => $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT,
            'methodDescOutput' => $abs['cfgFlags'] & AbstractObject::METHOD_DESC_OUTPUT,
            'output' => $abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT,
            'paramAttributeOutput' => $abs['cfgFlags'] & AbstractObject::PARAM_ATTRIBUTE_OUTPUT,
            'phpDocOutput' => $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT,
        );
        if (!$this->opts['output']) {
            // we're not outputting methods
            return '';
        }
        $html = '<dt class="methods">' . $this->getLabel($abs) . '</dt>' . "\n";
        if (!$this->opts['collect']) {
            return $html;
        }
        $magicMethods = \array_intersect(array('__call', '__callStatic'), \array_keys($abs['methods']));
        $html .= $this->magicMethodInfo($magicMethods);
        $html .= $this->dumpItems($abs, 'methods', array());
        return \str_replace(array(
            ' data-deprecated-desc="null"',
            ' data-implements="null"',
            ' <span class="t_type"></span>',
        ), '', $html);
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpItemInner($name, array $info, array $cfg)
    {
        return $this->dumpModifiers($info) . ' '
            . $this->dumpName($name, $info)
            . $this->dumpParams($info['params'])
            . $this->dumpReturnType($info)
            . ($name === '__toString'
                ? '<br />' . "\n" . $this->valDumper->dump($info['returnValue'])
                : '');
    }

    /**
     * Dump method name with phpdoc summary & desc
     *
     * @param string $name method name
     * @param array  $info method info
     *
     * @return string html fragment
     */
    protected function dumpName($name, $info)
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
            $name
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
        return $this->html->buildTag(
            'span',
            array(
                'class' => array(
                    'isPromoted' => $info['isPromoted'],
                    'parameter' => true,
                ),
                'data-attributes' => $this->opts['paramAttributeOutput']
                    ? ($info['attributes'] ?: null)
                    : null,
            ),
            (!empty($info['type'])
                ? $this->helper->markupType($info['type']) . ' '
                : '')
                . $this->dumpParamName($info)
                . $this->dumpParamDefault($info['defaultValue'])
        );
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
        return ' <span class="t_operator">=</span> '
            . $this->valDumper->dump($defaultValue, array(
                'attribs' => array(
                    'class' => array('t_parameter-default'),
                ),
                'tagName' => 'span',
            ));
    }

    /**
     * Dump method parameter name
     *
     * @param array $info Param info
     *
     * @return string html snippet
     */
    protected function dumpParamName(array $info)
    {
        return $this->html->buildTag(
            'span',
            array(
                'class' => 't_parameter-name',
                'title' => $this->opts['phpDocOutput']
                    ? $info['desc']
                    : '',
            ),
            \htmlspecialchars($info['name'])
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
     * {@inheritDoc}
     */
    protected function getAttribs(array $info, array $cfg = array())
    {
        return \array_merge(parent::getAttribs($info, $this->opts), array(
            'data-deprecated-desc' => isset($info['phpDoc']['deprecated'])
                ? $info['phpDoc']['deprecated'][0]['desc']
                : null,
            'data-implements' => $info['implements'],
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function getClasses(array $info)
    {
        $visClasses = \array_diff((array) $info['visibility'], array('debug'));
        $classes = \array_keys(\array_filter(array(
            'inherited' => $info['isInherited'],
            'isDeprecated' => $info['isDeprecated'],
            'isFinal' => $info['isFinal'],
            'isStatic' => $info['isStatic'],
            'method' => true,
            'overrides' => $info['isInherited'] === false && $info['declaredPrev'],
        )));
        return \array_merge($classes, $visClasses);
    }

    /**
     * Returns <dt class="methods">methods</dt>
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function getLabel(Abstraction $abs)
    {
        $label = \count($abs['methods']) > 0
            ? 'methods'
            : 'no methods';
        if (!($abs['cfgFlags'] & AbstractObject::METHOD_COLLECT)) {
            $label = 'methods <i>not collected</i>';
        }
        return $label;
    }

    /**
     * {@inheritDoc}
     */
    protected function getModifiers(array $info)
    {
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        return \array_keys(\array_filter(array(
            'final' => $info['isFinal'],
            $info['visibility'] => true,
            'static' => $info['isStatic'],
        )));
    }
}
