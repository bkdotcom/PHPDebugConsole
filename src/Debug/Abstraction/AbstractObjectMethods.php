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
     * @param Abstraction $abs Object Abstraction instance
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
     * Adds methods to abstraction
     *
     * @return void
     */
    private function addMethodsFull()
    {
        $abs = $this->abs;
        if ($this->abstracter->getCfg('cacheMethods') && isset(static::$methodCache[$abs['className']])) {
            $abs['methods'] = static::$methodCache[$abs['className']];
            $this->addFinish();
            return;
        }
        $this->addViaReflection();
        $this->addViaPhpDoc();
        $this->addImplements();
        if ($abs['className'] !== 'Closure') {
            static::$methodCache[$abs['className']] = $abs['methods'];
        }
        $this->addFinish();
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
     * remove phpDoc[method]
     *
     * @return void
     */
    private function addFinish()
    {
        $abs = $this->abs;
        unset($abs['phpDoc']['method']);
        if (isset($abs['methods']['__toString'])) {
            $abs['methods']['__toString']['returnValue'] = $this->toString();
        }
        $collectPhpDoc = $this->abs['cfgFlags'] & AbstractObject::COLLECT_PHPDOC;
        if ($collectPhpDoc) {
            return;
        }
        // remove PhpDoc desc and summary
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

    /**
     * Add `implements` value to common interface methods
     *
     * @return void
     */
    private function addImplements()
    {
        $abs = $this->abs;
        $interfaceMethods = array(
            'ArrayAccess' => array('offsetExists','offsetGet','offsetSet','offsetUnset'),
            'Countable' => array('count'),
            'Iterator' => array('current','key','next','rewind','void'),
            'IteratorAggregate' => array('getIterator'),
        );
        $interfaces = \array_intersect($abs['implements'], \array_keys($interfaceMethods));
        foreach ($interfaces as $interface) {
            foreach ($interfaceMethods[$interface] as $methodName) {
                // this method implements this interface
                $abs['methods'][$methodName]['implements'] = $interface;
            }
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
    private function addViaPhpDoc()
    {
        $abs = $this->abs;
        $inheritedFrom = null;
        if (
            empty($abs['phpDoc']['method'])
            && \array_intersect_key($abs['methods'], \array_flip(array('__call', '__callStatic')))
        ) {
            // phpDoc doesn't contain any @method tags,
            // we've got __call and/or __callStatic method:  check if parent classes have @method tags
            $inheritedFrom = $this->addViaPhpDocInherit();
        }
        if (empty($abs['phpDoc']['method'])) {
            // still undefined or empty
            return;
        }
        foreach ($abs['phpDoc']['method'] as $phpDocMethod) {
            $abs['methods'][$phpDocMethod['name']] = $this->buildMethodPhpDoc($phpDocMethod, $inheritedFrom);
        }
    }

    /**
     * Inspect inherited classes until we find methods defined in PhpDoc
     *
     * @return string|null class where found
     */
    private function addViaPhpDocInherit()
    {
        $inheritedFrom = null;
        $reflector = $this->abs['reflector'];
        while ($reflector = $reflector->getParentClass()) {
            $parsed = $this->helper->getPhpDoc($reflector);
            if (isset($parsed['method'])) {
                $inheritedFrom = $reflector->getName();
                $this->abs['phpDoc']['method'] = $parsed['method'];
                break;
            }
        }
        return $inheritedFrom;
    }

    /**
     * Add Methods from abstra
     *
     * @return void
     */
    private function addViaReflection()
    {
        $abs = $this->abs;
        $obj = $abs->getSubject();
        $methods = array();
        foreach ($abs['reflector']->getMethods() as $refMethod) {
            $info = $this->buildMethodRef($obj, $refMethod);
            if ($info['visibility'] === 'private' && $info['inheritedFrom']) {
                // getMethods() returns parent's private methods (#reasons)..  we'll skip it
                continue;
            }
            $methodName = $refMethod->getName();
            $methods[$methodName] = $info;
        }
        $abs['methods'] = $methods;
    }

    /**
     * Build magic method info
     *
     * @param array  $phpDocMethod  parsed phpdoc method info
     * @param string $inheritedFrom classname or null
     *
     * @return array
     */
    private function buildMethodPhpDoc($phpDocMethod, $inheritedFrom)
    {
        $className = $inheritedFrom
            ? $inheritedFrom
            : $this->abs['className'];
        return $this->buildMethodValues(array(
            'inheritedFrom' => $inheritedFrom,
            'isStatic' => $phpDocMethod['static'],
            'params' => \array_map(function ($phpDocParam) use ($className) {
                return $this->buildParamValues(array(
                    'defaultValue' => $this->phpDocParamValue($phpDocParam, $className),
                    'name' => $phpDocParam['name'],
                    'type' => $this->helper->resolvePhpDocType($phpDocParam['type'], $this->abs),
                ));
            }, $phpDocMethod['param']),
            'phpDoc' => array(
                'desc' => null,
                'summary' => $phpDocMethod['desc'],
            ),
            'return' => array(
                'desc' => null,
                'type' => $this->helper->resolvePhpDocType($phpDocMethod['type'], $this->abs),
            ),
            'visibility' => 'magic',
        ));
    }

    /**
     * Get method info
     *
     * @param object|string    $obj       object (or classname) method belongs to
     * @param ReflectionMethod $refMethod ReflectionMethod instance
     *
     * @return array
     */
    private function buildMethodRef($obj, ReflectionMethod $refMethod)
    {
        // getDeclaringClass() returns LAST-declared/overridden
        $className = \is_object($obj)
            ? \get_class($obj)
            : $obj;
        $declaringClassName = $refMethod->getDeclaringClass()->getName();
        $phpDoc = $this->helper->getPhpDoc($refMethod);
        \ksort($phpDoc);
        $info = $this->buildMethodValues(array(
            'attributes' => $this->abs['cfgFlags'] & AbstractObject::COLLECT_ATTRIBUTES_METHOD
                ? $this->helper->getAttributes($refMethod)
                : array(),
            'inheritedFrom' => $declaringClassName !== $className
                ? $declaringClassName
                : null,
            'isAbstract' => $refMethod->isAbstract(),
            'isDeprecated' => $refMethod->isDeprecated() || isset($phpDoc['deprecated']),
            'isFinal' => $refMethod->isFinal(),
            'isStatic' => $refMethod->isStatic(),
            'params' => $this->getParams($refMethod, $phpDoc),
            'phpDoc' => $phpDoc,
            'return' => $this->getReturn($refMethod, $phpDoc),
            'visibility' => $this->helper->getVisibility($refMethod),
        ));
        unset($info['phpDoc']['param']);
        unset($info['phpDoc']['return']);
        return $info;
    }

    /**
     * Return method info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    private static function buildMethodValues($values = array())
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
    private static function buildParamValues($values = array())
    {
        return \array_merge(static::$baseParamInfo, $values);
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
     * @param ReflectionMethod $refMethod ReflectionMethod instance
     * @param array            $phpDoc    Method's parsed phpDoc comment
     *
     * @return array
     */
    private function getParams(ReflectionMethod $refMethod, $phpDoc = array())
    {
        $params = $this->getParamsReflection($refMethod, $phpDoc);
        /*
            Iterate over params only defined via phpDoc
        */
        $phpDocCount = isset($phpDoc['param'])
            ? \count($phpDoc['param'])
            : 0;
        for ($i = \count($params); $i < $phpDocCount; $i++) {
            $phpDocParam = $phpDoc['param'][$i];
            $name = '$' . $phpDocParam['name'];
            if (\substr($name, -4) === ',...') {
                $name = '...' . \substr($name, 0, -4);
            }
            $params[] = $this->buildParamValues(array(
                'defaultValue' => $this->phpDocParamValue($phpDocParam, $this->abs['className']),
                'desc' => $phpDocParam['desc'],
                'isOptional' => true,
                'name' => $name,
                'type' => $this->helper->resolvePhpDocType($phpDocParam['type'], $this->abs),
            ));
        }
        return $params;
    }

    /**
     * Get parameter info from reflection
     *
     * @param ReflectionMethod $refMethod ReflectionMethod instance
     * @param array            $phpDoc    Method's parsed phpDoc comment
     *
     * @return array
     */
    private function getParamsReflection(ReflectionMethod $refMethod, $phpDoc)
    {
        $params = array();
        $collectAttributes = $this->abs['cfgFlags'] & AbstractObject::COLLECT_ATTRIBUTES_PARAM;
        \set_error_handler(function () {
            // suppressing "Use of undefined constant STDERR" type notice
            // encountered on
            //    $refParameter->getDefaultValue()
            //    $refParameter->__toString()
        });
        foreach ($refMethod->getParameters() as $i => $refParameter) {
            $phpDocParam = \array_merge(array(
                'desc' => null,
                'name' => '',
                'type' => null,
            ), isset($phpDoc['param'][$i]) ? $phpDoc['param'][$i] : array());
            $params[] = $this->buildParamValues(array(
                'attributes' => $collectAttributes
                    ? $this->helper->getAttributes($refParameter)
                    : array(),
                'defaultValue' => $this->getParamDefaultVal($refParameter),
                'desc' => $phpDocParam['desc'],
                'isOptional' => $refParameter->isOptional(),
                'isPromoted' =>  PHP_VERSION_ID >= 80000
                    ? $refParameter->isPromoted()
                    : false,
                'name' => $this->getParamName($refParameter, $phpDocParam['name']),
                'type' => $this->getParamTypeHint($refParameter, $phpDocParam['type']),
            ));
        }
        \restore_error_handler();
        return $params;
    }

    /**
     * Get param's default value
     *
     * @param ReflectionParameter $refParameter reflectionParameter
     *
     * @return mixed
     */
    private function getParamDefaultVal(ReflectionParameter $refParameter)
    {
        $defaultValue = Abstracter::UNDEFINED;
        if ($refParameter->isDefaultValueAvailable()) {
            $defaultValue = $refParameter->getDefaultValue();
            if (PHP_VERSION_ID >= 50406 && $refParameter->isDefaultValueConstant()) {
                /*
                    getDefaultValueConstantName() :
                        php may return something like self::CONSTANT_NAME
                        hhvm will return WhateverTheClassNameIs::CONSTANT_NAME
                */
                $defaultValue = new Abstraction(Abstracter::TYPE_CONST, array(
                    'name' => $refParameter->getDefaultValueConstantName(),
                    'value' => $defaultValue,
                ));
            }
        }
        return $defaultValue;
    }

    /**
     * Get Parameter "name"
     *
     * @param ReflectionParameter $refParameter reflectionParameter
     * @param string              $phpDocName   name via phpDoc
     *
     * @return mixed
     */
    private function getParamName(ReflectionParameter $refParameter, $phpDocName)
    {
        $name = '$' . $refParameter->getName();
        if (\method_exists($refParameter, 'isVariadic') && $refParameter->isVariadic()) {
            // php >= 5.6
            $name = '...' . $name;
        } elseif (\substr($phpDocName, -4) === ',...') {
            // phpDoc indicates variadic...
            $name = '...' . $name;
        }
        if ($refParameter->isPassedByReference()) {
            $name = '&' . $name;
        }
        return $name;
    }

    /**
     * Get param typehint
     *
     * @param ReflectionParameter $refParameter reflectionParameter
     * @param string|null         $phpDocType   type via phpDoc
     *
     * @return string|null
     */
    private function getParamTypeHint(ReflectionParameter $refParameter, $phpDocType)
    {
        $matches = array();
        if ($phpDocType !== null) {
            return $this->helper->resolvePhpDocType($phpDocType, $this->abs);
        }
        if (PHP_VERSION_ID >= 70000) {
            return $this->helper->getTypeString($refParameter->getType());
        }
        if ($refParameter->isArray()) {
            // isArray is deprecated in php 8.0
            // isArray is only concerned with type-hint and does not look at default value
            return 'array';
        }
        if (\preg_match('/\[\s<\w+>\s([\w\\\\]+)/s', $refParameter->__toString(), $matches)) {
            // Parameter #0 [ <required> namespace\Type $varName ]
            return $matches[1];
        }
        return null;
    }

    /**
     * Get return type & desc
     *
     * @param ReflectionMethod $refMethod ReflectionMethod
     * @param array            $phpDoc    parsed phpDoc param info
     *
     * @return array
     */
    private function getReturn(ReflectionMethod $refMethod, $phpDoc)
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
            $return['type'] = $this->helper->getTypeString($refMethod->getReturnType());
        }
        return $return;
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
            return $this->phpDocConstant($defaultValue, $className, $matches);
        }
        return \trim($defaultValue, '\'"');
    }

    /**
     * Build default value via PhpDoc
     *
     * @param string $defaultValue Default value as specified in PhpDoc
     * @param string $className    classname where defined
     * @param array  $matches      regex matches
     *
     * @return string|Abstraction
     */
    private function phpDocConstant($defaultValue, $className, $matches)
    {
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
