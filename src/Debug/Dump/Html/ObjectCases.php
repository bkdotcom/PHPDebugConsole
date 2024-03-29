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
 * Dump object constants and Enum cases as HTML
 */
class ObjectCases extends AbstractObjectSection
{
    /**
     * Dump enum cases
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        if (\strpos(\json_encode($abs['implements']), '"UnitEnum"') === false) {
            return '';
        }
        $cfg = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::CASE_ATTRIBUTE_OUTPUT,
            'collect' => $abs['cfgFlags'] & AbstractObject::CASE_COLLECT,
            'groupByInheritance' => false,
            'output' => $abs['cfgFlags'] & AbstractObject::CASE_OUTPUT,
        );
        if (!$cfg['output']) {
            return '';
        }
        if (!$cfg['collect']) {
            return '<dt class="cases">cases <i>not collected</i></dt>' . "\n";
        }
        if (!$abs['cases']) {
            return '<dt class="cases"><i>no cases!</i></dt>' . "\n";
        }
        $html = '<dt class="cases">cases</dt>' . "\n";
        $html .= $this->dumpItems($abs, 'cases', $cfg);
        return $html;
    }

    /**
     * {@inheritDoc}
     */
    protected function getClasses(array $info)
    {
        return array('case');
    }

    /**
     * {@inheritDoc}
     */
    protected function getModifiers(array $info)
    {
        return array();
    }
}
