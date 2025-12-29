<?php

namespace bdk\Table;

use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\Html;
use InvalidArgumentException;
use JsonSerializable;
use Serializable;

/**
 * Base class for table elements (Table, TableRow, TableCell)
 */
class Element implements JsonSerializable, Serializable
{
    /** @var array<string,mixed> */
    protected $attribs = array();

    /** @var bool */
    protected $buildingHtml = false;

    /** @var list<Element> */
    protected $children = array();

    /** @var array<string,mixed> */
    protected $defaults = array(
        'tagName' => 'div',
    );

    /** @var string|null */
    protected $html = null;

    /** @var array<string,mixed> */
    protected $meta = array();

    /** @var Element|null */
    protected $parent = null;

    /** @var string */
    protected $tagName = 'div';

    /**
     * Constructor
     *
     * @param string               $tagName        Element's tagName (ie 'div', 'span', 'td', etc)
     * @param list<Element>|string $childrenOrHtml Child elements or HTML content
     */
    public function __construct($tagName, $childrenOrHtml = '')
    {
        $this->setTagName($tagName);
        if (ArrayUtil::isList($childrenOrHtml)) {
            $this->setChildren($childrenOrHtml);
            return;
        }
        if (\is_array($childrenOrHtml)) {
            // associative array of properties
            foreach ($childrenOrHtml as $key => $val) {
                $method = 'set' . \ucfirst($key);
                $this->$method($val);
            }
            return;
        }
        $this->setHtml($childrenOrHtml);
    }

    /**
     * Serialize magic method
     * (since php 7.4)
     *
     * @return array<TKey,TValue>
     */
    public function __serialize()
    {
        $data = array(
            'attribs' => $this->attribs,
            'children' => \array_map(static function (self $child) {
                $data = $child->__serialize();
                if (\count($data) === 1) {
                    return \reset($data);
                }
                return $data;
            }, $this->children),
            'html' => $this->html,
            'meta' => $this->meta,
            'tagName' => $this->tagName,
        );
        $data = \array_filter($data, static function ($val) {
            return $val !== null && $val !== [];
        });
        return ArrayUtil::diffDeep($data, $this->defaults);
    }

    /**
     * Unserialize
     *
     * @param array $data serialized data
     *
     * @return void
     */
    public function __unserialize(array $data)
    {
        foreach ($data as $key => $val) {
            $method = 'set' . \ucfirst($key);
            if (\method_exists($this, $method)) {
                $this->$method($val);
            }
        }
    }

    /**
     * Get html attributes
     *
     * @return array
     */
    public function getAttribs()
    {
        $defaultAttribs = isset($this->defaults['attribs'])
            ? $this->defaults['attribs']
            : array();
        $attribs = ArrayUtil::mergeDeep($defaultAttribs, $this->attribs);
        \ksort($attribs);
        return $attribs;
    }

    /**
     * Get child elements
     *
     * @return array<AbstractElement>
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Get "inner" html content
     *
     * @return string|null
     */
    public function getHtml()
    {
        $children = $this->getChildren();
        if ($children) {
            $innerHtml = "\n" . \implode('', \array_map(static function (self $child) {
                return $child->getOuterHtml() . "\n";
            }, $children));
            $innerHtml = \str_replace("\n", "\n  ", $innerHtml);
            $innerHtml = \substr($innerHtml, 0, -2);
            return $innerHtml;
        }
        return $this->html;
    }

    /**
     * Get the index of this element amongst it's siblings
     *
     * @return int|null
     */
    public function getIndex()
    {
        if ($this->parent === null) {
            return null;
        }
        $siblings = $this->parent->getChildren();
        return \array_search($this, $siblings, true);
    }

    /**
     * Return element as html
     *
     * @return string
     */
    public function getOuterHtml()
    {
        // get innerHTML first... may update attribs
        $innerHtml = $this->getHtml();
        return Html::buildTag($this->getTagName(), $this->getAttribs(), $innerHtml);
    }

    /**
     * Get meta value(s)
     *
     * @param string $key     key to get
     *                        if not passed, return all meta values
     * @param mixed  $default (null) value to get
     *
     * @return mixed
     */
    public function getMeta($key = null, $default = null)
    {
        if ($key === null) {
            return $this->meta;
        }
        return \array_key_exists($key, $this->meta)
            ? $this->meta[$key]
            : $default;
    }

    /**
     * Get parent element
     *
     * @return Element|null;
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get element's tag name (ie 'span', 'div', 'td', etc)
     *
     * @return string
     */
    public function getTagName()
    {
        return $this->tagName;
    }

    /**
     * Get element's text content
     *
     * @return string
     */
    public function getText()
    {
        $children = $this->getChildren();
        if ($children) {
            return \implode('', \array_map(static function (self $child) {
                return $child->getText();
            }, $children));
        }
        return \htmlspecialchars_decode(\strip_tags($this->html));
    }

    /**
     * Add class(es) to element
     *
     * @param string|list<string> $class Class(es) to add
     *
     * @return $this
     */
    public function addClass($class)
    {
        $newClasses = $this->normalizeClass($class);
        $classesBefore = [];
        if ($this->buildingHtml && isset($this->defaults['attribs']['class'])) {
            $classesBefore = $this->defaults['attribs']['class'];
        } elseif (isset($this->attribs['class'])) {
            $classesBefore = $this->attribs['class'];
        }
        $classesAfter = \array_merge($classesBefore, $newClasses);
        return $this->setAttrib('class', $classesAfter);
    }

    /**
     * Append child element
     *
     * @param self $child Child element
     *
     * @return $this
     */
    public function appendChild(self $child)
    {
        $child->setParent($this);
        $this->children[] = $child;
        return $this;
    }

    /**
     * Remove class(es) from element
     *
     * @param string|list<string> $class Class(es) to remove
     *
     * @return $this
     */
    public function removeClass($class)
    {
        $removeClasses = $this->normalizeClass($class);
        $classesBefore = \array_key_exists('class', $this->attribs)
            ? $this->attribs['class']
            : [];
        $classesAfter = \array_diff($classesBefore, $removeClasses);
        return $this->setAttrib('class', $classesAfter);
    }

    /**
     * Update attribute value
     *
     * @param string $name  Name
     * @param mixed  $value Value
     *
     * @return $this
     */
    public function setAttrib($name, $value)
    {
        if ($name === 'class') {
            $value = $this->normalizeClass($value);
        }
        $attribs = &$this->attribs;
        if ($this->buildingHtml) {
            $this->defaults = \array_merge(array(
                'attribs' => array(),
            ), $this->defaults);
            $attribs = &$this->defaults['attribs'];
        }
        $attribs[$name] = $value;
        if ($name === 'class' && empty($value)) {
            unset($attribs[$name]);
        }
        return $this;
    }

    /**
     * Set html attribute(s)
     *
     * @param array $attribs Attributes
     *
     * @return $this
     */
    public function setAttribs(array $attribs)
    {
        \array_walk($attribs, function ($value, $key) {
            $this->setAttrib($key, $value);
        });
        return $this;
    }

    /**
     * Set children elements
     *
     * @param array $children Child elements
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setChildren(array $children)
    {
        foreach ($children as $child) {
            if (($child instanceof self) === false) {
                throw new InvalidArgumentException('Children must be instances of ' . __CLASS__);
            }
            $child->setParent($this);
        }
        $this->children = $children;
        return $this;
    }

    /**
     * Set default values
     *
     * Default values will not be included when serializing
     *
     * @param array $defaults Default values
     *
     * @return $this
     */
    public function setDefaults($defaults)
    {
        $this->defaults = $defaults;
        return $this;
    }

    /**
     * Set html content
     *
     * @param string $html Html content
     *
     * @return $this
     */
    public function setHtml($html)
    {
        $this->html = $html;
        return $this;
    }

    /**
     * Set meta value(s)
     *
     * Value(s) get merged with existing values
     *
     * @param mixed $mixed (string) key or (array) key/value array
     * @param mixed $val   value if updating a single key
     *
     * @return $this
     */
    public function setMeta($mixed, $val = null)
    {
        if (\is_array($mixed) === false) {
            $mixed = array($mixed => $val);
        }
        $this->meta = \array_merge($this->meta, $mixed);
        return $this;
    }

    /**
     * Set parent element
     *
     * @param Element|null $parent Parent element
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setParent($parent)
    {
        if ($parent !== null && ($parent instanceof Element) === false) {
            throw new InvalidArgumentException('Parent must be instance of ' . __CLASS__ . ' (or null)');
        }
        $this->parent = $parent;
        return $this;
    }

    /**
     * Set the tagName of the element (ie 'div', 'span', 'td', etc)
     *
     * @param string $tagName Element's tagName
     *
     * @return $this
     */
    public function setTagName($tagName)
    {
        $this->tagName = \strtolower($tagName);
        return $this;
    }

    /**
     * Set text content
     *
     * @param string $text Text content
     *
     * @return $this
     */
    public function setText($text)
    {
        $this->html = \htmlspecialchars($text);
        return $this;
    }

    /**
     * Implements `JsonSerializable`
     *
     * @return array<TKey,TValue>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $data = $this->__serialize();
        if (\array_keys($data) === ['children']) {
            return $data['children'];
        }
        if (\array_keys($data) === ['html']) {
            return $data['html'];
        }
        return $data;
    }

    /**
     * Implements `Serializable`
     *
     * @return string
     */
    public function serialize()
    {
        return \serialize($this->__serialize());
    }

    /**
     * Implements `Serializable`
     *
     * @param string $data serialized data
     *
     * @return void
     */
    public function unserialize($data)
    {
        /** @var mixed */
        $unserialized = \unserialize($data);
        if (\is_array($unserialized)) {
            $this->__unserialize($unserialized);
        }
    }

    /**
     * Normalize class value
     *
     * @param list<string>|array $class Class(es)
     *
     * @return list<string>
     */
    private function normalizeClass($class)
    {
        if (\is_string($class)) {
            $class = \explode(' ', $class);
        }
        $class = \array_unique(\array_filter($class));
        \sort($class);
        return $class;
    }
}
