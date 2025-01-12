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

namespace bdk\Debug\Dump\Html\Object;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Dump object constants and Enum cases as HTML
 */
class ExtendsImplements
{
    /** @var Helper */
    protected $helper;

    /** @var HtmlUtil */
    protected $html;

    /** @var ValDumper */
    protected $valDumper;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Html dumper
     * @param Helper    $helper    Html dump helpers
     * @param HtmlUtil  $html      Html methods
     */
    public function __construct(ValDumper $valDumper, Helper $helper, HtmlUtil $html)
    {
        $this->valDumper = $valDumper;
        $this->helper = $helper;
        $this->html = $html;
    }

    /**
     * Dump classNames of classes/interface extended by class/interface
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dumpExtends(ObjectAbstraction $abs)
    {
        $extends = $this->getItems($abs, 'extends');
        return $this->dumpExtendsImplements($extends, 'extends', 'extends', 'extends');
    }

    /**
     * Dump classNames of interfaces obj implements
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dumpImplements(ObjectAbstraction $abs)
    {
        $interfaces = $this->getItems($abs, 'implements');
        return $this->dumpExtendsImplements($interfaces, 'implements', 'implements', 'interface', $abs['interfacesCollapse']);
    }

    /**
     * Build inheritance tree
     *
     * This method is used to display
     *  - interfaces implemented by a class
     *  - parent interfaces an interface extends (interface may extend multiple interfaces)
     *
     * @param array<array-key,string|array> $implements         Implements structure
     * @param string                        $cssClass           CSS class
     * @param list<string>                  $interfacesCollapse Interfaces that should initially be hidden
     *
     * @return string
     */
    private function buildTree(array $implements, $cssClass, array $interfacesCollapse = array())
    {
        $str = '<ul class="list-unstyled">' . "\n";
        foreach ($implements as $k => $v) {
            $className = \is_array($v)
                ? $k
                : $v;
            $str .= '<li>'
                . $this->valDumper->dump(new Abstraction(Type::TYPE_IDENTIFIER, array(
                    'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
                    'value' => $className,
                )), array(
                    'attribs' => array(
                        'class' => array(
                            $cssClass => true,
                            'toggle-off' => \in_array($className, $interfacesCollapse, true),
                        ),
                    ),
                ))
                . (\is_array($v) ? "\n" . self::buildTree($v, $cssClass, $interfacesCollapse) : '')
                . '</li>' . "\n";
        }
        $str .= '</ul>' . "\n";
        return $str;
    }

    /**
     * Dump classNames of classes/interface extended by class/interface
     *
     * Note: interfaces may extend multiple interfaces (multiple inheritance)
     *
     * @param array  $listOrTree         List or tree of classes
     * @param string $label              "heading"
     * @param string $treeClass          Tree wrapped in <dd> with this class
     * @param string $itemClass          Each item in tree or list has this class
     * @param array  $interfacesCollapse Interfaces that should initially be hidden
     *
     * @return string html fragment
     */
    private function dumpExtendsImplements(array $listOrTree, $label, $treeClass, $itemClass, array $interfacesCollapse = array())
    {
        if (empty($listOrTree)) {
            return '';
        }
        if ($this->valDumper->debug->arrayUtil->isList($listOrTree)) {
            return '<dt>' . $label . '</dt>' . "\n"
                . \implode(\array_map(function ($className) use ($itemClass, $interfacesCollapse) {
                    return $this->valDumper->dump(new Abstraction(Type::TYPE_IDENTIFIER, array(
                        'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
                        'value' => $className,
                    )), array(
                        'attribs' => array(
                            'class' => array(
                                $itemClass => true,
                                'toggle-off' => \in_array($className, $interfacesCollapse, true),
                            ),
                        ),
                        'tagName' => 'dd',
                    )) . "\n";
                }, $listOrTree));
        }
        return '<dt>' . $label . '</dt>' . "\n"
            . '<dd class="' . $treeClass . '">' . "\n"
            . $this->buildTree($listOrTree, $itemClass, $interfacesCollapse)
            . '</dd>' . "\n";
    }

    /**
     * Get the items to be dumped
     *
     * Extend me for custom filtering
     *
     * @param ObjectAbstraction $abs  Object abstraction
     * @param string            $what 'extends' or 'implements'
     *
     * @return array
     */
    protected function getItems(ObjectAbstraction $abs, $what)
    {
        return $abs[$what];
    }
}
