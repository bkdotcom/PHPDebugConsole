<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use ReflectionProperty;

/**
 * Get object property info
 */
class AbstractObjectProperties extends AbstractObjectSub
{

    private static $basePropInfo = array(
        'debugInfoExcluded' => false,   // true if not included in __debugInfo
        'desc' => null,                 // from phpDoc
        'inheritedFrom' => null,        // populated only if inherited
        'isStatic' => false,
        'originallyDeclared' => null,   // populated only if originally declared in ancestor
        'overrides' => null,            // previous ancestor where property is defined
                                        //   populated only if we're overriding
        'forceShow' => false,           // initially show the property/value (even if protected or private)
                                        //   if value is an array, expand it
        'type' => null,
        'value' => null,
        'valueFrom' => 'value',         // 'value' | 'debugInfo' | 'debug'
        'visibility' => 'public',       // public, private, protected, magic, magic-read, magic-write, debug
                                        //   may also be an array (ie: ['private', 'magic-read'])
    );

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
     * {@inheritDoc}
     */
    public function onAbstractEnd(Abstraction $abs)
    {
        if ($abs['isTraverseOnly']) {
            return;
        }
        $this->abs = $abs;
        $this->addProperties($abs);
        if ($abs['className'] === 'Closure') {
            $ref = new \ReflectionFunction($abs->getSubject());
            $abs['properties']['file'] = static::buildPropInfo(array(
                'type' => Abstracter::TYPE_STRING,
                'value' => $ref->getFileName(),
                'valueFrom' => 'debug',
                'visibility' => 'debug',
            ));
            $abs['properties']['line'] = static::buildPropInfo(array(
                'type' => Abstracter::TYPE_INT,
                'value' => $ref->getStartLine(),
                'valueFrom' => 'debug',
                'visibility' => 'debug',
            ));
        }
    }

    /**
     * Adds properties to abstraction
     *
     * @param Abstraction $abs Abstraction event object
     *
     * @return void
     */
    private function addProperties(Abstraction $abs)
    {
        $abs = $this->abs;
        $obj = $abs->getSubject();
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
        $this->addPropertiesPhpDoc($abs); // magic properties documented via phpDoc
        if (\is_object($obj)) {
            $this->addPropertiesDom($abs);
            $this->addPropertiesDebug($abs); // use __debugInfo() values if useDebugInfo' && method exists
        }
        $properties = $abs['properties'];
        $abs['hist'][] = $obj;
        foreach ($properties as $name => $info) {
            $absInfo = $this->abstracter->needsAbstraction($info['value']);
            if ($absInfo) {
                $properties[$name]['value'] = $this->abstracter->getAbstraction($info['value'], $abs['debugMethod'], $absInfo, $abs['hist']);
            }
        }
        $abs['properties'] = $properties;
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
        // use var_dump to get the property names
        // get_object_vars() doesn't work
        $iniWas = \ini_set('xdebug.overload_var_dump', '0');
        \ob_start();
        /** @psalm-suppress ForbiddenCode */
        \var_dump($obj);
        $dump = \ob_get_clean();
        $matches = array();
        \ini_set('xdebug.overload_var_dump', $iniWas);
        \preg_match_all('/^\s+\["(.*?)"\]=>\n/sm', $dump, $matches);
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
     * Iterate over PhpDoc's magic properties & add to out abstrction
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
                        'desc' => $phpDocProp['desc'],
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
     * Get property type and description from phpDoc comment
     *
     * @param ReflectionProperty $reflectionProperty reflection property object
     *
     * @return array
     */
    private function getPhpDoc(ReflectionProperty $reflectionProperty)
    {
        $name = $reflectionProperty->name;
        $phpDoc = $this->phpDoc->getParsed($reflectionProperty);
        $info = array(
            'type' => null,
            'desc' => $phpDoc['summary']
                ? $phpDoc['summary']
                : null,
        );
        if (isset($phpDoc['var'])) {
            $var = $phpDoc['var'][0];
            if (\count($phpDoc['var']) > 1) {
                /*
                    php's getDocComment doesn't play nice with compound statements
                    https://www.phpdoc.org/docs/latest/references/phpdoc/tags/var.html
                */
                foreach ($phpDoc['var'] as $var) {
                    if ($var['name'] === $name) {
                        break;
                    }
                }
            }
            $info['type'] = $var['type'];
            if (!$info['desc']) {
                $info['desc'] = $var['desc'];
            } elseif ($var['desc']) {
                $info['desc'] = $info['desc'] . ': ' . $var['desc'];
            }
        }
        return $info;
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
        $reflectionProperty->setAccessible(true); // only accessible via reflection
        $isInstance = \is_object($obj);
        $className = $isInstance
            ? \get_class($obj) // prop->class is equiv to getDeclaringClass
            : $obj;
        // get type and comment from phpdoc
        $phpDoc = $this->getPhpDoc($reflectionProperty);
        /*
            getDeclaringClass returns "LAST-declared/overriden"
        */
        $declaringClassName = $reflectionProperty->getDeclaringClass()->getName();
        $propInfo = static::buildPropInfo(array(
            'desc' => $phpDoc['desc'],
            'inheritedFrom' => $declaringClassName !== $className
                ? $declaringClassName
                : null,
            'isStatic' => $reflectionProperty->isStatic(),
            'type' => $this->resolvePhpDocType($phpDoc['type']),
            'visibility' => $this->getPropVis($reflectionProperty),
        ));
        if ($abs['collectPropertyValues']) {
            $propName = $reflectionProperty->getName();
            if (\array_key_exists($propName, $abs['propertyOverrideValues'])) {
                $value = $abs['propertyOverrideValues'][$propName];
                $propInfo['value'] = $value;
                if (\is_array($value) && \array_intersect_key($value, static::$basePropInfo)) {
                    $propInfo = $value;
                }
                $propInfo['valueFrom'] = 'debug';
            } elseif ($isInstance) {
                $propInfo['value'] = $reflectionProperty->getValue($obj);
            }
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
