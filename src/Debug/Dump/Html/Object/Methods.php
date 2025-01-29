<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Dump\Html\Object;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;

/**
 * Dump object methods as HTML
 */
class Methods extends AbstractSection
{
    /** @var array<string,int> */
    protected $opts = array();

    /**
     * Dump object methods as html
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(ObjectAbstraction $abs)
    {
        $this->opts = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::METHOD_ATTRIBUTE_OUTPUT,
            'collect' => $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT,
            'methodDescOutput' => $abs['cfgFlags'] & AbstractObject::METHOD_DESC_OUTPUT,
            'output' => $abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT,
            'paramAttributeOutput' => $abs['cfgFlags'] & AbstractObject::PARAM_ATTRIBUTE_OUTPUT,
            'phpDocOutput' => $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT,
            'staticVarOutput' => $abs['cfgFlags'] & AbstractObject::METHOD_STATIC_VAR_OUTPUT,
        );
        if (!$this->opts['output']) {
            // we're not outputting methods
            return '';
        }
        $html = '<dt class="methods">' . $this->getLabel($abs) . '</dt>' . "\n";
        if (!$this->opts['collect']) {
            return $html;
        }
        $magicMethods = \array_intersect(['__call', '__callStatic'], \array_keys($abs['methods']));
        $html .= $this->magicMethodInfo($magicMethods);
        $html .= $this->dumpItems($abs, 'methods', array());
        return \str_replace([
            ' data-deprecated-desc="null"',
            ' data-implements="null"',
            ' data-throws="null"',
            ' <span class="t_type"></span>',
        ], '', $html);
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpItemInner($name, array $info, array $cfg)
    {
        return $this->dumpModifiers($info) . ' '
            . $this->dumpName($name, $info)
            . $this->dumpParams($info)
            . $this->dumpReturnType($info)
            . $this->dumpStaticVars($info)
            . ($name === '__toString'
                ? "\n" . '<h3>return value</h3>' . "\n"
                    . '<ul class="list-unstyled"><li>'
                    . $this->valDumper->dump($info['returnValue'], array(
                        'attribs' => array(
                            'class' => ['return-value'],
                        ),
                    ))
                    . '</li></ul>'
                : '');
    }

    /**
     * Dump method name with phpdoc summary & desc
     *
     * @param Abstraction|string $name Method name
     * @param array              $info Method info
     *
     * @return string html fragment
     */
    protected function dumpName($name, array $info)
    {
        return $this->html->buildTag(
            'span',
            array(
                'class' => ['t_identifier'],
                'title' => $this->opts['phpDocOutput']
                    ? $this->helper->dumpPhpDoc(
                        $info['phpDoc']['summary']
                        . ($this->opts['methodDescOutput']
                            ? "\n\n" . $info['phpDoc']['desc']
                            : '')
                    )
                    : '',
            ),
            $this->valDumper->dump($name, array(
                'tagName' => null,
                'type' => Type::TYPE_STRING,
            ))
        );
    }

    /**
     * Dump method parameters as HTML
     *
     * @param array $info Method info
     *
     * @return string html fragment
     */
    protected function dumpParams(array $info)
    {
        $params = \array_map([$this, 'dumpParam'], $info['params']);
        return '<span class="t_punct">(</span>'
            . \implode('<span class="t_punct">,</span>' . "\n", $params)
            . '<span class="t_punct">)</span>';
    }

    /**
     * Dump a single parameter
     *
     * @param array $info Parameter info
     *
     * @return string html fragment
     */
    protected function dumpParam(array $info)
    {
        $attribs = array(
            'class' => array(
                'isPromoted' => $info['isPromoted'],
                'parameter' => true,
            ),
            'data-attributes' => $info['attributes'],
            'data-chars' => $this->valDumper->findChars(\json_encode($info['attributes'], JSON_UNESCAPED_UNICODE)),
        );
        $attribs =  \array_intersect_key($attribs, \array_filter(array(
            'class' => true,
            'data-attributes' => $this->opts['paramAttributeOutput'] && $info['attributes'],
            'data-chars' => $this->opts['paramAttributeOutput'],
        )));
        return $this->html->buildTag(
            'span',
            $attribs,
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
                    'class' => ['t_parameter-default'],
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
        $name = \sprintf(
            '%s%s$%s',
            $info['isPassedByReference'] ? '&' : '',
            $info['isVariadic'] ? '...' : '',
            $info['name']
        );
        return $this->html->buildTag(
            'span',
            array(
                'class' => 't_parameter-name',
                'title' => $this->opts['phpDocOutput']
                    ? $this->helper->dumpPhpDoc($info['desc'])
                    : '',
            ),
            $this->valDumper->dump($name, array(
                'tagName' => null,
            ))
        );
    }

    /**
     * Dump method's return type
     *
     * @param array $info Method info
     *
     * @return string
     */
    protected function dumpReturnType(array $info)
    {
        if ($info['return']['type'] === null) {
            return '';
        }
        return '<span class="t_punct t_colon">:</span> '
            . $this->helper->markupType($info['return']['type'], array(
                'title' => $this->opts['phpDocOutput']
                    ? $this->helper->dumpPhpDoc($info['return']['desc'])
                    : '',
            ));
    }

    /**
     * Dump method's return type
     *
     * @param array $info Method info
     *
     * @return string
     */
    protected function dumpStaticVars(array $info)
    {
        if (!$this->opts['staticVarOutput'] || empty($info['staticVars'])) {
            return '';
        }
        $html = "\n" . '<h3>static variables</h3>' . "\n";
        $html .= '<ul class="list-unstyled">' . "\n";
        foreach ($info['staticVars'] as $name => $value) {
            $html .= '<li>'
                . $this->valDumper->dump($name, array(
                    'addQuotes' => false,
                    'attribs' => array(
                        'class' => ['t_identifier'],
                    ),
                ))
                . '<span class="t_operator">=</span> ' . $this->valDumper->dump($value)
                . '</li>' . "\n";
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * {@inheritDoc}
     */
    protected function getAttribs(array $info, array $cfg)
    {
        return \array_merge(parent::getAttribs($info, $this->opts), array(
            'data-deprecated-desc' => isset($info['phpDoc']['deprecated'])
                ? $this->helper->dumpPhpDoc($info['phpDoc']['deprecated'][0]['desc'])
                : null,
            'data-implements' => $info['implements'],
            'data-throws' => $this->opts['phpDocOutput'] && isset($info['phpDoc']['throws'])
                ? \array_map(function ($info) {
                    $info['desc'] = $this->helper->dumpPhpDoc($info['desc']);
                    $info['type'] = $this->helper->dumpPhpDoc($info['type']);
                    return $info;
                }, $info['phpDoc']['throws'])
                : null,
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function getClasses(array $info)
    {
        $visClasses = \array_diff((array) $info['visibility'], ['debug']);
        $classes = \array_keys(\array_filter(array(
            'isAbstract' => $info['isAbstract'],
            'isDeprecated' => $info['isDeprecated'],
            'isFinal' => $info['isFinal'],
            'isStatic' => $info['isStatic'],
            'method' => true,
        )));
        return \array_merge($classes, $visClasses);
    }

    /**
     * Returns <dt class="methods">methods</dt>
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function getLabel(ObjectAbstraction $abs)
    {
        if (!($abs['cfgFlags'] & AbstractObject::METHOD_COLLECT)) {
            return 'methods <i>not collected</i>';
        }
        return \count($abs['methods']) > 0
            ? 'methods'
            : 'no methods';
    }

    /**
     * {@inheritDoc}
     */
    protected function getModifiers(array $info)
    {
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        return \array_keys(\array_filter(array(
            'abstract' => $info['isAbstract'],
            'final' => $info['isFinal'],
            \implode(' ', (array) $info['visibility']) => true,
            'static' => $info['isStatic'],
        )));
    }
}
