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
use bdk\Debug\Abstraction\AbstractObjectHelper;
use Exception;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Get object method info
 */
class AbstractObjectMethods
{

    protected $abstracter;
    protected $helper;

    private static $baseMethodInfo = array(
        'attributes' => array(),
        'implements' => null,
        'inheritedFrom' => null,
        'isAbstract' => false,
        'isDeprecated' => false,
        'isFinal' => false,
        'isStatic' => false,
        'params' => array(),
        'phpDoc' => array(
            'desc' => null,
            'summary' => null,
        ),
        'return' => array(
            'type' => null,
            'desc' => null,
        ),
        'visibility' => 'public',  // public | private | protected | magic
    );

    private static $baseParamInfo = array(
        'attributes' => array(),
        'defaultValue' => Abstracter::UNDEFINED,
        'desc' => null,
        'isOptional' => false,
        'isPromoted' => false,
        'name' => '',
        'type' => null,
    );

    private static $methodCache = array();

    /**
     * Constructor
     *
     * @param Abstracter           $abstracter abstracter instance
     * @param AbstractObjectHelper $helper     helper class
     */
    public function __construct(Abstracter $abstracter, AbstractObjectHelper $helper)
    {
        $this->abstracter = $abstracter;
        $this->helper = $helper;
    }

    /**
     * Add method info to abstraction
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
        if (!($abs['cfgFlags'] & AbstractObject::COLLECT_METHODS)) {
            $this->addMethodsMin();
            return;
        }
        $this->addMethodsFull();
    }

    /**
     * Return method info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildMethodInfo($values = array())
    {
        return \array_merge(static::$baseMethodInfo, $values);
    }

    /**
     * Return method info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildParamInfo($values = array())
    {
        return \array_merge(static::$baseParamInfo, $values);
    }

    /**
     * Adds methods to abstraction
     *
     * @return void
     */
    private function addMethodsFull()
    {
        $abs = $this->abs;
        if ($this->abstracter->getCfg('cacheMethods') && isset(static::$methodCache[$abs['className']])) {
            $abs['methods'] = static::$methodCache[$abs['className']];
            $this->addMethodsFinish();
            return;
        }
        $obj = $abs->getSubject();
        $methodArray = array();
        $methods = $abs['reflector']->getMethods();
        $interfaceMethods = array(
            'ArrayAccess' => array('offsetExists','offsetGet','offsetSet','offsetUnset'),
            'Countable' => array('count'),
            'Iterator' => array('current','key','next','rewind','void'),
            'IteratorAggregate' => array('getIterator'),
        );
        $interfacesHide = \array_intersect($abs['implements'], \array_keys($interfaceMethods));
        foreach ($methods as $reflectionMethod) {
            $info = $this->methodInfo($obj, $reflectionMethod);
            $methodName = $reflectionMethod->getName();
            if ($info['visibility'] === 'private' && $info['inheritedFrom']) {
                /*
                    getMethods() returns parent's private methods (#reasons)..  we'll skip it
                */
                continue;
            }
            foreach ($interfacesHide as $interface) {
                if (\in_array($methodName, $interfaceMethods[$interface])) {
                    // this method implements this interface
                    $info['implements'] = $interface;
                    break;
                }
            }
            $methodArray[$methodName] = $info;
        }
        $abs['methods'] = $methodArray;
        $this->addMethodsPhpDoc();
        if ($abs['className'] !== 'Closure') {
            static::$methodCache[$abs['className']] = $abs['methods'];
        }
        $this->addMethodsFinish();
    }

    /**
     * remove phpDoc[method]
     *
     * @return void
     */
    private function addMethodsFinish()
    {
        $abs = $this->abs;
        unset($abs['phpDoc']['method']);
        if (isset($abs['methods']['__toString'])) {
            $abs['methods']['__toString']['returnValue'] = $this->toString();
        }
        if (!($this->abs['cfgFlags'] & AbstractObject::COLLECT_PHPDOC)) {
            foreach ($abs['methods'] as $name => $method) {
                $method['phpDoc']['desc'] = null;
                $method['phpDoc']['summary'] = null;
                $keys = \array_keys($method['params']);
                foreach ($keys as $key) {
                    $method['params'][$key]['desc'] = null;
                }
                $abs['methods'][$name] = $method;
            }
        }
    }

    /**
     * Add minimal method information to abstraction
     *
     * @return void
     */
    private function addMethodsMin()
    {
        $abs = $this->abs;
        $obj = $abs->getSubject();
        if (\method_exists($obj, '__toString')) {
            $abs['methods']['__toString'] = array(
                'returnValue' => $this->toString(),
                'visibility' => 'public',
            );
        }
        if (\method_exists($obj, '__get')) {
            $abs['methods']['__get'] = array('visibility' => 'public');
        }
        if (\method_exists($obj, '__set')) {
            $abs['methods']['__set'] = array('visibility' => 'public');
        }
    }

    /**
     * "Magic" methods may be defined in a class' doc-block
     * If so... move this information to method info
     *
     * @return void
     *
     * @see http://docs.phpdoc.org/references/phpdoc/tags/method.html
     */
    private function addMethodsPhpDoc()
    {
        $abs = $this->abs;
        $inheritedFrom = null;
        if (empty($abs['phpDoc']['method'])) {
            // phpDoc doesn't contain any @method tags,
            if (\array_intersect_key($abs['methods'], \array_flip(array('__call', '__callStatic')))) {
                // we've got __call and/or __callStatic method:  check if parent classes have @method tags
                $reflector = $abs['reflector'];
                while ($reflector = $reflector->getParentClass()) {
                    $parsed = $this->helper->getPhpDoc($reflector);
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
        $collectPhpDoc = $abs['cfgFlags'] & AbstractObject::COLLECT_PHPDOC;
        foreach ($abs['phpDoc']['method'] as $phpDocMethod) {
            $className = $inheritedFrom ? $inheritedFrom : $abs['className'];
            $abs['methods'][$phpDocMethod['name']] = $this->buildMethodInfo(array(
                'inheritedFrom' => $inheritedFrom,
                'isStatic' => $phpDocMethod['static'],
                'params' => \array_map(function ($phpDocParam) use ($className) {
                    return $this->buildParamInfo(array(
                        'defaultValue' => $this->phpDocParamValue($phpDocParam, $className),
                        'name' => $phpDocParam['name'],
                        'type' => $this->helper->resolvePhpDocType($phpDocParam['type'], $this->abs),
                    ));
                }, $phpDocMethod['param']),
                'phpDoc' => array(
                    'desc' => null,
                    'summary' => $collectPhpDoc
                        ? $phpDocMethod['desc']
                        : null,
                ),
                'return' => array(
                    'desc' => null,
                    'type' => $this->helper->resolvePhpDocType($phpDocMethod['type'], $abs),
                ),
                'visibility' => 'magic',
            ));
        }
    }

    /**
     * This does nothing
     *
     * @return void
     */
    private function devNull()
    {
        \func_get_args();
    }

    /**
     * Get parameter details
     *
     * returns array of
     *     [
     *         'defaultValue'   value or Abstracter::UNDEFINED
     *         'desc'           description (from phpDoc)
     *         'isOptional'
     *         'name'           name
     *         'type'           type hint
     *     ]
     *
     * @param ReflectionMethod $reflectionMethod method object
     * @param array            $phpDoc           method's parsed phpDoc comment
     *
     * @return array
     */
    private function getParams(ReflectionMethod $reflectionMethod, $phpDoc = array())
    {
        $paramArray = array();
        $collectAttributes = $this->abs['cfgFlags'] & AbstractObject::COLLECT_ATTRIBUTES_PARAM;
        \set_error_handler(function () {
            // suppressing "Use of undefined constant STDERR" type notice
            // encountered on
            //    $reflectionParameter->getDefaultValue()
            //    $reflectionParameter->__toString()
        });
        foreach ($reflectionMethod->getParameters() as $i => $reflectionParameter) {
            $phpDocParam = \array_merge(array(
                'desc' => null,
                'name' => '',
                'type' => null,
            ), isset($phpDoc['param'][$i]) ? $phpDoc['param'][$i] : array());
            $paramArray[] = $this->buildParamInfo(array(
                'attributes' => $collectAttributes
                    ? $this->helper->getAttributes($reflectionParameter)
                    : array(),
                'defaultValue' => $this->getParamDefaultVal($reflectionParameter),
                'desc' => $phpDocParam['desc'],
                'isOptional' => $reflectionParameter->isOptional(),
                'isPromoted' =>  PHP_VERSION_ID >= 80000
                    ? $reflectionParameter->isPromoted()
                    : false,
                'name' => $this->getParamName($reflectionParameter, $phpDocParam['name']),
                'type' => $this->getParamTypeHint($reflectionParameter, $phpDocParam['type']),
            ));
        }
        \restore_error_handler();
        /*
            Iterate over params only defined via phpDoc
        */
        $phpDocCount = isset($phpDoc['param'])
            ? \count($phpDoc['param'])
            : 0;
        for ($i = \count($paramArray); $i < $phpDocCount; $i++) {
            $phpDocParam = $phpDoc['param'][$i];
            $name = '$' . $phpDocParam['name'];
            if (\substr($name, -4) === ',...') {
                $name = '...' . \substr($name, 0, -4);
            }
            $paramArray[] = $this->buildParamInfo(array(
                'defaultValue' => $this->phpDocParamValue($phpDocParam),
                'desc' => $phpDocParam['desc'],
                'isOptional' => true,
                'name' => $name,
                'type' => $this->helper->resolvePhpDocType($phpDocParam['type'], $this->abs),
            ));
        }
        return $paramArray;
    }

    /**
     * Get param's default value
     *
     * @param ReflectionParameter $reflectionParameter reflectionParameter
     *
     * @return mixed
     */
    private function getParamDefaultVal(ReflectionParameter $reflectionParameter)
    {
        $defaultValue = Abstracter::UNDEFINED;
        if ($reflectionParameter->isDefaultValueAvailable()) {
            $defaultValue = $reflectionParameter->getDefaultValue();
            if (PHP_VERSION_ID >= 50406 && $reflectionParameter->isDefaultValueConstant()) {
                /*
                    getDefaultValueConstantName() :
                        php may return something like self::CONSTANT_NAME
                        hhvm will return WhateverTheClassNameIs::CONSTANT_NAME
                */
                $defaultValue = new Abstraction(Abstracter::TYPE_CONST, array(
                    'name' => $reflectionParameter->getDefaultValueConstantName(),
                    'value' => $defaultValue,
                ));
            }
        }
        return $defaultValue;
    }

    /**
     * Get Parameter "name"
     *
     * @param ReflectionParameter $reflectionParameter reflectionParameter
     * @param string              $phpDocName          name via phpDoc
     *
     * @return mixed
     */
    private function getParamName(ReflectionParameter $reflectionParameter, $phpDocName)
    {
        $name = '$' . $reflectionParameter->getName();
        if (\method_exists($reflectionParameter, 'isVariadic') && $reflectionParameter->isVariadic()) {
            // php >= 5.6
            $name = '...' . $name;
        } elseif (\substr($phpDocName, -4) === ',...') {
            // phpDoc indicates variadic...
            $name = '...' . $name;
        }
        if ($reflectionParameter->isPassedByReference()) {
            $name = '&' . $name;
        }
        return $name;
    }

    /**
     * Get param typehint
     *
     * @param ReflectionParameter $reflectionParameter reflectionParameter
     * @param string|null         $phpDocType          type via phpDoc
     *
     * @return string|null
     */
    private function getParamTypeHint(ReflectionParameter $reflectionParameter, $phpDocType)
    {
        $matches = array();
        if ($phpDocType !== null) {
            return $this->helper->resolvePhpDocType($phpDocType, $this->abs);
        }
        if (PHP_VERSION_ID >= 70000) {
            return $this->helper->getTypeString($reflectionParameter->getType());
        }
        if ($reflectionParameter->isArray()) {
            // isArray is deprecated in php 8.0
            // isArray is only concerned with type-hint and does not look at default value
            return 'array';
        }
        if (\preg_match('/\[\s<\w+>\s([\w\\\\]+)/s', $reflectionParameter->__toString(), $matches)) {
            // Parameter #0 [ <required> namespace\Type $varName ]
            return $matches[1];
        }
        return null;
    }

    /**
     * Get return type & desc
     *
     * @param ReflectionMethod $reflectionMethod reflectionParameter
     * @param array            $phpDoc           parsed phpDoc param info
     *
     * @return array
     */
    private function getReturn(ReflectionMethod $reflectionMethod, $phpDoc)
    {
        $return = array(
            'desc' => null,
            'type' => null,
        );
        if (!empty($phpDoc['return'])) {
            $return = \array_merge($return, $phpDoc['return']);
            $return['type'] = $this->helper->resolvePhpDocType($return['type'], $this->abs);
            if (!($this->abs['cfgFlags'] & AbstractObject::COLLECT_PHPDOC)) {
                $return['desc'] = null;
            }
        } elseif (PHP_VERSION_ID >= 70000) {
            $return['type'] = $this->helper->getTypeString($reflectionMethod->getReturnType());
        }
        return $return;
    }

    /**
     * Get method info
     *
     * @param object|string    $obj              object (or classname) method belongs to
     * @param ReflectionMethod $reflectionMethod ReflectionMethod instance
     *
     * @return array
     */
    private function methodInfo($obj, ReflectionMethod $reflectionMethod)
    {
        // getDeclaringClass() returns LAST-declared/overridden
        $className = \is_object($obj)
            ? \get_class($obj)
            : $obj;
        $declaringClassName = $reflectionMethod->getDeclaringClass()->getName();
        $phpDoc = $this->helper->getPhpDoc($reflectionMethod);
        \ksort($phpDoc);
        $vis = 'public';
        if ($reflectionMethod->isPrivate()) {
            $vis = 'private';
        } elseif ($reflectionMethod->isProtected()) {
            $vis = 'protected';
        }
        $info = $this->buildMethodInfo(array(
            'attributes' => $this->abs['cfgFlags'] & AbstractObject::COLLECT_ATTRIBUTES_METHOD
                ? $this->helper->getAttributes($reflectionMethod)
                : array(),
            'inheritedFrom' => $declaringClassName !== $className
                ? $declaringClassName
                : null,
            'isAbstract' => $reflectionMethod->isAbstract(),
            'isDeprecated' => $reflectionMethod->isDeprecated() || isset($phpDoc['deprecated']),
            'isFinal' => $reflectionMethod->isFinal(),
            'isStatic' => $reflectionMethod->isStatic(),
            'params' => $this->getParams($reflectionMethod, $phpDoc),
            'phpDoc' => $phpDoc,
            'return' => $this->getReturn($reflectionMethod, $phpDoc),
            'visibility' => $vis,
        ));
        unset($info['phpDoc']['param']);
        unset($info['phpDoc']['return']);
        return $info;
    }

    /**
     * Get defaultValue from phpDoc param
     *
     * Converts the defaultValue string to php scalar
     *
     * @param array  $param     parsed param in from @method tag
     * @param string $className className where phpDoc was found
     *
     * @return mixed
     */
    private function phpDocParamValue($param, $className = null)
    {
        if (!\array_key_exists('defaultValue', $param)) {
            return Abstracter::UNDEFINED;
        }
        $defaultValue = $param['defaultValue'];
        if (\in_array($defaultValue, array('true','false','null'))) {
            return \json_decode($defaultValue);
        }
        if (\is_numeric($defaultValue)) {
            // there are no quotes around value
            return $defaultValue * 1;
        }
        if (\preg_match('/^array\(\s*\)|\[\s*\]$/i', $defaultValue)) {
            // empty array...
            // we're not going to eval non-empty arrays...
            //    non empty array will appear as a string
            return array();
        }
        $matches = array();
        if (\preg_match('/^(self::)?([^\(\)\[\]]+)$/i', $defaultValue, $matches)) {
            // appears to be a constant
            if ($matches[1] && \defined($className . '::' . $matches[2])) {
                // self
                $defaultValue = new Abstraction(Abstracter::TYPE_CONST, array(
                    'name' => $matches[0],
                    'value' => \constant($className . '::' . $matches[2]),
                ));
            } elseif (\defined($defaultValue)) {
                $defaultValue = new Abstraction(Abstracter::TYPE_CONST, array(
                    'name' => $defaultValue,
                    'value' => \constant($defaultValue),
                ));
            }
            return $defaultValue;
        }
        return \trim($defaultValue, '\'"');
    }

    /**
     * Get object's __toString value if method is not deprecated
     *
     * @return string|Abstraction|null
     */
    private function toString()
    {
        $abs = $this->abs;
        // abs['methods']['__toString'] may not exist if via addMethodsMin
        if (!empty($abs['methods']['__toString']['isDeprecated'])) {
            return null;
        }
        $obj = $abs->getSubject();
        if (!\is_object($obj)) {
            return null;
        }
        $val = null;
        try {
            $val = $obj->__toString();
            /** @var Abstraction|string */
            $val = $this->abstracter->crate($val, $abs['debugMethod'], $abs['hist']);
        } catch (Exception $e) {
            // yes, __toString can throw exception..
            // example: SplFileObject->__toString will throw exception if file doesn't exist
            $this->devNull($e);
        }
        return $val;
    }
}
