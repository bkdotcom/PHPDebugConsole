<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
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
        $abs['properties'] = $this->getProperties($abs, $hist);
        if ($abs['collectMethods']) {
            $abs['methods'] = $this->getMethods($abs);
        } elseif (method_exists($obj, '__toString')) {
            $abs['methods'] = array(
                '__toString' => array(
                    'returnValue' => call_user_func(array($obj, '__toString')),
                ),
            );
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
     * @param object $obj  object
     * @param array  $hist (@internal) array & object history
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
            $abs['properties'] = $this->getProperties($abs, $hist);
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
     * Returns array of object's methods
     *
     * @param Event $abs Abstraction event object
     *
     * @return array
     */
    public function getMethods(Event $abs)
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
                'visibility' => $vis,
            );
            if ($info['visibility'] === 'private' && $info['inheritedFrom']) {
                /*
                    odd: getMethods() returns parent's private methods
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
        return $methodArray;
    }

    /**
     * Returns array of objects properties
     *
     * @param Event $abs  Abstraction event object
     * @param array $hist (@internal) object history
     *
     * @return array
     */
    public function getProperties(Event $abs, &$hist = array())
    {
        $obj = $abs->getSubject();
        $hist[] = $obj;
        $propArray = array();
        $reflectionObject = new \ReflectionObject($obj);
        $useDebugInfo = $reflectionObject->hasMethod('__debugInfo') && $this->abstracter->getCfg('useDebugInfo');
        $debugInfo = $useDebugInfo
            ? call_user_func(array($obj, '__debugInfo'))
            : array();
        /*
            We trace our ancestory to learn where properties are inherited from
        */
        $isAncestor = false;
        while ($reflectionObject) {
            $className = $reflectionObject->getName();
            $properties = $reflectionObject->getProperties();
            $objNamespace = substr($className, 0, strrpos($className, '\\'));
            $isDebugObj = $objNamespace == __NAMESPACE__;
            while ($properties) {
                $reflectionProperty = array_shift($properties);
                $name = $reflectionProperty->getName();
                if (isset($propArray[$name])) {
                    // already have info... we're in an ancestor
                    $propArray[$name]['overrides'] = $this->propOverrides(
                        $reflectionProperty,
                        $propArray[$name],
                        $className
                    );
                    $propArray[$name]['originallyDeclared'] = $className;
                    continue;
                } elseif (!($isAncestor && $reflectionProperty->isPrivate())) {
                    // always collect ancestor private props!
                    if ($useDebugInfo && !array_key_exists($name, $debugInfo)) {
                        // this prop isn't returned by __debugInfo, so skip it
                        continue;
                    } elseif ($isDebugObj && in_array($name, array('data','debug','instance'))) {
                        continue;
                    }
                }
                $propInfo = $this->getPropInfo($abs, $reflectionProperty);
                if (array_key_exists($name, $debugInfo)) {
                    // array_key_exists because debug value could be null
                    // ancestor privates (almost certainly) won't exist in debugInfo
                    $debugValue = $debugInfo[$name];
                    $propInfo['viaDebugInfo'] = $debugValue != $propInfo['value'];
                    $propInfo['value'] = $debugValue;
                    unset($debugInfo[$name]);
                }
                if ($this->abstracter->needsAbstraction($propInfo['value'])) {
                    $propInfo['value'] = $this->abstracter->getAbstraction($propInfo['value'], $hist);
                }
                $propArray[$name] = $propInfo;
            }
            $isAncestor = true;
            $reflectionObject = $reflectionObject->getParentClass();
        }
        /*
            Any values left in $debugInfo are only defined via __debugInfo
            $debugInfo now only contains key/value that don't exist as properties
        */
        $propArray = array_merge($propArray, $this->getPropertiesDebug($debugInfo, $hist));
        return $propArray;
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
        if (empty($phpDoc['param'])) {
            $phpDoc['param'] = array();
        }
        foreach ($params as $reflectionParameter) {
            $hasDefaultValue = $reflectionParameter->isDefaultValueAvailable();
            $name = '$'.$reflectionParameter->getName();
            if (method_exists($reflectionParameter, 'isVariadic') && $reflectionParameter->isVariadic()) {
                $name = '...'.$name;
            }
            if ($reflectionParameter->isPassedByReference()) {
                $name = '&'.$name;
            }
            $paramArray[$reflectionParameter->getName()] = array(
                'defaultValue' =>  $hasDefaultValue
                    ? $reflectionParameter->getDefaultValue()
                    : $this->abstracter->UNDEFINED,
                'desc' => null,
                'name' => $name,
                'optional' => $reflectionParameter->isOptional(),
                /*
                    Try to get param type from reflectionParameter
                    If unsuccessfull, the, type specified in phpDoc will end up getting used
                */
                'type' => $this->getParamTypeHint($reflectionParameter),
            );
        }
        $paramKeys = array_keys($paramArray);
        foreach ($phpDoc['param'] as $i => $phpDocParam) {
            $name = $phpDocParam['name'];
            if ($name === null && isset($paramKeys[$i])) {
                $name = $paramKeys[$i];
            }
            if (isset($paramArray[$name])) {
                $param = &$paramArray[$name];
                if (!isset($param['type'])) {
                    $param['type'] = $phpDocParam['type'];
                }
                $param['desc'] = $phpDocParam['desc'];
            }
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
     * Returns array of propInfo for debugInfo values
     *
     * @param array $debugInfo key/values as returned by __debugInfo()
     * @param array $hist      {@internal}
     *
     * @return array
     */
    protected function getPropertiesDebug($debugInfo, $hist = array())
    {
        $propArray = array();
        foreach ($debugInfo as $name => $value) {
            $propArray[$name] = array_merge(static::$basePropInfo, array(
                'value' => $this->abstracter->needsAbstraction($value)
                    ? $this->abstracter->getAbstraction($value, $hist)
                    : $value,
                'viaDebugInfo' => true,
                'visibility' => 'debug',
            ));
        }
        return $propArray;
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
     * @param array $hist (@internal) array & object history
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
     * determine propInfo['overrides'] value
     *
     * @param \ReflectionProperty $reflectionProperty Reflection Property
     * @param array               $propInfo           Property Info
     * @param string              $className          classname of object being inspected
     *
     * @return string|null
     */
    protected function propOverrides(\ReflectionProperty $reflectionProperty, $propInfo, $className)
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
        if ($array) {
            $sort = $this->abstracter->getCfg('objectSort');
            if ($sort == 'name') {
                ksort($array);
            } elseif ($sort == 'visibility') {
                $sortVisOrder = array('public', 'protected', 'private', 'debug');
                $sortData = array();
                foreach ($array as $name => $info) {
                    $sortData['name'][$name] = strtolower($name);
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
}
