<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.0.0
 */

namespace bdk\Debug;

use bdk\PubSub\Event;

/**
 * Abstracter:  Methods used to abstract objects
 */
class AbstractObject
{

    static private $basePropInfo = array(
        'desc' => null,
        'inheritedFrom' => null,        // populated only if inherited
        'isStatic' => false,
        'originallyDeclared' => null,   // populated only if originally declared in ancestor
        'overrides' => null,            // populated only if we're overriding
        'type' => null,
        'value' => null,
        'viaDebugInfo' => false,        // true if __debugInfo && __debugInfo value differs
        'visibility' => 'public',
    );
	protected $abstracter;
	protected $phpDoc;

    /**
     * Constructor
     *
     * @param Abstracter $abstracter abstracter obj
     * @param PhpDoc     $phpDoc     phpDoc obj
     */
    public function __construct(Abstracter $abstracter, PhpDoc $phpDoc)
    {
        $this->abstracter = $abstracter;
        $this->phpDoc = $phpDoc;
        $abstracter->eventManager->subscribe('debug.objAbstractStart', array($this, 'onStart'));
        $abstracter->eventManager->subscribe('debug.objAbstractEnd', array($this, 'onEnd'));
    }

    /**
     * returns information about an object
     *
     * @param object $obj  object to inspect
     * @param array  $hist (@internal) array & object history
     *
     * @return array
     */
    public function getAbstraction($obj, &$hist = array())
    {
        $reflector = new \ReflectionObject($obj);
        $abs = new Event($obj, array(
            'className' => get_class($obj),
            'collectMethods' => $this->abstracter->getCfg('collectMethods'),
            'collectPropertyValues' => true,
            'constants' => array(),
            'debug' => $this->abstracter->ABSTRACTION,
            'definition' => array(
                'fileName' => $reflector->getFileName(),
                'startLine' => $reflector->getStartLine(),
                'extensionName' => $reflector->getExtensionName(),
            ),
            'extends' => array(),
            'implements' => $reflector->getInterfaceNames(),
            'isExcluded' => $hist && in_array(get_class($obj), $this->abstracter->getCfg('objectsExclude')),
            'isRecursion' => in_array($obj, $hist, true),
            'methods' => array(),
            'phpDoc' => array(
                'summary' => null,
                'description' => null,
                // additional tags
            ),
            'properties' => array(),
            'scopeClass' => $this->getScopeClass($hist),
            'stringified' => null,
            'type' => 'object',
            'viaDebugInfo' => $this->abstracter->getCfg('useDebugInfo') && method_exists($obj, '__debugInfo'),
        ));
        if (array_filter(array($abs['isRecursion'], $abs['isExcluded']))) {
            return $abs->getValues();
        }
        /*
            debug.objAbstractStart subscriber may
            set isExcluded to true (but not to false)
            set collectPropertyValues (boolean)
            set collectMethods (boolean)
            set stringified
        */
        $abs = $this->abstracter->eventManager->publish('debug.objAbstractStart', $abs);
        if ($abs['isExcluded']) {
            return $abs->getValues();
        }
        $abs['phpDoc'] = $this->phpDoc->getParsed($reflector);
        $abs['constants'] = $this->getConstants($reflector);
        while ($reflector = $reflector->getParentClass()) {
            $abs['extends'][] = $reflector->getName();
        }
        $this->addProperties($abs, $hist);
        if ($abs['collectMethods']) {
            $this->addMethods($abs);
        } else {
            if (method_exists($obj, '__toString')) {
                $abs['methods']['__toString'] = array(
                    'returnValue' => call_user_func(array($obj, '__toString')),
                );
            }
            if (method_exists($obj, '__get')) {
                $abs['methods']['__get'] = true;
            }
            if (method_exists($obj, '__set')) {
                $abs['methods']['__set'] = true;
            }
        }
        /*
            debug.objAbstractEnd subscriber has free reign to modify abtraction array
        */
        $return = $this->abstracter->eventManager->publish('debug.objAbstractEnd', $abs)->getValues();
        $this->sort($return['properties']);
        $this->sort($return['methods']);
        unset($return['collectPropertyValues']);
        return $return;
    }

    /**
     * Special minimal abstraction for array of Traversable's being logged via table()
     * Rather than regular abstraction
     *
     * @param object $obj  Object being inspected
     * @param array  $hist (@internal) Array & object history
     *
     * @return array
     */
    public function getAbstractionTable($obj, &$hist = array())
    {
        $reflector = new \ReflectionObject($obj);
        $abs = new Event($obj, array(
            'className' => get_class($obj),
            'collectMethods' => false,
            'collectPropertyValues' => true,
            'debug' => $this->abstracter->ABSTRACTION,
            'implements' => $reflector->getInterfaceNames(),
            'isExcluded' => $hist && in_array(get_class($obj), $this->abstracter->getCfg('objectsExclude')),
            'isRecursion' => in_array($obj, $hist, true),
            'phpDoc' => $this->phpDoc->getParsed($reflector),
            'properties' => array(),
            'type' => 'object',
            'values' => array(),        // this is unique to getAbstractionTable
                                        //  will be populated if traversable
        ));
        if (is_object($obj) && $obj instanceof \Traversable) {
            $values = array();
            foreach ($obj as $k => $v) {
                $values[$k] = $v;
            }
            $abs['values'] = $values;
        } elseif (is_object($obj)) {
            $this->addProperties($abs, $hist);
        }
        unset($abs['collectPropertyValues']);
        return $abs->getValues();
    }

    /**
     * Get object's constants
     *
     * @param \ReflectionObject $reflector reflectionObject instance
     *
     * @return array
     */
    public function getConstants(\ReflectionObject $reflector)
    {
        if (!$this->abstracter->getCfg('collectConstants')) {
            return array();
        }
        $constants = $reflector->getConstants();
        while ($reflector = $reflector->getParentClass()) {
            $constants = array_merge($reflector->getConstants(), $constants);
        }
        if ($this->abstracter->getCfg('objectSort') == 'name') {
            ksort($constants);
        }
        return $constants;
    }

    /**
     * Adds methods to abstraction
     *
     * @param Event $abs Abstraction event object
     *
     * @return void
     */
    public function addMethods(Event $abs)
    {
        $obj = $abs->getSubject();
        $methodArray = array();
        $reflectionObject = new \ReflectionObject($obj);
        $className = $reflectionObject->getName();
        $methods = $reflectionObject->getMethods();
        $interfaces = $reflectionObject->getInterfaceNames();
        $interfaceMethods = array(
            'ArrayAccess' => array('offsetExists','offsetGet','offsetSet','offsetUnset'),
            'Countable' => array('count'),
            'Iterator' => array('current','key','next','rewind','void'),
            'IteratorAggregate' => array('getIterator'),
            // 'Throwable' => array('getMessage','getCode','getFile','getLine','getTrace','getTraceAsString','getPrevious','__toString'),
        );
        $interfacesHide = array_intersect($interfaces, array_keys($interfaceMethods));
        foreach ($methods as $reflectionMethod) {
            $methodName = $reflectionMethod->getName();
            $vis = 'public';
            if ($reflectionMethod->isPrivate()) {
                $vis = 'private';
            } elseif ($reflectionMethod->isProtected()) {
                $vis = 'protected';
            }
            $phpDoc = $this->phpDoc->getParsed($reflectionMethod);
            // getDeclaringClass() returns LAST-declared/overridden
            $declaringClassName = $reflectionMethod->getDeclaringClass()->getName();
            $info = array(
                'implements' => null,
                'inheritedFrom' => $declaringClassName != $className
                    ? $declaringClassName
                    : null,
                'isAbstract' => $reflectionMethod->isAbstract(),
                'isDeprecated' => $reflectionMethod->isDeprecated() || isset($phpDoc['deprecated']),
                'isFinal' => $reflectionMethod->isFinal(),
                'isStatic' => $reflectionMethod->isStatic(),
                'params' => $this->getParams($reflectionMethod, $phpDoc),
                'phpDoc' => $phpDoc,
                'visibility' => $vis,   // public | private | protected | debug | magic
            );
            if ($info['visibility'] === 'private' && $info['inheritedFrom']) {
                /*
                    getMethods() returns parent's private methods (must be a reason... but we'll skip it)
                */
                continue;
            }
            unset($info['phpDoc']['param']);
            foreach ($interfacesHide as $interface) {
                if (in_array($methodName, $interfaceMethods[$interface])) {
                    $info['implements'] = $interface;
                    break;
                }
            }
            if ($methodName == '__toString') {
                $info['returnValue'] = $reflectionMethod->invoke($obj);
            }
            $methodArray[$methodName] = $info;
        }
        $abs['methods'] = $methodArray;
        $this->addMethodsPhpDoc($abs);
        return;
    }

    /**
     * Adds properties to abstraction
     *
     * @param Event $abs  Abstraction event object
     * @param array $hist (@internal) object history
     *
     * @return void
     */
    public function addProperties(Event $abs, &$hist = array())
    {
        $obj = $abs->getSubject();
        $reflectionObject = new \ReflectionObject($obj);
        /*
            We trace our ancestory to learn where properties are inherited from
        */
        while ($reflectionObject) {
            $className = $reflectionObject->getName();
            $properties = $reflectionObject->getProperties();
            $isDebugObj = $className == __NAMESPACE__;
            while ($properties) {
                $reflectionProperty = array_shift($properties);
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
                if ($isDebugObj && $name == 'data') {
                    $abs['properties']['data'] = array_merge(self::$basePropInfo, array(
                        'value' => array('NOT INSPECTED'),
                        'visibility' => 'protected',
                    ));
                    continue;
                }
                $abs['properties'][$name] = $this->getPropInfo($abs, $reflectionProperty);
            }
            $reflectionObject = $reflectionObject->getParentClass();
        }
        $this->addPropertiesPhpDoc($abs);   // magic properties documented via phpDoc
        $this->addPropertiesDebug($abs);    // use __debugInfo() values if useDebugInfo' && method exists
        $properties = $abs['properties'];
        $hist[] = $obj;
        foreach ($properties as $name => $info) {
            if ($this->abstracter->needsAbstraction($info['value'])) {
                $properties[$name]['value'] = $this->abstracter->getAbstraction($info['value'], $hist);
            }
        }
        $abs['properties'] = $properties;
        return;
    }

    /**
     * debug.objAbstractStart event subscriber
     *
     * @param Event $event event object
     *
     * @return void
     */
    public function onStart(Event $event)
    {
        $obj = $event->getSubject();
        if ($obj instanceof \mysqli && ($obj->connect_errno || !$obj->stat)) {
            // avoid "Property access is not allowed yet"
            $event['collectPropertyValues'] = false;
        }
    }

    /**
     * debug.objAbstractEnd event subscriber
     *
     * @param Event $event event object
     *
     * @return void
     */
    public function onEnd(Event $event)
    {
        $obj = $event->getSubject();
        if ($obj instanceof \DateTimeInterface) {
            $event['stringified'] = $obj->format(\DateTime::ISO8601);
        } elseif ($obj instanceof \DOMNodeList) {
            // for reasons unknown, DOMNodeList's properties are invisible to reflection
            $event['properties']['length'] = array_merge(static::$basePropInfo, array(
                'type' => 'integer',
                'value' => $obj->length,
            ));
        } elseif ($obj instanceof \mysqli && !$event['collectPropertyValues']) {
            $propsAlwaysAvail = array(
                'client_info','client_version','connect_errno','connect_error','errno','error','stat'
            );
            $reflectionObject = new \ReflectionObject($obj);
            foreach ($propsAlwaysAvail as $name) {
                $reflectionProperty = $reflectionObject->getProperty($name);
                $event['properties'][$name]['value'] = $reflectionProperty->getValue($obj);
            }
        }
    }

    /**
     * Get parameter details
     *
     * returns array of
     *     [
     *         'constantName'   populated only if php >= 5.4.6 & default is a constant
     *         'defaultValue'   value or UNDEFINED
     *         'desc'           description (from phpDoc)
     *         'isOptional'
     *         'name'           name
     *         'type'           type hint
     *     ]
     *
     * @param \ReflectionMethod $reflectionMethod method object
     * @param array             $phpDoc           method's parsed phpDoc comment
     *
     * @return array
     */
    protected function getParams(\ReflectionMethod $reflectionMethod, $phpDoc = array())
    {
        $paramArray = array();
        $params = $reflectionMethod->getParameters();
        if (empty($phpDoc)) {
            $phpDoc = $this->phpDoc->getParsed($reflectionMethod);
        }
        foreach ($params as $i => $reflectionParameter) {
            $nameNoPrefix = $reflectionParameter->getName();
            $name = '$'.$nameNoPrefix;
            if (method_exists($reflectionParameter, 'isVariadic') && $reflectionParameter->isVariadic()) {
                $name = '...'.$name;
            }
            if ($reflectionParameter->isPassedByReference()) {
                $name = '&'.$name;
            }
            $constantName = null;
            $defaultValue = $this->abstracter->UNDEFINED;
            if ($reflectionParameter->isDefaultValueAvailable()) {
                $defaultValue = $reflectionParameter->getDefaultValue();
                if (version_compare(PHP_VERSION, '5.4.6', '>=') && $reflectionParameter->isDefaultValueConstant()) {
                    /*
                        php may return something like self::CONSTANT_NAME
                        hhvm will return WhateverTheClassNameIs::CONSTANT_NAME
                    */
                    $constantName = $reflectionParameter->getDefaultValueConstantName();
                }
            }
            $paramInfo = array(
                'constantName' => $constantName,
                'defaultValue' => $defaultValue,
                'desc' => null,
                'isOptional' => $reflectionParameter->isOptional(),
                'name' => $name,
                'type' => $this->getParamTypeHint($reflectionParameter),
            );
            /*
                Incorporate phpDoc info
            */
            if (isset($phpDoc['param'][$i])) {
                $paramInfo['desc'] = $phpDoc['param'][$i]['desc'];
                if (!isset($paramInfo['type'])) {
                    $paramInfo['type'] = $phpDoc['param'][$i]['type'];
                }
            }
            $paramArray[$nameNoPrefix] = $paramInfo;
        }
        return $paramArray;
    }

    /**
     * Get true typehint (not phpDoc typehint)
     *
     * @param \ReflectionParameter $reflectionParameter reflectionParameter
     *
     * @return string|null
     */
    protected function getParamTypeHint(\ReflectionParameter $reflectionParameter)
    {
        $return = null;
        if ($reflectionParameter->isArray()) {
            $return = 'array';
        } elseif (preg_match('/\[\s\<\w+?>\s([\w\\\\]+)/s', $reflectionParameter->__toString(), $matches)) {
            $return = $matches[1];
        }
        return $return;
    }

    /**
     * Get property info
     *
     * @param Event               $abs                Abstraction event object
     * @param \ReflectionProperty $reflectionProperty reflection property
     *
     * @return array
     */
    protected function getPropInfo(Event $abs, \ReflectionProperty $reflectionProperty)
    {
        $obj = $abs->getSubject();
        $reflectionProperty->setAccessible(true); // only accessible via reflection
        $className = get_class($obj); // prop->class is equiv to getDeclaringClass
        // get type and comment from phpdoc
        $commentInfo = $this->getPropCommentInfo($reflectionProperty);
        /*
            getDeclaringClass returns "LAST-declared/overriden"
        */
        $declaringClassName = $reflectionProperty->getDeclaringClass()->getName();
        $propInfo = array_merge(static::$basePropInfo, array(
            'desc' => $commentInfo['desc'],
            'inheritedFrom' => $declaringClassName !== $className
                ? $declaringClassName
                : null,
            'isStatic' => $reflectionProperty->isStatic(),
            'type' => $commentInfo['type'],
        ));
        if ($reflectionProperty->isPrivate()) {
            $propInfo['visibility'] = 'private';
        } elseif ($reflectionProperty->isProtected()) {
            $propInfo['visibility'] = 'protected';
        }
        if ($abs['collectPropertyValues']) {
            $propInfo['value'] = $reflectionProperty->getValue($obj);
        }
        return $propInfo;
    }

    /**
     * Get property type and description from phpDoc comment
     *
     * @param \ReflectionProperty $reflectionProperty reflection property object
     *
     * @return array
     */
    protected function getPropCommentInfo(\ReflectionProperty $reflectionProperty)
    {
        $info = array(
            'type' => null,
            'desc' => null,
        );
        $name = $reflectionProperty->name;
        $phpDoc = $this->phpDoc->getParsed($reflectionProperty);
        if ($phpDoc['summary']) {
            $info['desc'] = $phpDoc['summary'];
        }
        if (isset($phpDoc['var'])) {
            if (count($phpDoc['var']) == 1) {
                $var = $phpDoc['var'][0];
            } else {
                /*
                    php's getDocComment doesn't play nice with compound statements
                    https://www.phpdoc.org/docs/latest/references/phpdoc/tags/var.html
                */
                foreach ($phpDoc['var'] as $var) {
                    if ($var['name'] == $name) {
                        break;
                    }
                }
            }
            $info['type'] = $var['type'];
            $info['desc'] = $info['desc']
                ? $info['desc'].': '.$var['desc']
                : $var['desc'];
        }
        return $info;
    }

    /**
     * for nested objects (ie object is a property of an object), returns the parent class
     *
     * @param array $hist Array & object history
     *
     * @return null|string
     */
    protected function getScopeClass(&$hist)
    {
        $className = null;
        for ($i = count($hist) - 1; $i >= 0; $i--) {
            if (is_object($hist[$i])) {
                $className = get_class($hist[$i]);
                break;
            }
        }
        if ($i < 0) {
            $backtrace = version_compare(PHP_VERSION, '5.4.0', '>=')
                ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
                : debug_backtrace(false);   // don't provide object
            foreach ($backtrace as $i => $frame) {
                if (!isset($frame['class']) || strpos($frame['class'], __NAMESPACE__) !== 0) {
                    break;
                }
            }
            $className = isset($backtrace[$i]['class'])
                ? $backtrace[$i]['class']
                : null;
        }
        return $className;
    }

    /**
     * "Magic" methods may be defined in a class' doc-block
     * If so... move this information to the properties array
     *
     * @param Event $abs Abstraction event object
     *
     * @return void
     *
     * @see http://docs.phpdoc.org/references/phpdoc/tags/method.html
     */
    private function addMethodsPhpDoc(Event $abs)
    {
        $inheritedFrom = null;
        if (empty($abs['phpDoc']['method'])) {
            // phpDoc doesn't contain any @method tags,
            if (array_intersect_key($abs['methods'], array_flip(array('__call', '__callStatic')))) {
                // we've got __call and/or __callStatic method:  check if parent classes have @method tags
                $obj = $abs->getSubject();
                $reflector = new \ReflectionObject($obj);
                while ($reflector = $reflector->getParentClass()) {
                    $parsed = $this->phpDoc->getParsed($reflector);
                    if (isset($parsed['method'])) {
                        $inheritedFrom = $reflector->getName();
                        $abs['phpDoc']['method'] = $parsed['method'];
                        break;
                    }
                }
            }
            if (empty($abs['phpDoc']['method'])) {
                // still empty
                return;
            }
        }
        foreach ($abs['phpDoc']['method'] as $phpDocMethod) {
            $className = $inheritedFrom ? $inheritedFrom : $abs['className'];
            $abs['methods'][$phpDocMethod['name']] = array(
                'implements' => null,
                'inheritedFrom' => $inheritedFrom,
                'isAbstract' => false,
                'isDeprecated' => false,
                'isFinal' => false,
                'isStatic' => $phpDocMethod['static'],
                'params' => array_map(function ($param) use ($className) {
                    $info = $this->phpDocParam($param, $className);
                    return array(
                        'constantName' => $info['constantName'],
                        'defaultValue' => $info['defaultValue'],
                        'desc' => null,
                        'name' => $param['name'],
                        'optional' => false,
                        'type' => $param['type'],
                    );
                }, $phpDocMethod['param']),
                'phpDoc' => array(
                    'summary' => $phpDocMethod['desc'],
                    'description' => null,
                    'return' => array(
                        'type' => $phpDocMethod['type'],
                        'desc' => null,
                    )
                ),
                'visibility' => 'magic',
            );
        }
        unset($abs['phpDoc']['method']);
        return;
    }

    /**
     * Add/Update properties with info from __debugInfo method
     *
     * @param Event $abs Abstraction event object
     *
     * @return void
     */
    private function addPropertiesDebug(Event $abs)
    {
        if (!$abs['viaDebugInfo']) {
            return;
        }
        $obj = $abs->getSubject();
        $debugInfo = call_user_func(array($obj, '__debugInfo'));
        $properties = $abs['properties'];
        foreach ($properties as $name => $info) {
            if (array_key_exists($name, $debugInfo)) {
                if ($debugInfo[$name] !== $info['value']) {
                    $properties[$name]['viaDebugInfo'] = true;
                    $properties[$name]['value'] = $debugInfo[$name];
                }
                unset($debugInfo[$name]);
                continue;
            }
            if (in_array($info['visibility'], array('private','protected')) && $info['inheritedFrom']) {
                // keep the non-public ancestor regardless of inclusion in __debugInfo
                // (this doesn't include "magic" properties, which we don't want to keep)
                continue;
            }
            unset($properties[$name]);
        }
        foreach ($debugInfo as $name => $value) {
            $properties[$name] = array_merge(
                static::$basePropInfo,
                array(
                    'value' => $value,
                    'viaDebugInfo' => true,
                    'visibility' => 'debug',    // indicates this property is exclusive to debugInfo
                )
            );
        }
        $abs['properties'] = $properties;
    }

    /**
     * "Magic" properties may be defined in a class' doc-block
     * If so... move this information to the properties array
     *
     * @param Event $abs Abstraction event object
     *
     * @return void
     *
     * @see http://docs.phpdoc.org/references/phpdoc/tags/property.html
     */
    private function addPropertiesPhpDoc(Event $abs)
    {
        // tag => visibility
        $tags = array(
            'property' => 'magic',
            'property-read' => 'magic-read',
            'property-write' => 'magic-write',
        );
        $inheritedFrom = null;
        if (!array_intersect_key($abs['phpDoc'], $tags)) {
            // phpDoc doesn't contain any @property tags
            $found = false;
            $obj = $abs->getSubject();
            if (!method_exists($obj, '__get')) {
                // don't have magic getter... don't bother searching ancestor phpDocs
                return;
            }
            // we've got __get method:  check if parent classes have @property tags
            $reflector = new \ReflectionObject($obj);
            while ($reflector = $reflector->getParentClass()) {
                $parsed = $this->phpDoc->getParsed($reflector);
                $tagIntersect = array_intersect_key($parsed, $tags);
                if (!$tagIntersect) {
                    continue;
                }
                $found = true;
                $inheritedFrom = $reflector->getName();
                $abs['phpDoc'] = array_merge(
                    $abs['phpDoc'],
                    $tagIntersect
                );
                break;
            }
            if (!$found) {
                return;
            }
        }
        $properties = $abs['properties'];
        foreach ($tags as $tag => $vis) {
            if (!isset($abs['phpDoc'][$tag])) {
                continue;
            }
            foreach ($abs['phpDoc'][$tag] as $phpDocProp) {
                $properties[ $phpDocProp['name'] ] = array_merge(
                    self::$basePropInfo,
                    array(
                        'desc' => $phpDocProp['desc'],
                        'type' => $phpDocProp['type'],
                        'inheritedFrom' => $inheritedFrom,
                        'visibility' => $vis,
                    )
                );
            }
            unset($abs['phpDoc'][$tag]);
        }
        $abs['properties'] = $properties;
        return;
    }

    /**
     * Get defaultValue and constantName info from phpDoc param
     *
     * Converts the defaultValue string to php scalar
     *
     * @param array  $param     parsed param in from @method tag
     * @param string $className className where phpDoc was found
     *
     * @return array
     */
    private function phpDocParam($param, $className)
    {
        $constantName = null;
        $defaultValue = $this->abstracter->UNDEFINED;
        if (array_key_exists('defaultValue', $param)) {
            $defaultValue = $param['defaultValue'];
            if (in_array($defaultValue, array('true','false','null'))) {
                $defaultValue = json_decode($defaultValue);
            } elseif (is_numeric($defaultValue)) {
                // there are no quotes around value
                $defaultValue = $defaultValue * 1;
            } elseif (preg_match('/^array\(\s*\)|\[\s*\]$/i', $defaultValue)) {
                // empty array...
                // we're not going to eval non-empty arrays...
                //    non empty array will appear as a string
                $defaultValue = array();
            } elseif (preg_match('/^(self::)?([^\(\)\[\]]+)$/i', $defaultValue, $matches)) {
                // appears to be a constant
                if ($matches[1]) {
                    // self
                    if (defined($className.'::'.$matches[2])) {
                        $constantName = $matches[0];
                        $defaultValue = constant($className.'::'.$matches[2]);
                    }
                } elseif (defined($defaultValue)) {
                    $constantName = $defaultValue;
                    $defaultValue = constant($defaultValue);
                }
            } else {
                $defaultValue = trim($defaultValue, '\'"');
            }
        }
        return array(
            'constantName' => $constantName,
            'defaultValue' => $defaultValue,
        );
    }

    /**
     * Determine propInfo['overrides'] value
     *
     * @param \ReflectionProperty $reflectionProperty Reflection Property
     * @param array               $propInfo           Property Info
     * @param string              $className          className of object being inspected
     *
     * @return string|null
     */
    private function propOverrides(\ReflectionProperty $reflectionProperty, $propInfo, $className)
    {
        if (empty($propInfo['overrides'])
            && empty($propInfo['inheritedFrom'])
            && $reflectionProperty->getDeclaringClass()->getName() == $className
        ) {
            return $className;
        }
        return null;
    }

    /**
     * Sorts property/method array by visibility or name
     *
     * @param array $array array to sort
     *
     * @return void
     */
    protected function sort(&$array)
    {
        if (!$array) {
            return;
        }
        $sort = $this->abstracter->getCfg('objectSort');
        if ($sort == 'name') {
            // rather than a simple key sort, use array_multisort so that __construct is always first
            $sortData = array();
            foreach (array_keys($array) as $name) {
                $sortData[$name] = $name == '__construct'
                    ? 0
                    : $name;
            }
            array_multisort($sortData, $array);
        } elseif ($sort == 'visibility') {
            $sortVisOrder = array('public', 'protected', 'private', 'magic', 'magic-read', 'magic-write', 'debug');
            $sortData = array();
            foreach ($array as $name => $info) {
                $sortData['name'][$name] = $name == '__construct'
                    ? 0     // always place __construct at the top
                    : $name;
                /*
                    visibility may not be set on methods... if methods weren't collected, but still collected __toString/returnValue
                */
                $sortData['vis'][$name] = isset($info['visibility'])
                    ? array_search($info['visibility'], $sortVisOrder)
                    : count($sortVisOrder);
            }
            array_multisort($sortData['vis'], $sortData['name'], $array);
        }
    }
}
