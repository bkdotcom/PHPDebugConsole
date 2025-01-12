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

namespace bdk\Debug\Dump\Base;

use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Dump\Base\Value as ValDumper;

/**
 * Output object
 */
class BaseObject
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
     * Dump object
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(ObjectAbstraction $abs)
    {
        if ($abs['isRecursion']) {
            return '(object) ' . $abs['className'] . ' *RECURSION*';
        }
        if ($abs['isMaxDepth']) {
            return '(object) ' . $abs['className'] . ' *MAX DEPTH*';
        }
        if ($abs['isExcluded']) {
            return '(object) ' . $abs['className'] . ' NOT INSPECTED';
        }
        return array(
            '___class_name' => $abs['className'],
        ) + (array) $this->dumpObjectProperties($abs);
    }

    /**
     * Return array of object properties (name->value)
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return array|string
     */
    protected function dumpObjectProperties(ObjectAbstraction $abs)
    {
        $return = array();
        $properties = $abs->sort($abs['properties'], $abs['sort']);
        foreach ($properties as $name => $info) {
            $name = \str_replace('debug.', '', $name);
            $name = $this->valDumper->dump($name, array('addQuotes' => false));
            $info['isInherited'] = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
            $vis = $this->dumpPropVis($info);
            $name = '(' . $vis . ') ' . $name;
            $return[$name] = $this->valDumper->dump($info['value']);
        }
        return $return;
    }

    /**
     * Dump property visibility
     *
     * @param array $info Property info
     *
     * @return string visibility
     */
    protected function dumpPropVis(array $info)
    {
        $vis = (array) $info['visibility'];
        foreach ($vis as $i => $v) {
            if (\in_array($v, ['magic', 'magic-read', 'magic-write'], true)) {
                $vis[$i] = '✨ ' . $v;    // "sparkles": there is no magic-wand unicode char
            } elseif ($v === 'private' && $info['isInherited']) {
                $vis[$i] = '🔒 ' . $v;
            }
        }
        if ($info['debugInfoExcluded']) {
            $vis[] = 'excluded';
        }
        return \implode(' ', $vis);
    }
}
