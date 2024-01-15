<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\BaseValue;

/**
 * Dump val as plain text
 */
class TextValue extends BaseValue
{
    protected $valDepth = 0;

    /**
     * Used to reset valDepth
     *
     * @param string $depth value depth (used for indentation)
     *
     * @return void
     */
    public function setValDepth($depth = 0)
    {
        $this->valDepth = $depth;
    }

    /**
     * Dump array as text
     *
     * @param array $array Array to display
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    protected function dumpArray($array)
    {
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $array = parent::dumpArray($array);
        $str = \trim(\print_r($array, true));
        $str = \preg_replace('#^Array\n\(#', 'array(', $str);
        $str = \preg_replace('#^array\s*\(\s+\)#', 'array()', $str); // single-lineify empty array
        if ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
     *
     * @return string
     */
    protected function dumpBool($val)
    {
        return $val ? 'true' : 'false';
    }

    /**
     * Dump float value
     *
     * @param float       $val float value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return float|string
     */
    protected function dumpFloat($val, Abstraction $abs = null)
    {
        if ($val === Type::TYPE_FLOAT_INF) {
            return 'INF';
        }
        if ($val === Type::TYPE_FLOAT_NAN) {
            return 'NaN';
        }
        $date = $this->checkTimestamp($val, $abs);
        return $date
            ? '📅 ' . $val . ' (' . $date . ')'
            : $val;
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return 'null';
    }

    /**
     * Dump object as text
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        if ($abs['isRecursion']) {
            return $abs['className'] . ' *RECURSION*';
        }
        if ($abs['isMaxDepth']) {
            return $abs['className'] . ' *MAX DEPTH*';
        }
        if ($abs['isExcluded']) {
            return $abs['className'] . ' NOT INSPECTED';
        }
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $str = $abs['className'] . "\n"
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
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html
     */
    protected function dumpObjectMethods(Abstraction $abs)
    {
        $methodCollect = $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT;
        $methodOutput = $abs['cfgFlags'] & AbstractObject::METHOD_OUTPUT;
        if (!$methodCollect || !$methodOutput) {
            return '';
        }
        $str = '';
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
        foreach ($counts as $vis => $count) {
            if ($count > 0) {
                $str .= '    ' . $vis . ': ' . $count . "\n";
            }
        }
        $header = $str
            ? 'Methods:'
            : 'Methods: none!';
        return '  ' . $header . "\n" . $str;
    }

    /**
     * Dump object properties as text
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObjectProperties(Abstraction $abs)
    {
        $str = '';
        $propHeader = '';
        if (isset($abs['methods']['__get'])) {
            $str .= '    ✨ This object has a __get() method' . "\n";
        }
        $properties = $abs->sort($abs['properties'], $abs['sort']);
        foreach ($properties as $name => $info) {
            $name = \str_replace('debug.', '', $name);
            $info['className'] = $abs['className'];
            $info['isInherited'] = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
            $str .= $this->dumpProp($name, $info);
        }
        $propHeader = $str
            ? 'Properties:'
            : 'Properties: none!';
        return '  ' . $propHeader . "\n" . $str;
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
        $name = $this->dumpKeys
            ? $this->dump($name, array(
                'addQuotes' => \preg_match('#[\s\r\n]#u', $name) === 1 || $name === '',
            ))
            : $name;
        return \sprintf(
            '    %s(%s) %s%s' . "\n",
            $this->dumpPropPrefix($info),
            $this->dumpPropVis($info),
            $name,
            $info['debugInfoExcluded']
                ? ''
                : ' = ' . $this->dump($info['value'])
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

    /**
     * Dump string
     *
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, Abstraction $abs = null)
    {
        $addQuotes = $this->getDumpOpt('addQuotes');
        $date = \is_numeric($val)
            ? $this->checkTimestamp($val, $abs)
            : null;
        $val = $this->debug->utf8->dump($val);
        if ($addQuotes) {
            $val = '"' . $val . '"';
        }
        if ($date) {
            return '📅 ' . $val . ' (' . $date . ')';
        }
        $diff = $abs && $abs['strlen']
            ? $abs['strlen'] - \strlen($abs['value'])
            : 0;
        if ($diff) {
            $val .= '[' . $diff . ' more bytes (not logged)]';
        }
        return $val;
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return 'undefined';
    }

    /**
     * Dump Abstraction::TYPE_UNKNOWN
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    protected function dumpUnknown(Abstraction $abs)
    {
        $values = parent::dumpUnknown($abs);
        return 'unknown: ' . \print_r($values['value'], true);
    }
}
