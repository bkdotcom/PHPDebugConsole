<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Dump\TextAnsi;

use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Dump\Text\TextObject;
use bdk\Debug\Dump\TextAnsi\Value as ValDumper;

/**
 * Output object as Text
 */
class TextAnsiObject extends TextObject
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
                ? $this->valDumper->getCfg('escapeCodes.punct') . '()' . $this->valDumper->escapeReset
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
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeReset = $this->valDumper->escapeReset;
        foreach ($abs['methods'] as $info) {
            $counts[ $info['visibility'] ]++;
        }
        $counts = \array_filter($counts);
        $counts = \array_map(static function ($vis, $count) use ($escapeCodes, $escapeReset) {
            return '    ' . $vis . $escapeCodes['punct'] . ': '
                . $escapeCodes['numeric'] . $count . $escapeReset . "\n";
        }, \array_keys($counts), $counts);
        $header = $counts
            ? "\e[4m" . $this->debug->i18n->trans('object.methods') . ":\e[24m"
            : $this->debug->i18n->trans('object.methods.none');
        return '  ' . $header . "\n" . \implode('', $counts);
    }

    /**
     * Dump object properties as text with ANSI escape codes
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
            ? "\e[4m" . $this->debug->i18n->trans('object.properties')  . ':' . "\e[24m" . "\n"
            : $this->debug->i18n->trans('object.properties.none') . "\n";
        $magicMethods = \array_intersect(['__get', '__set'], \array_keys($abs['methods']));
        $subHeader = $this->magicMethodInfo($magicMethods);
        return '  ' . $header . $subHeader . $this->dumpPropertiesBody($abs, $cfg);
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
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeReset = $this->valDumper->escapeReset;
        $operator = $cfg['asArray']
            ? '=>'
            : '=';
        return \sprintf(
            '    %s%s %s%s%s',
            $this->dumpPropPrefix($info),
            $cfg['asArray']
                ? ''
                : $escapeCodes['muted'] . '(' . $this->dumpPropVis($info) . ')' . $escapeReset,
            $this->dumpPropName($name, $cfg),
            $info['debugInfoExcluded']
                ? ''
                : ' ' . $escapeCodes['operator'] . $operator . $escapeReset . ' ',
            $info['debugInfoExcluded']
                ? ''
                : $this->valDumper->dump($info['value'])
        ) . "\n";
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
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeReset = $this->valDumper->escapeReset;
        $escapeColor = \is_int($name)
            ? $escapeCodes['numeric']
            : $escapeCodes['property'];
        if ($cfg['asArray']) {
            return $escapeCodes['punct'] . '['
                . $escapeColor . $this->valDumper->dump($name, array(
                    'addQuotes' => false,
                ))
                . $escapeCodes['punct'] . ']' . $escapeReset;
        }
        $this->valDumper->escapeReset = \str_replace('m', ';49m', $escapeCodes['property']); // 49 = default background color
        $name = $this->valDumper->dump($name, array(
            'addQuotes' => \preg_match('#[\s\r\n]#u', $name) === 1 || $name === '',
        ));
        $this->valDumper->escapeReset = $escapeReset;
        return $escapeColor . $name . $escapeReset;
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
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeCodesMethods = $this->valDumper->getCfg('escapeCodesMethods');
        $escapeReset = $this->valDumper->escapeReset;
        return \strtr(parent::dumpPropPrefix($info), array(
            '↓' => $escapeCodesMethods['warn'] . '↓' . $escapeReset,
            '↳' => $escapeCodes['muted'] . '↳' . $escapeReset,
            '⚠' => $escapeCodesMethods['warn'] . '⚠' . $escapeReset,
            '⟳' => $escapeCodes['muted'] . '⟳' . $escapeReset,
        ));
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
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeReset = $this->valDumper->escapeReset;
        if ($abs['isRecursion']) {
            return $className . ' ' . $escapeCodes['recursion'] . '*' . $this->debug->i18n->trans('abs.recursion') . '*' . $escapeReset;
        }
        if ($abs['isMaxDepth']) {
            return $className . ' ' . $escapeCodes['recursion'] . '*' . $this->debug->i18n->trans('abs.max-depth') . '*' . $escapeReset;
        }
        if ($abs['isExcluded']) {
            return $className . ' ' . $escapeCodes['excluded'] . $this->debug->i18n->trans('abs.not-inspected') . $escapeReset;
        }
        return '';
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
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeReset = $this->valDumper->escapeReset;
        return '  ' . $escapeCodes['muted'] . '✨ ' . (\count($methods) === 1
                ? $this->debug->i18n->trans('object.methods.magic.1', array('method' => $methods[0]))
                : $this->debug->i18n->trans('object.methods.magic.2', array('method1' => $methods[0], 'method2' => $methods[1]))
            ) . $escapeReset . "\n";
    }
}
