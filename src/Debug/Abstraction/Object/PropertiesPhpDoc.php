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

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Helper;

/**
 * Get object magic-property info
 */
class PropertiesPhpDoc
{
    /** @var Helper */
    protected $helper;

    /** @var array<string,string> */
    private $magicPhpDocTags = array(
        'property' => 'magic',
        'property-read' => 'magic-read',
        'property-write' => 'magic-write',
    );

    /**
     * Constructor
     *
     * @param Helper $helper Helper instance
     */
    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * "Magic" properties may be defined in a class' doc-block
     * If so... move this information to the properties array
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     *
     * @see http://docs.phpdoc.org/references/phpdoc/tags/property.html
     */
    public function addViaPhpDoc(Abstraction $abs)
    {
        $declaredLast = $abs['className'];
        $phpDoc = $this->helper->getPhpDoc($abs['reflector'], $abs['fullyQualifyPhpDocType']);
        $haveMagic = \array_intersect_key($phpDoc, $this->magicPhpDocTags);
        if (!$haveMagic && $abs['reflector']->hasMethod('__get')) {
            // phpDoc doesn't contain any @property tags
            // we've got __get method:  check if parent classes have @property tags
            $declaredLast = $this->addViaPhpDocInherit($abs);
            $haveMagic = $declaredLast !== $abs['className'];
        }
        if ($haveMagic) {
            $this->addViaPhpDocIter($abs, $declaredLast);
        }
    }

    /**
     * Inspect inherited classes until we find properties defined in PhpDoc
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string|null class where found
     */
    private function addViaPhpDocInherit(Abstraction $abs)
    {
        $declaredLast = $abs['className'];
        $reflector = $abs['reflector'];
        while ($reflector = $reflector->getParentClass()) {
            $parsed = $this->helper->getPhpDoc($reflector, $abs['fullyQualifyPhpDocType']);
            $tagIntersect = \array_intersect_key($parsed, $this->magicPhpDocTags);
            if ($tagIntersect === array()) {
                continue;
            }
            $declaredLast = $reflector->getName();
            $abs['phpDoc'] = \array_merge(
                $abs['phpDoc'],
                $tagIntersect
            );
            break;
        }
        return $declaredLast;
    }

    /**
     * Iterate over PhpDoc's magic properties & add to abstraction
     *
     * @param Abstraction $abs          Object Abstraction instance
     * @param string|null $declaredLast Where the magic properties were found
     *
     * @return void
     */
    private function addViaPhpDocIter(Abstraction $abs, $declaredLast)
    {
        $properties = $abs['properties'];
        $tags = \array_intersect_key($this->magicPhpDocTags, $abs['phpDoc']);
        foreach ($tags as $tag => $vis) {
            foreach ($abs['phpDoc'][$tag] as $phpDocProp) {
                $name = $phpDocProp['name'];
                $properties[$name] = $this->buildViaPhpDoc($abs, $phpDocProp, $declaredLast, $vis);
            }
            unset($abs['phpDoc'][$tag]);
        }
        $abs['properties'] = $properties;
    }

    /**
     * Build property info from parsed PhpDoc magic property values
     *
     * @param Abstraction $abs          Object Abstraction instance
     * @param array       $phpDocProp   parsed property docblock tag
     * @param string      $declaredLast className
     * @param string      $vis          prop visibility
     *
     * @return array
     */
    private function buildViaPhpDoc(Abstraction $abs, $phpDocProp, $declaredLast, $vis)
    {
        $name = $phpDocProp['name'];
        $existing = isset($abs['properties'][$name])
            ? $abs['properties'][$name]
            : null;
        return \array_merge(
            $existing ?: Properties::buildValues(),
            array(
                'declaredLast' => $declaredLast,
                'phpDoc' => array(
                    'desc' => '',
                    'summary' => $abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT
                        ? $phpDocProp['desc']
                        : '',
                ),
                'type' => $phpDocProp['type'],
                'visibility' => $existing
                    ? \array_merge((array) $vis, (array) $existing['visibility']) // we want "magic" visibility first
                    : (array) $vis,
            )
        );
    }
}
