<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Dump\Html\Object;

use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;

/**
 * Dump object constants and Enum cases as HTML
 */
class Cases extends AbstractSection
{
    /**
     * Dump enum cases
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(ObjectAbstraction $abs)
    {
        if (\strpos(\json_encode($abs['implements']), '"UnitEnum"') === false) {
            return '';
        }
        $cfg = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::CASE_ATTRIBUTE_OUTPUT,
            'collect' => $abs['cfgFlags'] & AbstractObject::CASE_COLLECT,
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
        return '<dt class="cases">cases</dt>' . "\n"
            . $this->dumpItems($abs, 'cases', $cfg);
    }

    /**
     * {@inheritDoc}
     */
    protected function getClasses(array $info)
    {
        return ['case'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getModifiers(array $info)
    {
        return [];
    }
}
