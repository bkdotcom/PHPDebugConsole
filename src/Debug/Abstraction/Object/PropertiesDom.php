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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;

/**
 * Get object property info
 */
/**
 * Get object property info
 */
class PropertiesDom
{
    /** @var Abstracter */
    private $abstracter;

    /** @var array<string,string> */
    private $domNodeProps = array(
        'attributes' => 'DOMNamedNodeMap',
        'childNodes' => 'DOMNodeList',
        'firstChild' => 'DOMNode',
        'lastChild' => 'DOMNode',
        'localName' => 'string',
        'namespaceURI' => 'string',
        'nextSibling' => 'DOMNode', // var_dump() doesn't include ¯\_(ツ)_/¯
        'nodeName' => 'string',
        'nodeType' => 'int',
        'nodeValue' => 'string',
        'ownerDocument' => 'DOMDocument',
        'parentNode' => 'DOMNode',
        'prefix' => 'string',
        'previousSibling' => 'DOMNode',
        'textContent' => 'string',
    );

    /** @var array<string,string> */
    private $domDocumentProps = array(
        'actualEncoding' => 'string',
        'baseURI' => 'string',
        'config' => 'DOMConfiguration',
        'doctype' => 'DOMDocumentType',
        'documentElement' => 'DOMElement',
        'documentURI' => 'string',
        'encoding' => 'string',
        'formatOutput' => 'bool',
        'implementation' => 'DOMImplementation',
        'preserveWhiteSpace' => 'bool',
        'recover' => 'bool',
        'resolveExternals' => 'bool',
        'standalone' => 'bool',
        'strictErrorChecking' => 'bool',
        'substituteEntities' => 'bool',
        'validateOnParse' => 'bool',
        'version' => 'string',
        'xmlEncoding' => 'string',
        'xmlStandalone' => 'bool',
        'xmlVersion' => 'string',
    );

    /** @var array<string,string> */
    private $domElementProps = array(
        'schemaTypeInfo' => 'bool',
        'tagName' => 'string',
    );

    /**
     * Constructor
     *
     * @param Abstracter $abstracter Abstracter instance
     */
    public function __construct(Abstracter $abstracter)
    {
        $this->abstracter = $abstracter;
    }

    /**
     * Add properties to Dom* abstraction
     *
     * DOM* properties are invisible to reflection
     * https://bugs.php.net/bug.php?id=48527
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function add(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        if ($abs['properties']) {
            return;
        }
        if ($this->isDomObj($obj) === false) {
            return;
        }
        // for php < 8.1
        $props = $this->getProps($obj);
        foreach ($props as $propName => $type) {
            $val = $obj->{$propName};
            if (!$type) {
                $type = $this->abstracter->type->getType($val)[0];
            }
            $abs['properties'][$propName] = Properties::buildValues(array(
                'type' => $type,
                'value' => \is_object($val)
                    ? Abstracter::NOT_INSPECTED
                    : $val,
            ));
        }
    }

    /**
     * use print_r to get the property names
     * get_object_vars() doesn't work
     * var_dump may be overridden by xdebug...  and if xdebug v3 unable to disable at runtime
     *
     * PHP < 8.1
     *
     * @param object $obj DOMXXX instance
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    private function getProps($obj)
    {
        $dump = \print_r($obj, true);
        $matches = [];
        \preg_match_all('/^\s+\[(.+?)\] => /m', $dump, $matches);
        $props = \array_fill_keys($matches[1], null);
        if ($obj instanceof DOMNode) {
            $props = \array_merge($props, $this->domNodeProps);
            if ($obj instanceof DOMDocument) {
                $props = \array_merge($props, $this->domDocumentProps);
            } elseif ($obj instanceof DOMElement) {
                $props = \array_merge($props, $this->domElementProps);
            }
        }
        return $props;
    }

    /**
     * Check if a Dom* class  where properties aren't avail to reflection
     *
     * @param object $obj object to check
     *
     * @return bool
     *
     * @psalm-assert-if-true DOMNode|DOMNodeList $obj
     */
    private function isDomObj($obj)
    {
        return $obj instanceof DOMNode || $obj instanceof DOMNodeList;
    }
}
