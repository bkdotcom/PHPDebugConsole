<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Dump\Html\Object;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Base class for dumping object constants/enum-cases/properties/methods
 */
abstract class AbstractSection
{
    /** @var Debug */
    protected $debug;

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
        $this->debug = $valDumper->debug;
        $this->valDumper = $valDumper;
        $this->helper = $helper;
        $this->html = $html;
    }

    /**
     * Dump object cases, constants, properties, or methods
     *
     * Likely calls self::dumpItems()
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    abstract public function dump(ObjectAbstraction $abs);

    /**
     * Iterate over cases, constants, properties, or methods
     *
     * @param ObjectAbstraction $abs  Object abstraction
     * @param string            $what 'cases', 'constants', 'properties', or 'methods'
     * @param array             $cfg  config options
     *
     * @return string
     */
    public function dumpItems(ObjectAbstraction $abs, $what, array $cfg)
    {
        $cfg = \array_merge(array(
            'asArray' => false, // used by Properties
            'groupByInheritance' => \strpos($abs['sort'], 'inheritance') === 0,
            'objClassName' => $abs['className'],
            'phpDocOutput' => $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT,
            'what' => $what,
        ), $cfg);
        return $cfg['groupByInheritance']
            ? $this->dumpItemsByInheritance($abs, $cfg)
            : $this->dumpItemsFiltered($abs, \array_keys($this->getItems($abs, $what)), $cfg);
    }

    /**
     * Build "inherited from" classname heading/divider
     *
     * @param string $className Class name
     *
     * @return string
     */
    protected function buildInheritedFromHeading($className)
    {
        return '<dd class="heading">Inherited from '
            . $this->valDumper->markupIdentifier($className, 'className')
            . '</dd>' . "\n";
    }

    /**
     * Dump Property or Constant info as HTML
     *
     * @param string|Abstraction $name Property/constant name
     * @param array              $info Property/constant info
     * @param array              $cfg  config options
     *
     * @return string html fragment
     */
    protected function dumpItem($name, array $info, array $cfg)
    {
        return $this->html->buildTag(
            'dd',
            $this->getAttribs($info, $cfg),
            $this->dumpItemInner($name, $info, $cfg)
        );
    }

    /**
     * Build property/constant/case inner html
     *
     * @param string|Abstraction $name Property name
     * @param array              $info Property info
     * @param array              $cfg  options
     *
     * @return string html fragment
     */
    protected function dumpItemInner($name, array $info, array $cfg)
    {
        $operator = $cfg['asArray']
            ? '=&gt;'
            : '=';
        $parts = \array_filter(array(
            '1_modifiers' => $this->dumpModifiers($info, $cfg),
            '2_type' => isset($info['type'])
                ? $this->helper->markupType($info['type'])
                : '',
            '3_name' => $this->dumpName($name, $info, $cfg),
            '4_operator' => $info['value'] !== Abstracter::UNDEFINED
                ? '<span class="t_operator">' . $operator . '</span>'
                : '',
            '5_value' => $info['value'] !== Abstracter::UNDEFINED
                ?  $this->valDumper->dump($info['value'], $cfg)
                : '',
        ));
        return \implode(' ', $parts);
    }

    /**
     * Iterate over cases, constants, properties, or methods
     *
     * @param ObjectAbstraction   $abs  ObjectAbstraction instance
     * @param list<string>        $keys keys/names of items to output
     * @param array<string,mixed> $cfg  Config options
     *
     * @return string
     */
    private function dumpItemsFiltered(ObjectAbstraction $abs, array $keys, array $cfg)
    {
        $html = '';
        $items = $this->getItems($abs, $cfg['what']);
        $items = \array_intersect_key($items, \array_flip($keys));
        $items = $abs->sort($items, $abs['sort']);
        $absKeys = $abs['keys'];
        \array_walk($items, function ($info, $name) use ($absKeys, $cfg, &$html) {
            if (isset($absKeys[$name])) {
                $name = $absKeys[$name];
            } elseif (\is_string($name)) {
                $name = \preg_replace('/^debug\./', '', $name);
            }
            $vis = (array) $info['visibility'];
            $info = \array_merge(array(
                'declaredLast' => null,
                'declaredPrev' => null,
                'objClassName' => $cfg['objClassName'],  // used by Properties to determine "isDynamic"
            ), $info);
            $info['isInherited'] = $info['declaredLast'] && $info['declaredLast'] !== $info['objClassName'];
            $info['isPrivateAncestor'] = \in_array('private', $vis, true) && $info['isInherited'];
            if ($info['isPrivateAncestor']) {
                $info['isInherited'] = false;
            }
            $html .= $this->dumpItem($name, $info, $cfg) . "\n";
        });
        return $html;
    }

    /**
     * group by inheritance... with headings
     *
     * @param ObjectAbstraction   $abs ObjectAbstraction instance
     * @param array<string,mixed> $cfg Config options
     *
     * @return string
     */
    private function dumpItemsByInheritance(ObjectAbstraction $abs, array $cfg)
    {
        $html = '';
        $items = $this->getItems($abs, $cfg['what']);
        $items = $abs->sort($items, $abs['sort']);
        $itemCount = \count($items);
        $itemOutCount = 0;
        // stop looping over classes when we've output everything
        // no sense in showing "inherited from" when no more inherited items
        // Or, we could only display the heading when itemsFiltered non-empty
        $className = $abs['className'];
        $classes = $this->getInheritedClasses($abs, $cfg['what']);
        \array_unshift($classes, $className);
        while ($classes && $itemOutCount < $itemCount) {
            $classNameCur = \array_shift($classes);
            $itemsFiltered = \array_filter($items, static function ($info) use ($classNameCur) {
                return !isset($info['declaredLast']) || $info['declaredLast'] === $classNameCur;
            });
            $keys = \array_keys($itemsFiltered);
            $items = \array_diff_key($items, $itemsFiltered);
            $itemOutCount += \count($itemsFiltered);
            $html .= $itemsFiltered && \in_array($classNameCur, [$className, 'stdClass'], true) === false
                ? $this->buildInheritedFromHeading($classNameCur)
                : '';
            $html .= $this->dumpItemsFiltered($abs, $keys, $cfg);
        }
        return $html;
    }

    /**
     * Dump "modifiers"
     *
     * @param array<string,mixed> $info Abstraction info
     * @param array<string,mixed> $cfg  Config options
     *
     * @return string html fragment
     */
    protected function dumpModifiers(array $info, array $cfg)
    {
        $modifiers = $this->getModifiers($info, $cfg);
        return \implode(' ', \array_map(static function ($modifier) {
            $class = 't_modifier_' . $modifier;
            $modifier = \str_replace('-set', '(set)', $modifier);
            return '<span class="' . $class . '">' . $modifier . '</span>';
        }, $modifiers));
    }

    /**
     * Dump property/constant/case name
     *
     * @param string|Abstraction $name Property name
     * @param array              $info Property info
     * @param array              $cfg  options
     *
     * @return string html fragment
     */
    private function dumpName($name, array $info, array $cfg)
    {
        $classes = array(
            'no-quotes' => \preg_match('#[\s\r\n]#u', $name) !== 1 && $name !== '' && !$cfg['asArray'],
            't_identifier' => !$cfg['asArray'],
            't_int' => \is_int($name),
            't_key' => $cfg['asArray'],
            't_string' => \is_string($name) && !$cfg['asArray'],
        );
        return $this->html->buildTag(
            'span',
            array(
                'class' => $classes,
                'title' => $cfg['phpDocOutput']
                    ? $this->helper->dumpPhpDoc($info['phpDoc']['summary'])
                    : '',
            ),
            $this->valDumper->dump($name, array(
                'charHighlightTrim' => true, // highlight leading/trailing whitespace
                'tagName' => null, // don't wrap it
            ))
        );
    }

    /**
     * Get html attributes
     *
     * @param array<string,mixed> $info Abstraction info
     * @param array<string,mixed> $cfg  config options
     *
     * @return array<string,mixed>
     */
    protected function getAttribs(array $info, array $cfg)
    {
        $attribs = array(
            'class' => $this->getClasses($info),
            'data-attributes' => $info['attributes'],
            'data-chars' => $this->valDumper->findChars(\json_encode($info['attributes'], JSON_UNESCAPED_UNICODE)),
            'data-declared-prev' => $info['declaredPrev'],
            'data-deprecated-desc' => !empty($info['isDeprecated']) ? $this->getDeprecatedDesc($info) : '',
            'data-inherited-from' => $info['declaredLast'],
        );
        $filter = \array_filter(array(
            'class' => true,
            'data-attributes' => $cfg['attributeOutput'] && $info['attributes'],
            'data-chars' => $cfg['attributeOutput'],
            'data-declared-prev' => empty($info['isInherited']) && !empty($info['declaredPrev']),
            'data-deprecated-desc' => !empty($attribs['data-deprecated-desc']),
            'data-inherited-from' => !empty($info['isInherited']) || $info['isPrivateAncestor'],
        ));
        return \array_intersect_key($attribs, $filter);
    }

    /**
     * Get deprecated description from a combination of #Deprecated and @deprecated
     *
     * @param array $info Abstraction info
     *
     * @return string
     */
    protected function getDeprecatedDesc(array $info)
    {
        $depAttrs = \array_filter($info['attributes'], static function ($attrInfo) {
            return $attrInfo['name'] === 'Deprecated';
        });
        $depAttr = \array_shift($depAttrs) ?: [];
        $desc = '';
        if (isset($depAttr['arguments']['message'])) {
            $desc = $this->helper->dumpPhpDoc($depAttr['arguments']['message'], array(
                'sanitize' => true,
            ));
        } elseif (isset($info['phpDoc']['deprecated'])) {
            $desc = $this->helper->dumpPhpDoc($info['phpDoc']['deprecated'][0]['desc']);
        }
        $since = '';
        if (isset($depAttr['arguments']['since'])) {
            $since = 'since ' . $this->helper->dumpPhpDoc($depAttr['arguments']['since'], array(
                'sanitize' => true,
            ));
        } elseif (isset($info['phpDoc']['deprecated'][0]['version'])) {
            $since = 'since ' . $info['phpDoc']['deprecated'][0]['version'];
        }
        if ($desc && $since) {
            $since = '<em>(' . $since . ')</em>';
        }
        return \implode(' ', \array_filter([$desc, $since]));
    }

    /**
     * Get css classes to apply to item
     *
     * @param array<string,mixed> $info Abstraction info
     *
     * @return string[]
     */
    abstract protected function getClasses(array $info);

    /**
     * Get the extended classes we'll iterate over for "groupByInheritance"
     *
     * @param ObjectAbstraction $abs  Object abstraction
     * @param string            $what 'cases', 'constants', 'properties', or 'methods'
     *
     * @return list<string>
     */
    private function getInheritedClasses(ObjectAbstraction $abs, $what)
    {
        $classes = $abs['extends'];
        if ($abs['isInterface']) {
            // flatten the tree
            \preg_match_all('/("[^"]+")/', \json_encode($classes), $matches);
            $classes = \array_map('json_decode', $matches[1]);
        }
        if ($what === 'constants') {
            // constants can be defined in interface
            // flatten the tree
            \preg_match_all('/("[^"]+")/', \json_encode($abs['implements']), $matches);
            $implementsList = \array_map('json_decode', $matches[1]);
            $classes = \array_merge($classes, $implementsList);
        }
        return $classes;
    }

    /**
     * Get the items to be dumped
     *
     * Extend me for custom filtering
     *
     * @param ObjectAbstraction $abs  Object abstraction
     * @param string            $what 'cases', 'constants', 'properties', or 'methods'
     *
     * @return array
     */
    protected function getItems(ObjectAbstraction $abs, $what)
    {
        return $abs[$what];
    }

    /**
     * Get "modifiers" (abstract, final, readonly, static, etc)
     *
     * @param array<string,mixed> $info Abstraction info
     * @param array<string,mixed> $cfg  Config options
     *
     * @return string[]
     */
    abstract protected function getModifiers(array $info, array $cfg);

    /**
     * Generate some info regarding the given method names
     *
     * @param array $methods method names
     *
     * @return string html fragment
     */
    protected function magicMethodInfo($methods)
    {
        if (!$methods) {
            return '';
        }
        $methods = \array_map(static function ($method) {
            return '<code>' . $method . '</code>';
        }, $methods);
        $methods = \array_values($methods);
        return '<dd class="info magic">'
            . (\count($methods) === 1
                ? $this->debug->i18n->trans('object.methods.magic.1', array('method' => $methods[0]))
                : $this->debug->i18n->trans('object.methods.magic.2', array('method1' => $methods[0], 'method2' => $methods[1]))
            )
            . '</dd>' . "\n";
    }
}
