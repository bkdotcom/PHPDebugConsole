<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\AbstractObjectHelper;
use bdk\Debug\Abstraction\AbstractObjectMethodParams;
use Exception;
use ReflectionMethod;

/**
 * Get object method info
 */
class AbstractObjectMethods
{
    protected $abstracter;
    protected $helper;
    protected $params;

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
        $this->params = new AbstractObjectMethodParams($abstracter, $helper);
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
        $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT
            ? $this->addMethodsFull($abs)
            : $this->addMethodsMin($abs);
        $this->addFinish($abs);
    }

    /**
     * Return method info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildMethodValues($values = array())
    {
        return \array_merge(static::$baseMethodInfo, $values);
    }

    /**
     * Adds methods to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addMethodsFull(Abstraction $abs)
    {
        if ($this->abstracter->getCfg('methodCache') && isset(static::$methodCache[$abs['className']])) {
            $abs['methods'] = static::$methodCache[$abs['className']];
            $this->addFinish($abs);
            return;
        }
        $briefBak = $this->abstracter->debug->setCfg('brief', true);
        $this->addViaReflection($abs);
        $this->abstracter->debug->setCfg('brief', $briefBak);
        $this->addViaPhpDoc($abs);
        $this->addImplements($abs);
        if ($abs['className'] !== 'Closure') {
            static::$methodCache[$abs['className']] = $abs['methods'];
        }
    }

    /**
     * Add minimal method information to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addMethodsMin(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        if (\method_exists($obj, '__toString')) {
            $abs['methods']['__toString'] = array(
                'returnValue' => null, // set via addFinish()
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
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addFinish(Abstraction $abs)
    {
        unset($abs['phpDoc']['method']);
        if (isset($abs['methods']['__toString'])) {
            $abs['methods']['__toString']['returnValue'] = $this->toString($abs);
        }
        $phpDocCollect = $abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT;
        if ($phpDocCollect) {
            return;
        }
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
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addImplements(Abstraction $abs)
    {
        $interfaceMethods = array(
            'ArrayAccess' => array('offsetExists','offsetGet','offsetSet','offsetUnset'),
            'BackedEnum' => array('from', 'tryFrom'),
            'Countable' => array('count'),
            'Iterator' => array('current','key','next','rewind','valid'),
            'IteratorAggregate' => array('getIterator'),
            'UnitEnum' => array('cases'),
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
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     *
     * @see http://docs.phpdoc.org/references/phpdoc/tags/method.html
     */
    private function addViaPhpDoc(Abstraction $abs)
    {
        $inheritedFrom = null;
        if (
            empty($abs['phpDoc']['method'])
            && \array_intersect_key($abs['methods'], \array_flip(array('__call', '__callStatic')))
        ) {
            // phpDoc doesn't contain any @method tags,
            // we've got __call and/or __callStatic method:  check if parent classes have @method tags
            $inheritedFrom = $this->addViaPhpDocInherit($abs);
        }
        if (empty($abs['phpDoc']['method'])) {
            // still undefined or empty
            return;
        }
        foreach ($abs['phpDoc']['method'] as $phpDocMethod) {
            $abs['methods'][$phpDocMethod['name']] = $this->buildMethodPhpDoc($abs, $phpDocMethod, $inheritedFrom);
        }
    }

    /**
     * Inspect inherited classes until we find methods defined in PhpDoc
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string|null class where found
     */
    private function addViaPhpDocInherit(Abstraction $abs)
    {
        $inheritedFrom = null;
        $reflector = $abs['reflector'];
        while ($reflector = $reflector->getParentClass()) {
            $parsed = $this->helper->getPhpDoc($reflector);
            if (isset($parsed['method'])) {
                $inheritedFrom = $reflector->getName();
                $abs['phpDoc']['method'] = $parsed['method'];
                break;
            }
        }
        return $inheritedFrom;
    }

    /**
     * Add methods from reflection
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addViaReflection(Abstraction $abs)
    {
        $methods = array();
        foreach ($abs['reflector']->getMethods() as $refMethod) {
            $info = $this->buildMethodRef($abs, $refMethod);
            if ($info['visibility'] === 'private' && $info['inheritedFrom']) {
                // getMethods() returns parent's private methods (#reasons)..  we'll skip it
                continue;
            }
            unset($info['phpDoc']['param']);
            unset($info['phpDoc']['return']);
            \ksort($info['phpDoc']);
            $methodName = $refMethod->getName();
            $methods[$methodName] = $info;
        }
        $abs['methods'] = $methods;
    }

    /**
     * Build magic method info
     *
     * @param Abstraction $abs           Object Abstraction instance
     * @param array       $phpDocMethod  parsed phpdoc method info
     * @param string      $inheritedFrom classname or null
     *
     * @return array
     */
    private function buildMethodPhpDoc(Abstraction $abs, $phpDocMethod, $inheritedFrom)
    {
        $className = $inheritedFrom
            ? $inheritedFrom
            : $abs['className'];
        return $this->buildMethodValues(array(
            'inheritedFrom' => $inheritedFrom,
            'isStatic' => $phpDocMethod['static'],
            'params' => $this->params->getParamsPhpDoc($abs, $phpDocMethod, $className),
            'phpDoc' => array(
                'desc' => null,
                'summary' => $phpDocMethod['desc'],
            ),
            'return' => array(
                'desc' => null,
                'type' => $this->helper->resolvePhpDocType($phpDocMethod['type'], $abs),
            ),
            'visibility' => 'magic',
        ));
    }

    /**
     * Get method info
     *
     * @param Abstraction      $abs       Object Abstraction instance
     * @param ReflectionMethod $refMethod ReflectionMethod instance
     *
     * @return array
     */
    private function buildMethodRef(Abstraction $abs, ReflectionMethod $refMethod)
    {
        $obj = $abs->getSubject();
        // get_class() returns "raw" classname
        //     could be stdClass@anonymous/filepath.php:6$2e
        // $abs['className'] is a "friendly" name ... just stdClass@anonymous
        $className = \is_object($obj)
            ? \get_class($obj)
            : $obj;
        // getDeclaringClass() returns LAST-declared/overridden
        $declaringClassName = $refMethod->getDeclaringClass()->getName();
        $phpDoc = $this->helper->getPhpDoc($refMethod);
        return $this->buildMethodValues(array(
            'attributes' => $abs['cfgFlags'] & AbstractObject::METHOD_ATTRIBUTE_COLLECT
                ? $this->helper->getAttributes($refMethod)
                : array(),
            'inheritedFrom' => $declaringClassName !== $className
                ? $declaringClassName
                : null,
            'isAbstract' => $refMethod->isAbstract(),
            'isDeprecated' => $refMethod->isDeprecated() || isset($phpDoc['deprecated']),
            'isFinal' => $refMethod->isFinal(),
            'isStatic' => $refMethod->isStatic(),
            'params' => $this->params->getParams($abs, $refMethod, $phpDoc),
            'phpDoc' => $phpDoc,
            'return' => $this->getReturn($abs, $refMethod, $phpDoc),
            'visibility' => $this->helper->getVisibility($refMethod),
        ));
    }

    /**
     * Get return type & desc
     *
     * @param Abstraction      $abs       Object Abstraction instance
     * @param ReflectionMethod $refMethod ReflectionMethod
     * @param array            $phpDoc    parsed phpDoc param info
     *
     * @return array
     */
    private function getReturn(Abstraction $abs, ReflectionMethod $refMethod, $phpDoc)
    {
        $return = array(
            'desc' => null,
            'type' => null,
        );
        if (!empty($phpDoc['return'])) {
            $return = \array_merge($return, $phpDoc['return']);
            $return['type'] = $this->helper->resolvePhpDocType($return['type'], $abs);
            if (!($abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT)) {
                $return['desc'] = null;
            }
        } elseif (PHP_VERSION_ID >= 70000) {
            $return['type'] = $this->helper->getTypeString($refMethod->getReturnType());
        }
        return $return;
    }

    /**
     * Get object's __toString value if method is not deprecated
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string|Abstraction|null
     */
    private function toString(Abstraction $abs)
    {
        // abs['methods']['__toString'] may not exist if via addMethodsMin
        if (!empty($abs['methods']['__toString']['isDeprecated'])) {
            return null;
        }
        $obj = $abs->getSubject();
        if (\is_object($obj) === false) {
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
            return $val;
        }
        return $val;
    }
}
