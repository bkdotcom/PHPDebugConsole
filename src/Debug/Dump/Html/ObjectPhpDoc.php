<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.3
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use BDK\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Value as ValDumper;

/**
 * Dump object properties as HTML
 */
class ObjectPhpDoc
{
    public $valDumper;

    /**
     * Constructor
     *
     * @param ValDumper $valDumper Html dumper
     */
    public function __construct(ValDumper $valDumper)
    {
        $this->valDumper = $valDumper;
    }

    /**
     * Dump object's phpDoc info
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html fragment
     */
    public function dump(Abstraction $abs)
    {
        $str = '<dt>phpDoc</dt>' . "\n";
        foreach ($abs['phpDoc'] as $tagName => $values) {
            if (\is_array($values) === false) {
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
                $value = $this->valDumper->dump(\implode(' ', $tagData), array(
                    'tagName' => null,
                    'type' => Type::TYPE_STRING,
                ));
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
     * Dump PhpDoc see and link tag value
     *
     * @param array $tagData parsed tag
     *
     * @return string html partial
     */
    private function dumpTagSeeLink(array $tagData)
    {
        $desc = $tagData['desc'] ?: $tagData['uri'] ?: '';
        if (isset($tagData['uri'])) {
            return '<a href="' . $tagData['uri'] . '" target="_blank">' . \htmlspecialchars($desc) . '</a>';
        }
        // see tag
        $info = $this->valDumper->markupIdentifier($tagData['fqsen'])
            . ' <span class="phpdoc-desc">' . \htmlspecialchars($desc) . '</span>';
        return \str_replace(' <span class="phpdoc-desc"></span>', '', $info);
    }
}
