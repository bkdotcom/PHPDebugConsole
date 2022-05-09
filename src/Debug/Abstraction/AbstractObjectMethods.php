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
    protected $abs;
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
        $this->params = new AbstractObjectMethodParams($helper);
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
            'Iterator' => array('current','key','next','rewind','valid'),
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
     * Add methods from reflection
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
            'params' => $this->params->getParamsPhpDoc($this->abs, $phpDocMethod, $className),
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
            'params' => $this->params->getParams($this->abs, $refMethod, $phpDoc),
            'phpDoc' => $phpDoc,
            'return' => $this->getReturn($refMethod, $phpDoc),
            'visibility' => $this->helper->getVisibility($refMethod),
        ));
        unset($info['phpDoc']['param']);
        unset($info['phpDoc']['return']);
        return $info;
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
            return $val;
        }
        return $val;
    }
}
