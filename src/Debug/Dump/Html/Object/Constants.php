<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b2
 */

namespace bdk\Debug\Dump\Html\Object;

use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;

/**
 * Dump object constants as HTML
 */
class Constants extends AbstractSection
{
    /**
     * Dump object constants
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(ObjectAbstraction $abs)
    {
        $cfg = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::CONST_ATTRIBUTE_OUTPUT,
            'collect' => $abs['cfgFlags'] & AbstractObject::CONST_COLLECT,
            'output' => $abs['cfgFlags'] & AbstractObject::CONST_OUTPUT,
        );
        if (!$cfg['output']) {
            return '';
        }
        if (!$cfg['collect']) {
            return '<dt class="constants">constants <i>not collected</i></dt>' . "\n";
        }
        if (!$abs['constants']) {
            return '';
        }
        $html = '<dt class="constants">constants</dt>' . "\n";
        $html .= $this->dumpItems($abs, 'constants', $cfg);
        return $html;
    }

    /**
     * {@inheritDoc}
     */
    protected function getClasses(array $info)
    {
        $visClasses = \array_diff((array) $info['visibility'], ['debug']);
        $classes = \array_keys(\array_filter(array(
            'constant' => true,
            'isFinal' => $info['isFinal'],
            'private-ancestor' => $info['isPrivateAncestor'],
        )));
        return \array_merge($classes, $visClasses);
    }

    /**
     * {@inheritDoc}
     */
    protected function getModifiers(array $info)
    {
        return \array_merge(\array_keys(\array_filter(array(
            'final' => $info['isFinal'],
        ))), (array) $info['visibility']);
    }
}
