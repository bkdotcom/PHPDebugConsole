<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\ObjectMethods;
use bdk\Debug\Dump\Html\ObjectProperties;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Debug\Utility\Html as HtmlUtil;

/**
 * Output object as HTML
 */
class HtmlObject
{
    public $valDumper;
    protected $constants;
    protected $helper;
    protected $html;
    protected $methods;
    protected $properties;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Dump\Html instance
     * @param Helper    $helper    Html dump helpers
     * @param HtmlUtil  $html      Html methods
     */
    public function __construct(ValDumper $valDumper, Helper $helper, HtmlUtil $html)
    {
        $this->valDumper = $valDumper;
        $this->helper = $helper;
        $this->html = $html;
        $this->constants = new ObjectConstants($this, $helper, $html);
        $this->methods = new ObjectMethods($this, $helper, $html);
        $this->properties = new ObjectProperties($this, $helper, $html);
    }

    /**
     * Dump object
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        $classname = $this->dumpClassname($abs);
        if ($abs['isRecursion']) {
            return $classname . "\n" . '<span class="t_recursion">*RECURSION*</span>';
        }
        if ($abs['isExcluded']) {
            return $this->dumpToString($abs)
                . $classname . "\n" . '<span class="excluded">NOT INSPECTED</span>';
        }
        if (($abs['cfgFlags'] & AbstractObject::BRIEF) && \in_array('UnitEnum', $abs['implements'], true)) {
            return $classname;
        }
        $html = $this->dumpToString($abs)
            . $classname . "\n"
            . '<dl class="object-inner">' . "\n"
                . $this->dumpModifiers($abs)
                . $this->dumpExtends($abs)
                . $this->dumpImplements($abs)
                . $this->dumpAttributes($abs)
                . $this->constants->dumpConstants($abs)
                . $this->constants->dumpCases($abs)
                . $this->properties->dump($abs)
                . $this->methods->dump($abs)
                . $this->dumpPhpDoc($abs)
            . '</dl>' . "\n";
        return $this->cleanup($html);
    }

    /**
     * Generate some info regarding the given method names
     *
     * @param array $methods method names
     *
     * @return string html fragment
     */
    public function magicMethodInfo($methods)
    {
        if (!$methods) {
            return '';
        }
        $methods = \array_map(static function ($method) {
            return '<code>' . $method . '</code>';
        }, $methods);
        $methods = \count($methods) === 1
            ? 'a ' . $methods[0] . ' method'
            : \implode(' and ', $methods) . ' methods';
        return '<dd class="info magic">This object has ' . $methods . '</dd>' . "\n";
    }

    /**
     * Remove empty tags and unneeded attributes
     *
     * @param string $html html fragment
     *
     * @return string html fragment
     */
    private function cleanup($html)
    {
        // remove <dt>'s that have no <dd>'
        $html = \preg_replace('#(?:<dt>(?:extends|implements|phpDoc)</dt>\n)+(<dt|</dl)#', '$1', $html);
        $html = \str_replace(array(
            ' data-attributes="null"',
            ' data-inherited-from="null"',
            ' title=""',
        ), '', $html);
        return $html;
    }

    /**
     * Dump object attributes
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpAttributes(Abstraction $abs)
    {
        $attributes = $abs['attributes'];
        if (!$attributes || !($abs['cfgFlags'] & AbstractObject::OBJ_ATTRIBUTE_OUTPUT)) {
            return '';
        }
        $str = '<dt class="attributes">attributes</dt>' . "\n";
        foreach ($attributes as $info) {
            $str .= '<dd class="attribute">'
                . $this->valDumper->markupIdentifier($info['name'])
                . $this->dumpAttributeArgs($info['arguments'])
                . '</dd>' . "\n";
        }
        return $str;
    }

    /**
     * Dump attribute arguments
     *
     * @param array $args Attribute arguments
     *
     * @return string html fragment
     */
    protected function dumpAttributeArgs($args)
    {
        if (!$args) {
            return '';
        }
        foreach ($args as $name => $value) {
            $arg = '';
            if (\is_string($name)) {
                $arg .= '<span class="t_parameter-name">' . \htmlspecialchars($name) . '</span>'
                    . '<span class="t_punct t_colon">:</span>';
            }
            $arg .= $this->valDumper->dump($value);
            $args[$name] = $arg;
        }
        return '<span class="t_punct">(</span>'
            . \implode('<span class="t_punct">,</span> ', $args)
            . '<span class="t_punct">)</span>';
    }

    /**
     * Dump classname of object
     * Classname may be wrapped in a span that includes phpDoc summary / desc
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpClassname(Abstraction $abs)
    {
        $phpDocOutput = $abs['cfgFlags'] & AbstractObject::PHPDOC_OUTPUT;
        $title = $phpDocOutput
            ? \trim($abs['phpDoc']['summary'] . "\n\n" . $abs['phpDoc']['desc'])
            : null;
        if (\in_array('UnitEnum', $abs['implements'], true)) {
            return $this->html->buildTag(
                'span',
                \array_filter(array(
                    'class' => 't_const',
                    'title' => $title,
                )),
                $this->valDumper->markupIdentifier($abs['className'] . '::' . $abs['properties']['name']['value'])
            );
        }
        return $this->valDumper->markupIdentifier($abs['className'], false, 'span', array(
            'title' => $title,
        ));
    }

    /**
     * Dump classnames of classes obj extends
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpExtends(Abstraction $abs)
    {
        return '<dt>extends</dt>' . "\n"
            . \implode(\array_map(function ($classname) {
                return '<dd class="extends">' . $this->valDumper->markupIdentifier($classname) . '</dd>' . "\n";
            }, $abs['extends']));
    }

    /**
     * Dump classnames of interfaces obj extends
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpImplements(Abstraction $abs)
    {
        return '<dt>implements</dt>' . "\n"
            . \implode(\array_map(function ($classname) {
                return '<dd class="interface">' . $this->valDumper->markupIdentifier($classname) . '</dd>' . "\n";
            }, $abs['implements']));
    }

    /**
     * Dump method modifiers (final)
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpModifiers(Abstraction $abs)
    {
        return $abs['isFinal']
            ? '<dt class="t_modifier_final">final</dt>' . "\n"
            : '';
    }

    /**
     * Dump object's phpDoc info
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpPhpDoc(Abstraction $abs)
    {
        $str = '<dt>phpDoc</dt>' . "\n";
        foreach ($abs['phpDoc'] as $tagName => $values) {
            if (\is_array($values) === false) {
                continue;
            }
            foreach ($values as $tagData) {
                $tagData['tagName'] = $tagName;
                $str .= $this->dumpPhpDocTag($tagData);
            }
        }
        return $str;
    }

    /**
     * Markup tag
     *
     * @param array $tagData parsed tag
     *
     * @return string html fragment
     */
    private function dumpPhpDocTag($tagData)
    {
        $tagName = $tagData['tagName'];
        switch ($tagName) {
            case 'author':
                $info = $this->dumpPhpDocTagAuthor($tagData);
                break;
            case 'link':
            case 'see':
                $desc = $tagData['desc'] ?: $tagData['uri'] ?: '';
                if (isset($tagData['uri'])) {
                    $info = '<a href="' . $tagData['uri'] . '" target="_blank">' . \htmlspecialchars($desc) . '</a>';
                    break;
                }
                // see tag
                $info = $this->valDumper->markupIdentifier($tagData['fqsen'])
                    . ' <span class="phpdoc-desc">' . \htmlspecialchars($desc) . '</span>';
                $info = \str_replace(' <span class="phpdoc-desc"></span>', '', $info);
                break;
            default:
                unset($tagData['tagName']);
                $info = \htmlspecialchars(\implode(' ', $tagData));
        }
        return '<dd class="phpdoc phpdoc-' . $tagName . '">'
            . '<span class="phpdoc-tag">' . $tagName . '</span>'
            . '<span class="t_operator">:</span> '
            . $info
            . '</dd>' . "\n";
    }

    /**
     * Dump PhpDoc author tag value
     *
     * @param array $tagData parsed tag
     *
     * @return string html partial
     */
    private function dumpPhpDocTagAuthor($tagData)
    {
        $html = $tagData['name'];
        if ($tagData['email']) {
            $html .= ' &lt;<a href="mailto:' . $tagData['email'] . '">' . $tagData['email'] . '</a>&gt;';
        }
        if ($tagData['desc']) {
            // desc is non-standard for author tag
            $html .= ' ' . \htmlspecialchars($tagData['desc']);
        }
        return $html;
    }

    /**
     * Dump object's __toString or stringified value
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    protected function dumpToString(Abstraction $abs)
    {
        $len = 0;
        $val = $this->getToStringVal($abs, $len);
        if ($val === $abs['className']) {
            return '';
        }
        $valAppend = '';
        $classes = array('t_stringified');
        if ($len > 100) {
            $classes[] = 't_string_trunc';   // truncated
            $val = \substr($val, 0, 100);
            $valAppend = '&hellip; <i>(' . ($len - 100) . ' more bytes)</i>';
        }
        $valDumped = $this->valDumper->dump($val);
        $parsed = $this->html->parseTag($valDumped);
        return $this->html->buildTag(
            'span',
            array(
                'class' => \array_merge($classes, $parsed['attribs']['class']),
                'title' => \implode(' : ', \array_filter(array(
                    !$abs['stringified'] ? '__toString()' : null,
                    // ie a timestamp will have a human readable date in title
                    isset($parsed['attribs']['title']) ? $parsed['attribs']['title'] : null,
                )))
            ),
            $parsed['innerhtml'] . $valAppend
        ) . "\n";
    }

    /**
     * Get object's "string" representation
     *
     * @param Abstraction $abs    Object Abstraction instance
     * @param int         $strlen updated to ength of non-truncated value
     *
     * @return string
     */
    private function getToStringVal(Abstraction $abs, &$strlen)
    {
        $val = $abs['className'];
        if ($abs['stringified']) {
            $val = $abs['stringified'];
        } elseif (($abs['cfgFlags'] & AbstractObject::TO_STRING_OUTPUT) && isset($abs['methods']['__toString']['returnValue'])) {
            $val = $abs['methods']['__toString']['returnValue'];
        }
        if ($val instanceof Abstraction) {
            $strlen = $val['strlen'];
            $val = $val['value'];
        } elseif (\is_string($val)) {
            $strlen = \strlen($val);
        }
        return $val;
    }
}
