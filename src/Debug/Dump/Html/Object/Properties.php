<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Dump\Html\Object;

use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;

/**
 * Dump object properties as HTML
 */
class Properties extends AbstractSection
{
    /**
     * Dump object properties as HTML
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(ObjectAbstraction $abs)
    {
        $cfg = array(
            'attributeOutput' => $abs['cfgFlags'] & AbstractObject::PROP_ATTRIBUTE_OUTPUT,
        );
        if ($abs['isInterface']) {
            return '';
        }
        $magicMethods = \array_intersect(['__get', '__set'], \array_keys($abs['methods']));
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
        $visClasses = \array_diff((array) $info['visibility'], ['debug']);
        $classes = \array_keys(\array_filter(array(
            'debug-value' => $info['valueFrom'] === 'debug',
            'debuginfo-excluded' => $info['debugInfoExcluded'],
            'debuginfo-value' => $info['valueFrom'] === 'debugInfo',
            'forceShow' => $info['forceShow'],
            'getHook' => \in_array('get', $info['hooks'], true),
            'isDeprecated' => $info['isDeprecated'],
            'isDynamic' => $info['declaredLast'] === null
                && $info['valueFrom'] === 'value'
                && $info['objClassName'] !== 'stdClass',
            'isEager' => !empty($info['isEager']),
            'isFinal' => $info['isFinal'],
            'isPromoted' => $info['isPromoted'],
            'isReadOnly' => $info['isReadOnly'],
            'isStatic' => $info['isStatic'],
            'isVirtual' => $info['isVirtual'],
            'isWriteOnly' => $info['isVirtual'] && \in_array('get', $info['hooks'], true) === false,
            'private-ancestor' => $info['isPrivateAncestor'],
            'property' => true,
            'setHook' => \in_array('set', $info['hooks'], true),
        )));
        return \array_merge($classes, $visClasses);
    }

    /**
     * get property "header"
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function getLabel(ObjectAbstraction $abs)
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
        $info = \array_merge(array(
            'isEager' => null, // only collected on isLazy objects
        ), $info);
        $modifiers = \array_merge(
            array(
                'eager' => $info['isEager'],
                'final' => $info['isFinal'],
            ),
            \array_fill_keys((array) $info['visibility'], true),
            array(
                'readonly' => $info['isReadOnly'],
                'static' => $info['isStatic'],
            )
        );
        return \array_keys(\array_filter($modifiers));
    }
}
