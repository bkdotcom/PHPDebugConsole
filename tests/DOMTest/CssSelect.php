<?php

namespace bdk;

/**
 * CSS selector class
 */
class CssSelect
{

    protected $DOMXpath;

    /**
     * Constructor
     *
     * @param string|DOMDocument $html HTML string or DOMDocument object
     */
    public function __construct($html = '')
    {
        $this->DOMXpath = $this->getDOMXpath($html);
    }

    /**
     * Magic method
     *
     * Used to access select() non-statically
     *
     * @param string $name      method name
     * @param array  $arguments method arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($name === 'select') {
            return call_user_func_array(array($this, 'selectNonStatic'), $arguments);
        }
    }

    /**
     * Magic method
     *
     * Used to access select() statically
     *
     * @param string $name      method name
     * @param array  $arguments method arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if ($name === 'select') {
            return call_user_func_array(array('self', 'selectStatic'), $arguments);
        }
    }

    /**
     * Select elements from $html using css $selector.
     *
     * @param string|DOMDocument $html     HTML string or DOMDocument
     * @param string             $selector CSS selector
     * @param boolean            $asArray  true
     *
     * @return mixed
     */
    protected static function selectStatic($html, $selector, $asArray = true)
    {
        $DOMXpath = self::getDOMXpath($html);
        $xpath = self::selectorToXpath($selector);
        $elements = $DOMXpath->evaluate($xpath);
        return $asArray
            ? self::elementsToArray($elements)
            : $elements;
    }

    /**
     * Select elements using css $selector.
     *
     * When $asArray is true:
     * matching elements will be return as an associative array containing
     *      name : element name
     *      attributes : attributes array
     *      innerHTML : innner HTML
     *
     * Otherwise regular DOMElement's will be returned.
     *
     * @param string  $selector css selector
     * @param boolean $asArray  [<description>]
     *
     * @return mixed
     */
    protected function selectNonStatic($selector, $asArray = true)
    {
        $DOMXpath = $this->DOMXpath;
        $xpath = self::selectorToXpath($selector);
        $elements = $DOMXpath->evaluate($xpath);
        return $asArray
            ? self::elementsToArray($elements)
            : $elements;
    }

    /**
     * Convert DOMNodeList to an array.
     *
     * @param \DOMNodeList $elements elements
     *
     * @return array
     */
    protected static function elementsToArray(\DOMNodeList $elements)
    {
        $array = array();
        for ($i = 0, $length = $elements->length; $i < $length; ++$i) {
            if ($elements->item($i)->nodeType == XML_ELEMENT_NODE) {
                array_push($array, self::elementToArray($elements->item($i)));
            }
        }
        return $array;
    }

    /**
     * Convert DOMElement to an array.
     *
     * @param \DOMElement $element element
     *
     * @return array
     */
    protected static function elementToArray(\DOMElement $element)
    {
        $array = array(
            'name' => $element->nodeName,
            'attributes' => array(),
            'innerHTML' => self::DOMInnerHTML($element),
            // 'text' => utf8_decode($element->textContent),
            // 'children' => self::elementsToArray($element->childNodes),
        );
        foreach ($element->attributes as $key => $attr) {
            $array['attributes'][$key] = $attr->value;
        }
        return $array;
    }

    /**
     * Build inner html for given DOMElement
     *
     * @param \DOMElement $element dom element
     *
     * @return string html
     */
    protected static function DOMInnerHTML(\DOMElement $element)
    {
        $innerHTML = '';
        foreach ($element->childNodes as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }
        /*
            saveHTML doesn't close "void" tags  :(
        */
        $voidTags = array('area','base','br','col','command','embed','hr','img','input','keygen','link','meta','param','source','track','wbr');
        $regEx = '#<('.implode('|', $voidTags).')(\b[^>]*)>#';
        $innerHTML = preg_replace($regEx, '<\\1\\2 />', $innerHTML);
        return trim($innerHTML);
    }

    /**
     * Return \DOMXpath object
     *
     * @param string|\DOMDocument $html HTML string or \DOMDocument object
     *
     * @return DOUMXpath
     */
    protected static function getDOMXpath($html)
    {
        if ($html instanceof \DOMDocument) {
            $DOMXpath = new \DOMXpath($html);
        } else {
            libxml_use_internal_errors(true);
            if (empty($html)) {
                $html = '<!-- empty document -->';
            }
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);    // WTF!
            foreach ($dom->childNodes as $node) {
                if ($node->nodeType == XML_PI_NODE) {
                    $dom->removeChild($node); // remove hack
                    break;
                }
            }
            $dom->encoding = 'UTF-8';
            $DOMXpath = new \DOMXpath($dom);
        }
        return $DOMXpath;
    }

    /**
     * Convert $selector into an XPath string.
     *
     * @param string $selector CSS selector
     *
     * @return string
     */
    public static function selectorToXpath($selector)
    {
        // remove spaces around operators
        $selector = preg_replace('/\s*>\s*/', '>', $selector);
        $selector = preg_replace('/\s*~\s*/', '~', $selector);
        $selector = preg_replace('/\s*\+\s*/', '+', $selector);
        $selector = preg_replace('/\s*,\s*/', ',', $selector);
        $selectors = preg_split('/\s+(?![^\[]+\])/', $selector);
        foreach ($selectors as &$selector) {
            // ,
            $selector = preg_replace('/,/', '|descendant-or-self::', $selector);
            // input:checked, :disabled, etc.
            $selector = preg_replace('/(.+)?:(checked|disabled|required|autofocus)/', '\1[@\2="\2"]', $selector);
            // input:autocomplete, :autocomplete
            $selector = preg_replace('/(.+)?:(autocomplete)/', '\1[@\2="on"]', $selector);
            // input:button, input:submit, etc.
            $selector = preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/', 'input[@type="\1"]', $selector);
            // foo[id]
            $selector = preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/', '\\1[@\\2]', $selector);
            // [id]
            $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)\]/', '*[@\\1]', $selector);
            // foo[id=foo]
            $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/', '[@\\1="\\2"]', $selector);
            // [id=foo]
            $selector = preg_replace('/^\[/', '*[', $selector);
            // div#foo
            $selector = preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/', '\\1[@id="\\2"]', $selector);
            // #foo
            $selector = preg_replace('/\#([_\w-]+[_\w\d-]*)/', '*[@id="\\1"]', $selector);
            // div.foo
            $selector = preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/', '\\1[contains(concat(" ",@class," ")," \\2 ")]', $selector);
            // .foo
            $selector = preg_replace('/\.([_\w-]+[_\w\d-]*)/', '*[contains(concat(" ",@class," ")," \\1 ")]', $selector);
            // div:first-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):first-child/', '*/\\1[position()=1]', $selector);
            // div:last-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):last-child/', '*/\\1[position()=last()]', $selector);
            // :first-child
            $selector = str_replace(':first-child', '*/*[position()=1]', $selector);
            // :last-child
            $selector = str_replace(':last-child', '*/*[position()=last()]', $selector);
            // :nth-last-child
            $selector = preg_replace('/:nth-last-child\((\d+)\)/', '[position()=(last() - (\\1 - 1))]', $selector);
            // div:nth-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/', '*/*[position()=\\2 and self::\1]', $selector);
            // :nth-child
            $selector = preg_replace('/:nth-child\((\d+)\)/', '*/*[position()=\\1]', $selector);
            // :contains(Foo)
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/', '\\1[contains(string(.),"\\2")]', $selector);
            // >
            $selector = preg_replace('/>/', '/', $selector);
            // ~
            $selector = preg_replace('/~/', '/following-sibling::', $selector);
            // +
            $selector = preg_replace('/\+([_\w-]+[_\w\d-]*)/', '/following-sibling::\\1[position()=1]', $selector);
            $selector = str_replace(']*', ']', $selector);
            $selector = str_replace(']/*[position', '][position', $selector);
        }

        // ' '
        $selector = implode('/descendant::', $selectors);
        $selector = 'descendant-or-self::' . $selector;
        // :scope
        $selector = preg_replace('/(((\|)?descendant-or-self::):scope)/', '.\\3', $selector);
        // $element
        $sub_selectors = explode(',', $selector);

        foreach ($sub_selectors as $key => $sub_selector) {
            $parts = explode('$', $sub_selector);
            $sub_selector = array_shift($parts);
            if (count($parts) && preg_match_all('/((?:[^\/]*\/?\/?)|$)/', $parts[0], $matches)) {
                $results = $matches[0];
                $results[] = str_repeat('/..', count($results) - 2);
                $sub_selector .= implode('', $results);
            }
            $sub_selectors[$key] = $sub_selector;
        }
        $selector = implode(',', $sub_selectors);
        return $selector;
    }
}
