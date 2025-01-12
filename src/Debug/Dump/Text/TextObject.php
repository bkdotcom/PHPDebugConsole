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
    /** @var int */

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
        if ($abs['isRecursion']) {
            return $className . ' *RECURSION*';
        }
        if ($abs['isMaxDepth']) {
            return $className . ' *MAX DEPTH*';
        }
        if ($abs['isExcluded']) {
            return $className . ' NOT INSPECTED';
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
        foreach ($abs['methods'] as $info) {
            $counts[ $info['visibility'] ]++;
        }
        $counts = \array_filter($counts);
        $counts = \array_map(static function ($vis, $count) {
            return '    ' . $vis . ': ' . $count . "\n";
        }, \array_keys($counts), $counts);
        $header = $counts
            ? 'Methods:'
            : 'Methods: none!';
        return '  ' . $header . "\n" . \implode('', $counts);
    }

    /**
     * Dump object properties as text
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObjectProperties(ObjectAbstraction $abs)
    {
        $header = \count($abs['properties']) > 0
            ? 'Properties:'
            : 'Properties: none!';
        $subHeader = '';
        if (isset($abs['methods']['__get'])) {
            $subHeader = '    ✨ This object has a __get() method' . "\n";
        }
        return '  ' . $header . "\n" . $subHeader . $this->dumpObjectPropertiesBody($abs);
    }

    /**
     * Dump object properties as text
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObjectPropertiesBody(ObjectAbstraction $abs)
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
            $str .= $this->dumpProp($name, $info);
        }
        return $str;
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
        $name = $this->valDumper->dump($name, array(
            'addQuotes' => \preg_match('#[\s\r\n]#u', $name) === 1 || $name === '',
        ));
        return \sprintf(
            '    %s(%s) %s%s' . "\n",
            $this->dumpPropPrefix($info),
            $this->dumpPropVis($info),
            $name,
            $info['debugInfoExcluded']
                ? ''
                : ' = ' . $this->valDumper->dump($info['value'])
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
}
