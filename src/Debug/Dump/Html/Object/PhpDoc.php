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

use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use BDK\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Value as ValDumper;

/**
 * Dump object properties as HTML
 */
class PhpDoc
{
    /** @var ValDumper */
    public $valDumper;

    /** @var Helper */
    protected $helper;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Html dumper
     * @param Helper    $helper    Html dump helpers
     */
    public function __construct(ValDumper $valDumper, Helper $helper)
    {
        $this->valDumper = $valDumper;
        $this->helper = $helper;
    }

    /**
     * Dump phpDoc info
     *
     * @param ObjectAbstraction|array $phpDoc Object Abstraction instance or array of phpDoc tags/values
     *
     * @return string html fragment
     */
    public function dump($phpDoc)
    {
        if ($phpDoc instanceof ObjectAbstraction) {
            $phpDoc = $phpDoc['phpDoc'];
        }
        $phpDoc = \array_filter($phpDoc, 'is_array');
        $phpDoc = $this->getItems($phpDoc);
        $str = '<dt>phpDoc</dt>' . "\n";
        foreach ($phpDoc as $tagName => $values) {
            if ($tagName === 'package') {
                $values['tagName'] = 'package';
                $str .= $this->dumpTag($values);
                continue;
            }
            foreach ($values as $tagData) {
                $tagData['tagName'] = $tagName;
                $str .= $this->dumpTag($tagData);
            }
        }
        return $str;
    }

    /**
     * Markup tag
     *
     * @param array $tagData Parsed tag
     *
     * @return string html fragment
     */
    private function dumpTag(array $tagData)
    {
        $tagName = $tagData['tagName'];
        switch ($tagName) {
            case 'author':
                $value = $this->dumpTagAuthor($tagData);
                break;
            case 'link':
            case 'see':
                $value = $this->dumpTagSeeLink($tagData);
                break;
            default:
                unset($tagData['tagName']);
                $value = $this->helper->dumpPhpDoc(\implode(' ', $tagData));
        }
        return '<dd class="phpdoc phpdoc-' . $tagName . '">'
            . '<span class="phpdoc-tag">' . $this->valDumper->dump($tagName, array(
                'tagName' => null,
                'type' => Type::TYPE_STRING,
            )) . '</span>'
            . '<span class="t_operator">:</span> '
            . $value
            . '</dd>' . "\n";
    }

    /**
     * Dump PhpDoc author tag value
     *
     * @param array $tagData parsed tag
     *
     * @return string html partial
     */
    private function dumpTagAuthor(array $tagData)
    {
        $html = $this->helper->dumpPhpDoc($tagData['name']);
        if ($tagData['email']) {
            $emailEscaped = $this->valDumper->string->dump($tagData['email']);
            $html .= ' &lt;<a href="mailto:' . $tagData['email'] . '">' . $emailEscaped . '</a>&gt;';
        }
        if ($tagData['desc']) {
            // desc is non-standard for author tag
            $html .= ' ' . $this->helper->dumpPhpDoc($tagData['desc']);
        }
        return $html;
    }

    /**
     * Dump PhpDoc see and link tag value
     *
     * @param array $tagData parsed tag
     *
     * @return string html partial
     */
    private function dumpTagSeeLink(array $tagData)
    {
        $desc = $this->helper->dumpPhpDoc($tagData['desc'] ?: $tagData['uri'] ?: '');
        if (isset($tagData['uri'])) {
            return '<a href="' . $tagData['uri'] . '" target="_blank">' . $desc . '</a>';
        }
        // see tag
        $info = $this->valDumper->markupIdentifier($tagData['fqsen'])
            . ' <span class="phpdoc-desc">' . $desc . '</span>';
        return \str_replace(' <span class="phpdoc-desc"></span>', '', $info);
    }

    /**
     * Get the phpDoc tags to be dumped
     *
     * Extend me for custom filtering
     *
     * @param array $phpDoc info
     *
     * @return array
     */
    protected function getItems(array $phpDoc)
    {
        return $phpDoc;
    }
}
