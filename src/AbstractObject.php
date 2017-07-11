<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/**
 * Abstracter:  Methods used to abstract objects
 */
class AbstractObject
{

    static private $basePropInfo = array(
        'visibility' => 'public',
        'isStatic' => false,
        'value' => null,
        'type' => null,
        'desc' => null,
        'inheritedFrom' => null,        // populated only if inherited
        'overrides' => null,            // populated only if we're overriding
        'originallyDeclared' => null,   // populated only if originally declared in ancestor
        'viaDebugInfo' => false,        // true if __debugInfo && __debugInfo value differs
    );
	protected $abstracter;
	protected $phpDoc;

    /**
     * Constructor
     *
     * @param object $abstracter abstracter obj
     * @param objedt $phpDoc     phpDoc obj
     */
    public function __construct($abstracter, $phpDoc)
    {
        $this->abstracter = $abstracter;
        $this->phpDoc = $phpDoc;
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
        $return = array(
            'debug' => $this->abstracter->ABSTRACTION,
            'type' => 'object',
            'excluded' => $hist && in_array(get_class($obj), $this->abstracter->getCfg('objectsExclude')),
            'collectMethods' => $this->abstracter->getCfg('collectMethods'),
            'viaDebugInfo' => $this->abstracter->getCfg('useDebugInfo') && method_exists($obj, '__debugInfo'),
            'isRecursion' => in_array($obj, $hist, true),
            'className' => get_class($obj),
            'extends' => array(),
            'implements' => $reflector->getInterfaceNames(),
            'constants' => array(),
            'properties' => array(),
            'methods' => array(),
            'scopeClass' => $this->getScopeClass($hist),
            'stringified' => null,
            'phpDoc' => array(
                'summary' => null,
                'description' => null,
                // additional tags
            ),
        );
        if (!$return['isRecursion'] && !$return['excluded']) {
            $collectConstants = $this->abstracter->getCfg('collectConstants');
            $return['phpDoc'] = $this->phpDoc->getParsed($reflector);
            $return['properties'] = $this->getProperties($obj, $hist);
            if ($collectConstants) {
                $return['constants'] = $reflector->getConstants();
            }
            while ($reflector = $reflector->getParentClass()) {
                $return['extends'][] = $reflector->getName();
                if ($collectConstants) {
                    $return['constants'] = array_merge($reflector->getConstants(), $return['constants']);
                }
            }
            if ($this->abstracter->getCfg('objectSort') == 'name') {
                ksort($return['constants']);
            }
            if ($return['collectMethods']) {
                $return['methods'] = $this->getMethods($obj);
            } elseif (method_exists($obj, '__toString')) {
                $return['methods'] = array(
                    '__toString' => array(
                        'returnValue' => call_user_func(array($obj, '__toString')),
                    ),
                );
            }
            if ($obj instanceof \DateTimeInterface) {
                $return['stringified'] = $obj->format(\DateTime::ISO8601);
            }
        }
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
        $return = array(
            'debug' => $this->abstracter->ABSTRACTION,
            'type' => 'object',
            'className' => get_class($obj),
            'implements' => $reflector->getInterfaceNames(),
            'properties' => array(),
            'phpDoc' => $this->phpDoc->getParsed($reflector),
            'values' => array(),        // this is unique to getAbstractionTable
                                        //  will be populated if traversable
        );
        if (is_object($obj) && $obj instanceof \Traversable) {
            foreach ($obj as $k => $v) {
                $return['values'][$k] = $v;
            }
        } elseif (is_object($obj)) {
            $return['properties'] = $this->getProperties($obj, $hist);
        }
        return $return;
    }

    /**
     * Returns array of objects methods
     *
     * @param object $obj object
     *
     * @return array
     */
    public function getMethods($obj)
    {
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
                'visibility' => $vis,
                'inheritedFrom' => $declaringClassName != $className
                    ? $declaringClassName
                    : null,
                'isAbstract' => $reflectionMethod->isAbstract(),
                'isDeprecated' => $reflectionMethod->isDeprecated() || isset($phpDoc['deprecated']),
                'isFinal' => $reflectionMethod->isFinal(),
                'isStatic' => $reflectionMethod->isStatic(),
                'params' => $this->getParams($reflectionMethod, $phpDoc),
                'implements' => null,
                'phpDoc' => $phpDoc,
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
        $this->sort($methodArray);
        return $methodArray;
    }

    /**
     * Get parameter details
     *
     * @param \ReflectionMethod $reflectionMethod method object
     * @param array             $phpDoc           method's parsed phpDoc comment
     *
     * @return array
     */
    public function getParams(\ReflectionMethod $reflectionMethod, $phpDoc = array())
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
                'name' => $name,
                'optional' => $reflectionParameter->isOptional(),
                'defaultValue' =>  $hasDefaultValue
                    ? $reflectionParameter->getDefaultValue()
                    : $this->abstracter->UNDEFINED,
                /*
                    Try to get param type from reflectionParameter
                    If unsuccessfull, the, type specified in phpDoc will end up getting used
                */
                'type' => $this->getParamTypeHint($reflectionParameter),
                'desc' => null,
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
    public function getParamTypeHint(\ReflectionParameter $reflectionParameter)
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
     * Returns array of objects properties
     *
     * @param object $obj  object
     * @param array  $hist (@internal) object history
     *
     * @return array
     */
    public function getProperties($obj, &$hist = array())
    {
        $hist[] = $obj;
        $propArray = array();
        $reflectionObject = new \ReflectionObject($obj);
        $useDebugInfo = $reflectionObject->hasMethod('__debugInfo') && $this->abstracter->getCfg('useDebugInfo');
        $debugInfo = $useDebugInfo
            ? call_user_func(array($obj, '__debugInfo'))
            : array();
        if ($obj instanceof \DOMNodeList) {
            // for reasons unknown, DOMNodeList's properties are invisible to reflection
            $propArray['length'] = array_merge(static::$basePropInfo, array(
                'type' => 'integer',
                'value' => $obj->length,
            ));
        }
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
                    if (empty($propArray[$name]['overrides'])
                        && empty($propArray[$name]['inheritedFrom'])
                        && $reflectionProperty->getDeclaringClass()->getName() == $className
                    ) {
                        $propArray[$name]['overrides'] = $className;
                    }
                    $propArray[$name]['originallyDeclared'] = $className;
                    continue;
                } elseif ($useDebugInfo && !array_key_exists($name, $debugInfo)) {
                    // useDebugInfo option && obj has __debugInfo() method && this prop isn't returned by __debugInfo()
                    // we'll still grab private ancestors
                    if (!($isAncestor && $reflectionProperty->isPrivate())) {
                        continue;
                    }
                } elseif ($isDebugObj && in_array($name, array('data','debug','instance'))) {
                    continue;
                }
                $propInfo = $this->getPropInfo($obj, $reflectionProperty);
                if ($useDebugInfo && array_key_exists($name, $debugInfo)) {
                    // ancestor privates won't exist in debugInfo
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
        */
        foreach ($debugInfo as $name => $value) {
            if ($this->abstracter->needsAbstraction($value)) {
                $value = $this->abstracter->getAbstraction($value, $hist);
            }
            $propArray[$name] = array_merge(static::$basePropInfo, array(
                'visibility' => 'debug',
                'value' => $value,
                'viaDebugInfo' => true,
            ));
        }
        $this->sort($propArray);
        return $propArray;
    }

    /**
     * Get property info
     *
     * @param object              $obj                object
     * @param \ReflectionProperty $reflectionProperty reflection property
     *
     * @return array
     */
    protected function getPropInfo($obj, \ReflectionProperty $reflectionProperty)
    {
        $reflectionProperty->setAccessible(true); // only accessible via reflection
        $name = $reflectionProperty->name;
        $className = get_class($obj); // prop->class is equiv to getDeclaringClass
        // get type and comment from phpdoc
        $commentInfo = $this->getPropCommentInfo($reflectionProperty);
        /*
            getDeclaringClass returns "LAST-declared/overriden"
        */
        $declaringClassName = $reflectionProperty->getDeclaringClass()->getName();
        $propInfo = array_merge(static::$basePropInfo, array(
            'isStatic' => $reflectionProperty->isStatic(),
            'type' => $commentInfo['type'],
            'desc' => $commentInfo['desc'],
            'inheritedFrom' => $declaringClassName !== $className
                ? $declaringClassName
                : null,
        ));
        if ($reflectionProperty->isPrivate()) {
            $propInfo['visibility'] = 'private';
        } elseif ($reflectionProperty->isProtected()) {
            $propInfo['visibility'] = 'protected';
        }
        if ($className == 'mysqli' && $obj->connect_errno) {
            // avoid "Property access is not allowed yet"
            $propsAlwaysAvail = array('client_info','client_version','connect_errno','connect_error','errno','error','stat');
            $value = in_array($name, $propsAlwaysAvail)
                ? $reflectionProperty->getValue($obj)
                : null;
        } else {
            $value = $reflectionProperty->getValue($obj);
        }
        $propInfo['value'] = $value;
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
                    $sortData['vis'][$name] = array_search($info['visibility'], $sortVisOrder);
                }
                array_multisort($sortData['vis'], $sortData['name'], $array);
            }
        }
    }
}
