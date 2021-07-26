<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use ReflectionProperty;
use Reflector;

/**
 * Get object property info
 */
class AbstractObjectProperties extends AbstractObjectSub
{

    private static $basePropInfo = array(
        'attributes' => array(),
        'debugInfoExcluded' => false,   // true if not included in __debugInfo
        'desc' => null,                 // from phpDoc
        'inheritedFrom' => null,        // populated only if inherited
                                        //   not populated if extended/redefined
        'isPromoted' => false,
        'isStatic' => false,
        'originallyDeclared' => null,   // populated only if originally declared in ancestor
        'overrides' => null,            // previous ancestor where property is defined
                                        //   populated only if we're overriding
        'forceShow' => false,           // initially show the property/value (even if protected or private)
                                        //   if value is an array, expand it
        'type' => null,
        'value' => Abstracter::UNDEFINED,
        'valueFrom' => 'value',         // 'value' | 'debugInfo' | 'debug'
        'visibility' => 'public',       // public, private, protected, magic, magic-read, magic-write, debug
                                        //   may also be an array (ie: ['private', 'magic-read'])
    );

    /**
     * Add property info/values to abstraction
     *
     * @param Abstraction $abs Object abstraction
     *
     * @return void
     */
    public function add(Abstraction $abs)
    {
        if ($abs['isTraverseOnly']) {
            return;
        }
        $this->abs = $abs;
        $this->addPropertiesBase($abs);
        $this->addPropertiesPhpDoc($abs); // magic properties documented via phpDoc
        $obj = $abs->getSubject();
        if (\is_object($obj)) {
            $this->addPropertiesDom($abs);
            $this->addPropertiesDebug($abs); // use __debugInfo() values if useDebugInfo' && method exists
            if ($abs['className'] === 'Closure') {
                $this->addPropertiesClosure($abs);
            }
        }
        $this->crate($abs);
    }

    /**
     * Return property info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildPropInfo($values = array())
    {
        return \array_merge(static::$basePropInfo, $values);
    }

    /**
     * Get type and description from phpDoc comment for Constant or Property
     *
     * @param Reflector $reflector ReflectionProperty or ReflectionClassConstant property object
     *
     * @return array
     */
    public function getVarPhpDoc(Reflector $reflector)
    {
        $refObj = new \ReflectionObject($reflector);
        if ($refObj->isInterface()) {
            return array(
                'type' => null,
                'desc' => null,
            );
        }
        /** @psalm-suppress NoInterfaceProperties */
        $name = $reflector->name;
        $phpDoc = $this->phpDoc->getParsed($reflector);
        $info = array(
            'type' => null,
            'desc' => $phpDoc['summary'],
        );
        if (!isset($phpDoc['var'])) {
            return $info;
        }
        /*
            php's getDocComment doesn't play nice with compound statements
            https://www.phpdoc.org/docs/latest/references/phpdoc/tags/var.html
        */
        $var = array();
        foreach ($phpDoc['var'] as $var) {
            if ($var['name'] === $name) {
                break;
            }
        }
        $info['type'] = $var['type'];
        if (!$info['desc']) {
            $info['desc'] = $var['desc'];
        } elseif ($var['desc']) {
            $info['desc'] = $info['desc'] . ': ' . $var['desc'];
        }
        return $info;
    }

    /**
     * Adds properties (via reflection) to abstraction
     *
     * @param Abstraction $abs Abstraction event object
     *
     * @return void
     */
    private function addPropertiesBase(Abstraction $abs)
    {
        $reflectionObject = $abs['reflector'];
        /*
            We trace our ancestory to learn where properties are inherited from
        */
        while ($reflectionObject) {
            $className = $reflectionObject->getName();
            $properties = $reflectionObject->getProperties();
            while ($properties) {
                $reflectionProperty = \array_shift($properties);
                $name = $reflectionProperty->getName();
                if (isset($abs['properties'][$name])) {
                    // already have info... we're in an ancestor
                    $abs['properties'][$name]['overrides'] = $this->propOverrides(
                        $reflectionProperty,
                        $abs['properties'][$name],
                        $className
                    );
                    $abs['properties'][$name]['originallyDeclared'] = $className;
                    continue;
                }
                $abs['properties'][$name] = $this->getPropInfo($abs, $reflectionProperty);
            }
            $reflectionObject = $reflectionObject->getParentClass();
        }
    }

    /**
     * Add file & line debug properties for Closure
     *
     * @param Abstraction $abs Abstraction event object
     *
     * @return void
     */
    private function addPropertiesClosure(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        $ref = new \ReflectionFunction($obj);
        $abs['properties']['debug.file'] = static::buildPropInfo(array(
            'type' => Abstracter::TYPE_STRING,
            'value' => $ref->getFileName(),
            'valueFrom' => 'debug',
            'visibility' => 'debug',
        ));
        $abs['properties']['debug.line'] = static::buildPropInfo(array(
            'type' => Abstracter::TYPE_INT,
            'value' => $ref->getStartLine(),
            'valueFrom' => 'debug',
            'visibility' => 'debug',
        ));
    }

    /**
     * Add/Update properties with info from __debugInfo method
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function addPropertiesDebug(Abstraction $abs)
    {
        if (!$abs['collectPropertyValues']) {
            return;
        }
        if (!$abs['viaDebugInfo']) {
            // using __debugInfo is disabled, or object does not have __debugInfo method
            return;
        }
        $obj = $abs->getSubject();
        // temporarily store __debugInfo values in abstraction
        $abs['debugInfo'] = \call_user_func(array($obj, '__debugInfo'));
        $properties = $this->addPropertiesDebugWalk($abs);
        /*
            What remains in debugInfo are __debugInfo only values
        */
        foreach ($abs['debugInfo'] as $name => $value) {
            $properties[$name] = static::buildPropInfo(array(
                'value' => $value,
                'valueFrom' => 'debugInfo',
                'visibility' => 'debug',    // indicates this "property" is exclusive to debugInfo
            ));
        }
        $abs['properties'] = $properties;
        unset($abs['debugInfo']);
    }

    /**
     * Iterate over properties to set value & valueFrom
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return array
     */
    private function addPropertiesDebugWalk(Abstraction $abs)
    {
        $debugInfo = $abs['debugInfo'];
        $properties = $abs['properties'];
        foreach ($properties as $name => $info) {
            if (\array_key_exists($name, $abs['propertyOverrideValues'])) {
                // we're using override value
                unset($debugInfo[$name]);
                continue;
            }
            if (\array_key_exists($name, $debugInfo)) {
                if ($debugInfo[$name] !== $info['value']) {
                    $properties[$name]['value'] = $debugInfo[$name];
                    $properties[$name]['valueFrom'] = 'debugInfo';
                }
                unset($debugInfo[$name]);
                continue;
            }
            $isPrivateAncestor = \in_array('private', (array) $info['visibility']) && $info['inheritedFrom'];
            if ($isPrivateAncestor) {
                // exempt from isExcluded
                continue;
            }
            $properties[$name]['debugInfoExcluded'] = true;
        }
        $abs['debugInfo'] = $debugInfo;
        return $properties;
    }

    /**
     * Add properties to Dom* abstraction
     *
     * DOM* properties are invisible to reflection
     * https://bugs.php.net/bug.php?id=48527
     *
     * @param Abstraction $abs Abstraction event object
     *
     * @return void
     */
    private function addPropertiesDom(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        if ($abs['properties']) {
            return;
        }
        if (!$this->isDomObj($obj)) {
            return;
        }
        /*
            use print_r to get the property names
            get_object_vars() doesn't work
            var_dump may be overridden by xdebug...  and if xdebug v3 unable to disable at runtime
        */
        $dump = \print_r($obj, true);
        $matches = array();
        \preg_match_all('/^\s+\[(.+?)\] => /m', $dump, $matches);
        $props = \array_fill_keys($matches[1], null);

        if ($obj instanceof \DOMNode) {
            $props = \array_merge($props, array(
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
            ));
            if ($obj instanceof \DOMDocument) {
                $props = \array_merge($props, array(
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
                ));
            } elseif ($obj instanceof \DOMElement) {
                $props = \array_merge($props, array(
                    'schemaTypeInfo' => 'bool',
                    'tagName' => 'string',
                ));
            }
        }
        foreach ($props as $propName => $type) {
            $val = $obj->{$propName};
            if (!$type) {
                // function array dereferencing = php 5.4
                $type = $this->abstracter->getType($val)[0];
            }
            $abs['properties'][$propName] = static::buildPropInfo(array(
                'type' => $type,
                'value' => \is_object($val)
                    ? Abstracter::NOT_INSPECTED
                    : $val,
            ));
        }
    }

    /**
     * "Magic" properties may be defined in a class' doc-block
     * If so... move this information to the properties array
     *
     * @param Abstraction $abs Abstraction event object
     *
     * @return void
     *
     * @see http://docs.phpdoc.org/references/phpdoc/tags/property.html
     */
    private function addPropertiesPhpDoc(Abstraction $abs)
    {
        // tag => visibility
        $tags = array(
            'property' => 'magic',
            'property-read' => 'magic-read',
            'property-write' => 'magic-write',
        );
        $inheritedFrom = null;
        if (!\array_intersect_key($abs['phpDoc'], $tags)) {
            // phpDoc doesn't contain any @property tags
            $found = false;
            $obj = $abs->getSubject();
            if (!\method_exists($obj, '__get')) {
                // don't have magic getter... don't bother searching ancestor phpDocs
                return;
            }
            // we've got __get method:  check if parent classes have @property tags
            $reflector = $abs['reflector'];
            while ($reflector = $reflector->getParentClass()) {
                $parsed = $this->phpDoc->getParsed($reflector);
                $tagIntersect = \array_intersect_key($parsed, $tags);
                if (!$tagIntersect) {
                    continue;
                }
                $found = true;
                $inheritedFrom = $reflector->getName();
                $abs['phpDoc'] = \array_merge(
                    $abs['phpDoc'],
                    $tagIntersect
                );
                break;
            }
            if (!$found) {
                return;
            }
        }
        $this->addPropertiesPhpDocIter($abs, $inheritedFrom);
    }

    /**
     * Iterate over PhpDoc's magic properties & add to abstrction
     *
     * @param Abstraction $abs           Abstraction event object
     * @param string|null $inheritedFrom Where the magic properties were found
     *
     * @return void
     */
    private function addPropertiesPhpDocIter(Abstraction $abs, $inheritedFrom)
    {
        // tag => visibility
        $tags = array(
            'property' => 'magic',
            'property-read' => 'magic-read',
            'property-write' => 'magic-write',
        );
        $properties = $abs['properties'];
        $collectPhpDoc = $abs['flags'] & AbstractObject::COLLECT_PHPDOC;
        foreach ($tags as $tag => $vis) {
            if (!isset($abs['phpDoc'][$tag])) {
                continue;
            }
            foreach ($abs['phpDoc'][$tag] as $phpDocProp) {
                $exists = isset($properties[ $phpDocProp['name'] ]);
                $properties[ $phpDocProp['name'] ] = \array_merge(
                    $exists
                        ? $properties[ $phpDocProp['name'] ]
                        : self::$basePropInfo,
                    array(
                        'desc' => $collectPhpDoc
                            ? $phpDocProp['desc']
                            : null,
                        'type' => $this->resolvePhpDocType($phpDocProp['type']),
                        'inheritedFrom' => $inheritedFrom,
                        'visibility' => $exists
                            ? array($properties[ $phpDocProp['name'] ]['visibility'], $vis)
                            : $vis,
                    )
                );
                if ($exists === false) {
                    $properties[ $phpDocProp['name'] ]['value'] = Abstracter::UNDEFINED;
                }
            }
            unset($abs['phpDoc'][$tag]);
        }
        $abs['properties'] = $properties;
    }

    /**
     * "Crate" property values
     *
     * @param Abstraction $abs Abstraction event object
     *
     * @return void
     */
    private function crate(Abstraction $abs)
    {
        $abs['hist'][] = $abs->getSubject();
        $properties = $this->abs['properties'];
        foreach ($properties as $name => $info) {
            $properties[$name]['value'] = $this->abstracter->crate($info['value'], $abs['debugMethod'], $abs['hist']);
        }
        $abs['properties'] = $properties;
    }

    /**
     * Get property info
     *
     * @param Abstraction        $abs                Abstraction event object
     * @param ReflectionProperty $reflectionProperty ReflectionProperty instance
     *
     * @return array
     */
    private function getPropInfo(Abstraction $abs, ReflectionProperty $reflectionProperty)
    {
        $obj = $abs->getSubject();
        $isInstance = \is_object($obj);
        $className = $isInstance
            ? \get_class($obj) // prop->class is equiv to getDeclaringClass
            : $obj;
        $reflectionProperty->setAccessible(true); // only accessible via reflection
        // get type and desc from phpdoc
        $phpDoc = $this->getVarPhpDoc($reflectionProperty);
        /*
            getDeclaringClass returns "LAST-declared/overriden"
        */
        $declaringClassName = $reflectionProperty->getDeclaringClass()->getName();
        $propInfo = static::buildPropInfo(array(
            'attributes' => $abs['flags'] & AbstractObject::COLLECT_ATTRIBUTES_PROP
                ? $this->getAttributes($reflectionProperty)
                : array(),
            'desc' => $abs['flags'] & AbstractObject::COLLECT_PHPDOC
                ? $phpDoc['desc']
                : null,
            'inheritedFrom' => $declaringClassName !== $className
                ? $declaringClassName
                : null,
            'isPromoted' =>  PHP_VERSION_ID >= 80000
                ? $reflectionProperty->isPromoted()
                : false,
            'isStatic' => $reflectionProperty->isStatic(),
            'type' => $this->getPropType($phpDoc['type'], $reflectionProperty),
            'visibility' => $this->getPropVis($reflectionProperty),
        ));
        if ($abs['collectPropertyValues']) {
            $propInfo = $this->getPropValue($propInfo, $abs, $reflectionProperty);
        }
        return $propInfo;
    }

    /**
     * Get Property's type
     * Priority given to phpDoc type, followed by declared type (PHP 7.4)
     *
     * @param string             $phpDocType         type specified in phpDoc block
     * @param ReflectionProperty $reflectionProperty ReflectionProperty instance
     *
     * @return string|null
     */
    private function getPropType($phpDocType, ReflectionProperty $reflectionProperty)
    {
        $type = $this->resolvePhpDocType($phpDocType);
        if ($type !== null) {
            return $type;
        }
        return PHP_VERSION_ID >= 70400
            ? $this->getTypeString($reflectionProperty->getType())
            : null;
    }

    /**
     * Set 'value' and 'valueFrom' values
     *
     * @param array              $propInfo           propInfo array
     * @param Abstraction        $abs                Abstraction event object
     * @param ReflectionProperty $reflectionProperty ReflectionProperty
     *
     * @return array updated propInfo
     */
    private function getPropValue($propInfo, Abstraction $abs, ReflectionProperty $reflectionProperty)
    {
        $propName = $reflectionProperty->getName();
        if (\array_key_exists($propName, $abs['propertyOverrideValues'])) {
            $value = $abs['propertyOverrideValues'][$propName];
            $propInfo['value'] = $value;
            if (\is_array($value) && \array_intersect_key($value, static::$basePropInfo)) {
                $propInfo = $value;
            }
            $propInfo['valueFrom'] = 'debug';
            return $propInfo;
        }
        $obj = $abs->getSubject();
        $isInstance = \is_object($obj);
        if ($isInstance) {
            $isInitialized = PHP_VERSION_ID < 70400 || $reflectionProperty->isInitialized($obj);
            $propInfo['value'] = $isInitialized
                ? $reflectionProperty->getValue($obj)
                : Abstracter::UNDEFINED;  // value won't be displayed
        }
        return $propInfo;
    }

    /**
     * Get property visibility
     *
     * @param ReflectionProperty $reflectionProperty ReflectionProperty instance
     *
     * @return 'public'|'private'|'protected'
     */
    private function getPropVis(ReflectionProperty $reflectionProperty)
    {
        if ($reflectionProperty->isPrivate()) {
            return 'private';
        }
        if ($reflectionProperty->isProtected()) {
            return 'protected';
        }
        return 'public';
    }

    /**
     * Check if a Dom* class  where properties aren't avail to reflection
     *
     * @param object $obj object to check
     *
     * @return bool
     */
    private function isDomObj($obj)
    {
        return $obj instanceof \DOMNode || $obj instanceof \DOMNodeList;
    }

    /**
     * Determine propInfo['overrides'] value
     *
     * This is the classname of previous ancestor where property is defined
     *
     * @param ReflectionProperty $reflectionProperty Reflection Property
     * @param array              $propInfo           Property Info
     * @param string             $className          className of object being inspected
     *
     * @return string|null
     */
    private function propOverrides(ReflectionProperty $reflectionProperty, $propInfo, $className)
    {
        if (
            empty($propInfo['overrides'])
            && empty($propInfo['inheritedFrom'])
            && $reflectionProperty->getDeclaringClass()->getName() === $className
        ) {
            return $className;
        }
        return null;
    }
}
