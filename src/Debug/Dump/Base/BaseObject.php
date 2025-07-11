<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Dump\Base;

use bdk\Debug;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Dump\Base\Value as ValDumper;

/**
 * Output object
 */
class BaseObject
{
    /** @var ValDumper */
    public $valDumper;

    /** @var Debug */
    protected $debug;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Value dumper instance
     */
    public function __construct(ValDumper $valDumper)
    {
        $this->debug = $valDumper->debug;
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
        $str = $this->dumpSpecialCases($abs, $abs['className']);
        if ($str) {
            return $str;
        }
        return array(
            '___class_name' => $abs['className'],
        ) + (array) $this->dumpProperties($abs, array());
    }

    /**
     * Return array of object properties (name->value)
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     * @param array             $cfg Configuration options
     *
     * @return array|string
     */
    protected function dumpProperties(ObjectAbstraction $abs, array $cfg) // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
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
            $vis[] = $this->debug->i18n->trans('word.excluded');
        }
        return \implode(' ', $vis);
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
        if ($abs['isRecursion']) {
            return $className . ' *' . $this->debug->i18n->trans('abs.recursion') . '*';
        }
        if ($abs['isMaxDepth']) {
            return $className . ' *' . $this->debug->i18n->trans('abs.max-depth') . '*';
        }
        if ($abs['isExcluded']) {
            return $className . ' ' . $this->debug->i18n->trans('abs.not-inspected');
        }
        return '';
    }
}
