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
 * Dump object properties as HTML
 */
class ObjectProperties extends AbstractObjectSection
{
    /**
     * Dump object properties as HTML
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        $cfg = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::PROP_ATTRIBUTE_OUTPUT,
        );
        if ($abs['isInterface']) {
            return '';
        }
        $magicMethods = \array_intersect(array('__get', '__set'), \array_keys($abs['methods']));
        $html = '<dt class="properties">' . $this->getLabel($abs) . '</dt>' . "\n";
        $html .= $this->magicMethodInfo($magicMethods);
        $html .= $this->dumpItems($abs, 'properties', $cfg);
        return $html;
    }

    /**
     * {@inheritDoc}
     */
    protected function getClasses(array $info)
    {
        $visClasses = \array_diff((array) $info['visibility'], array('debug'));
        $classes = \array_keys(\array_filter(array(
            'debug-value' => $info['valueFrom'] === 'debug',
            'debuginfo-excluded' => $info['debugInfoExcluded'],
            'debuginfo-value' => $info['valueFrom'] === 'debugInfo',
            'forceShow' => $info['forceShow'],
            'isDynamic' => $info['declaredLast'] === null
                && $info['valueFrom'] === 'value'
                && $info['objClassName'] !== 'stdClass',
            'isPromoted' => $info['isPromoted'],
            'isReadOnly' => $info['isReadOnly'],
            'isStatic' => $info['isStatic'],
            'private-ancestor' => $info['isPrivateAncestor'],
            'property' => true,
        )));
        return \array_merge($classes, $visClasses);
    }

    /**
     * get property "header"
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function getLabel(Abstraction $abs)
    {
        if (\count($abs['properties']) === 0) {
            return 'no properties';
        }
        $label = 'properties';
        if ($abs['viaDebugInfo']) {
            $label .= ' <span class="text-muted">(via __debugInfo)</span>';
        }
        return $label;
    }

    /**
     * {@inheritDoc}
     */
    protected function getModifiers(array $info)
    {
        $modifiers = (array) $info['visibility'];
        return \array_merge($modifiers, \array_keys(\array_filter(array(
            'readonly' => $info['isReadOnly'],
            'static' => $info['isStatic'],
        ))));
    }
}
