<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump\Text;

use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Dump\Base\BaseObject;
use bdk\Debug\Dump\Text\Value as ValDumper;

/**
 * Output object as Text
 */
class TextObject extends BaseObject
{
    /** @var ValDumper */
    public $valDumper;

    /**
     * Dump object as text
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string
     */
    public function dump(ObjectAbstraction $abs)
    {
        $className = $this->valDumper->markupIdentifier($abs['className'], 'className');
        $str = $this->dumpSpecialCases($abs, $className);
        if ($str) {
            return $str;
        }
        $cfg = array(
            'asArray' => $abs['className'] === 'stdClass'
                && ($abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT) === 0
                && ($abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_OUTPUT) === 0,
        );
        $isNested = $this->valDumper->valDepth > 0;
        $this->valDumper->incValDepth();
        $str = $className
            . ($cfg['asArray'] && \count($abs['properties']) === 0
                ? '()'
                : '') . "\n"
            . $this->dumpProperties($abs, $cfg)
            . $this->dumpMethods($abs, $cfg);
        $str = \trim($str);
        if ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump object methods as text
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     * @param array             $cfg Configuration options
     *
     * @return string html
     */
    protected function dumpMethods(ObjectAbstraction $abs, array $cfg)
    {
        $methodCollect = $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT;
        $methodOutput = $abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT;
        if (!$methodCollect || !$methodOutput || $cfg['asArray']) {
            return '';
        }
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $counts = array(
            'public' => 0,
            'protected' => 0,
            'private' => 0,
            'magic' => 0,
        );
        foreach ($abs['methods'] as $info) {
            $counts[ $info['visibility'] ]++;
        }
        $counts = \array_filter($counts);
        $counts = \array_map(static function ($vis, $count) {
            return '    ' . $vis . ': ' . $count . "\n";
        }, \array_keys($counts), $counts);
        $header = $counts
            ? $this->debug->i18n->trans('object.methods') . ':'
            : $this->debug->i18n->trans('object.methods.none');
        return '  ' . $header . "\n" . \implode('', $counts);
    }

    /**
     * Dump object properties as text
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     * @param array             $cfg Configuration options
     *
     * @return string
     */
    protected function dumpProperties(ObjectAbstraction $abs, array $cfg)
    {
        if ($cfg['asArray']) {
            return $this->dumpPropertiesBody($abs, $cfg);
        }
        $header = \count($abs['properties']) > 0
            ? $this->debug->i18n->trans('object.properties') . ":\n"
            : $this->debug->i18n->trans('object.properties.none') . "\n";
        $magicMethods = \array_intersect(['__get', '__set'], \array_keys($abs['methods']));
        $subHeader = $this->magicMethodInfo($magicMethods);
        return '  ' . $header . $subHeader . $this->dumpPropertiesBody($abs, $cfg);
    }

    /**
     * Dump object properties as text
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     * @param array             $cfg Configuration options
     *
     * @return string
     */
    protected function dumpPropertiesBody(ObjectAbstraction $abs, array $cfg)
    {
        $str = '';
        $properties = $abs->sort($abs['properties'], $abs['sort']);
        $absKeys = isset($abs['keys'])
            ? $abs['keys']
            : array();
        foreach ($properties as $name => $info) {
            $name = \preg_replace('/^debug\./', '', $name);
            $name = isset($absKeys[$name])
                ? $absKeys[$name]
                : $name;
            $info['className'] = $abs['className'];
            $info['isInherited'] = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
            $str .= $this->dumpProp($name, $info, $cfg);
        }
        return $str;
    }

    /**
     * Dump object property
     *
     * @param string $name Property name
     * @param array  $info Property info
     * @param array  $cfg  Configuration options
     *
     * @return string
     */
    protected function dumpProp($name, array $info, array $cfg)
    {
        $operator = $cfg['asArray']
            ? '=>'
            : '=';
        return \sprintf(
            '    %s%s %s%s%s' . "\n",
            $this->dumpPropPrefix($info),
            $cfg['asArray']
                ? ''
                : '(' . $this->dumpPropVis($info) . ')',
            $this->dumpPropName($name, $cfg),
            $info['debugInfoExcluded']
                ? ''
                : ' ' . $operator . ' ',
            $info['debugInfoExcluded']
                ? ''
                : $this->valDumper->dump($info['value'])
        );
    }

    /**
     * Dump property name
     *
     * @param int|string $name Property name
     * @param array      $cfg  Configuration options
     *
     * @return string
     */
    private function dumpPropName($name, array $cfg)
    {
        if ($cfg['asArray']) {
            return '[' . $this->valDumper->dump($name, array(
                'addQuotes' => false,
            )) . ']';
        }
        return $this->valDumper->dump($name, array(
            'addQuotes' => \preg_match('#[\s\r\n]#u', $name) === 1 || $name === '',
        ));
    }

    /**
     * Get inherited/dynamic/override indicator
     *
     * @param array $info Property info
     *
     * @return string
     */
    protected function dumpPropPrefix(array $info)
    {
        $info = \array_filter(array(
            'inherited' => $info['isInherited'],
            'isDynamic' => $info['declaredLast'] === null
                && $info['valueFrom'] === 'value'
                && $info['className'] !== 'stdClass',
            'overrides' => $info['isInherited'] === false && $info['declaredPrev'],
        ));
        $prefixes = \array_intersect_key(array(
            'inherited' => '↳',
            'isDynamic' => '⚠',
            'overrides' => '⟳',
        ), $info);
        return $prefixes
            ? \implode(' ', $prefixes) . ' '
            : '';
    }

    /**
     * Generate some info regarding the given method names
     *
     * @param array $methods method names
     *
     * @return string html fragment
     */
    protected function magicMethodInfo($methods)
    {
        if (!$methods) {
            return '';
        }
        $methods = \array_values($methods);
        return '  ✨ ' . (\count($methods) === 1
                ? $this->debug->i18n->trans('object.methods.magic.1', array('method' => $methods[0]))
                : $this->debug->i18n->trans('object.methods.magic.2', array('method1' => $methods[0], 'method2' => $methods[1]))
            ) . "\n";
    }
}
