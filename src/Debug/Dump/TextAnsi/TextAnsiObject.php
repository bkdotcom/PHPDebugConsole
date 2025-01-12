<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
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
     * Constructor
     *
     * @param ValDumper $valDumper Dump\Html instance
     */
    public function __construct(ValDumper $valDumper)
    {
        $this->valDumper = $valDumper;
    }

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
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeReset = $this->valDumper->escapeReset;
        if ($abs['isRecursion']) {
            return $className . ' ' . $escapeCodes['recursion'] . '*RECURSION*' . $escapeReset;
        }
        if ($abs['isMaxDepth']) {
            return $className . ' ' . $escapeCodes['recursion'] . '*MAX DEPTH*' . $escapeReset;
        }
        if ($abs['isExcluded']) {
            return $className . ' ' . $escapeCodes['excluded'] . 'NOT INSPECTED' . $escapeReset;
        }
        $isNested = $this->valDumper->valDepth > 0;
        $this->valDumper->incValDepth();
        $str = $className . "\n"
            . $this->dumpObjectProperties($abs)
            . $this->dumpObjectMethods($abs);
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
     *
     * @return string html
     */
    protected function dumpObjectMethods(ObjectAbstraction $abs)
    {
        $methodCollect = $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT;
        $methodOutput = $abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT;
        if (!$methodCollect || !$methodOutput) {
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
            ? "\e[4mMethods:\e[24m"
            : 'Methods: none!';
        return '  ' . $header . "\n" . \implode('', $counts);
    }

    /**
     * Dump object properties as text with ANSI escape codes
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObjectProperties(ObjectAbstraction $abs)
    {
        $header = \count($abs['properties']) > 0
            ? "\e[4m" . 'Properties:' . "\e[24m"
            : 'Properties: none!';
        $subHeader = '';
        if (isset($abs['methods']['__get'])) {
            $escapeCodes = $this->valDumper->getCfg('escapeCodes');
            $escapeReset = $this->valDumper->escapeReset;
            $subHeader = '    ' . $escapeCodes['muted']
                . '✨ This object has a __get() method'
                . $escapeReset . "\n";
        }
        return '  ' . $header . "\n" . $subHeader . $this->dumpObjectPropertiesBody($abs);
    }

    /**
     * Dump object property
     *
     * @param string $name Property name
     * @param array  $info Property info
     *
     * @return string
     */
    protected function dumpProp($name, array $info)
    {
        $escapeCodes = $this->valDumper->getCfg('escapeCodes');
        $escapeReset = $this->valDumper->escapeReset;
        $this->valDumper->escapeReset = \str_replace('m', ';49m', $escapeCodes['property']);
        $name = $this->valDumper->dump($name, array(
            'addQuotes' => \preg_match('#[\s\r\n]#u', $name) === 1 || $name === '',
        ));
        $this->valDumper->escapeReset = $escapeReset;
        return \sprintf(
            '    %s%s %s%s',
            $this->dumpPropPrefix($info),
            $escapeCodes['muted'] . '(' . $this->dumpPropVis($info) . ')' . $escapeReset,
            $escapeCodes['property'] . $name . $escapeReset,
            $info['debugInfoExcluded']
                ? ''
                : \sprintf(
                    ' %s=%s %s',
                    $escapeCodes['operator'],
                    $escapeReset,
                    $this->valDumper->dump($info['value'])
                )
        ) . "\n";
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
            '↳' => $escapeCodes['muted'] . '↳' . $escapeReset,
            '⚠' => $escapeCodesMethods['warn'] . '⚠' . $escapeReset,
            '⟳' => $escapeCodes['muted'] . '⟳' . $escapeReset,
        ));
    }
}
