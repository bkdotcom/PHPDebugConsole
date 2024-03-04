<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;

/**
 * Dump object constants as HTML
 */
class ObjectConstants extends AbstractObjectSection
{
    /**
     * Dump object constants
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
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
        $visClasses = \array_diff((array) $info['visibility'], array('debug'));
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
