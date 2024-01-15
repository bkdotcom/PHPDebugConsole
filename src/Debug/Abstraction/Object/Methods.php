<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\MethodParams;
use Exception;
use ReflectionClass;
use ReflectionMethod;

/**
 * Get object method info
 */
class Methods extends AbstractInheritable
{
    protected $methods = array();
    protected $methodsWithStatic = array();

    /** @var MethodParams */
    protected $params;

    private static $baseMethodInfo = array(
        'attributes' => array(),
        'declaredLast' => null,
        'declaredOrig' => null,
        'declaredPrev' => null,
        'implements' => null,
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
            'desc' => null,
            'type' => null,
        ),
        'staticVars' => array(),
        'visibility' => 'public',  // public | private | protected | magic
    );

    /**
     * Constructor
     *
     * @param AbstractObject $abstractObject Object abstracter
     */
    public function __construct(AbstractObject $abstractObject)
    {
        parent::__construct($abstractObject);
        $this->params = new MethodParams($abstractObject);
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
        $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT
            ? $this->addFull($abs)
            : $this->addMin($abs);
        if (isset($abs['methods']['__toString'])) {
            $abs['methods']['__toString']['returnValue'] = null;
        }
    }

    /**
     * Add static variable info to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function addInstance(Abstraction $abs)
    {
        $staticVarCollect = $abs['cfgFlags'] & AbstractObject::METHOD_COLLECT
            && $abs['cfgFlags'] & AbstractObject::METHOD_STATIC_VAR_COLLECT;
        if ($staticVarCollect === false) {
            return;
        }
        foreach ($abs['methodsWithStaticVars'] as $name) {
            $reflector = $abs['reflector']->getMethod($name);
            $abs['methods'][$name]['staticVars'] = \array_map(function ($value) use ($abs) {
                return $this->abstracter->crate($value, $abs['debugMethod'], $abs['hist']);
            }, $reflector->getStaticVariables());
        }
    }

    /**
     * Return method info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildMethodValues(array $values = array())
    {
        return \array_merge(static::$baseMethodInfo, $values);
    }

    /**
     * Get object's __toString value if method is not deprecated
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string|Abstraction|null
     */
    public function toString(Abstraction $abs)
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

    /**
     * Adds methods to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addFull(Abstraction $abs)
    {
        $briefBak = $this->abstracter->debug->setCfg('brief', true, Debug::CONFIG_NO_PUBLISH);
        $this->addViaRef($abs);
        $this->abstracter->debug->setCfg('brief', $briefBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
        $this->addViaPhpDoc($abs);
        $this->addImplements($abs);
        $this->helper->clearPhpDoc($abs);
        \ksort($abs['methods']);
    }

    /**
     * Add minimal method information to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addMin(Abstraction $abs)
    {
        $reflector = $abs['reflector'];
        if ($reflector->hasMethod('__toString')) {
            $abs['methods']['__toString'] = array(
                'visibility' => 'public',
            );
        }
        if ($reflector->hasMethod('__get')) {
            $abs['methods']['__get'] = array('visibility' => 'public');
        }
        if ($reflector->hasMethod('__set')) {
            $abs['methods']['__set'] = array('visibility' => 'public');
        }
    }

    /**
     * Add `implements` value to interface methods
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addImplements(Abstraction $abs)
    {
        $stack = $abs['implements'];
        while ($stack) {
            $key = \key($stack);
            $val = \array_shift($stack);
            $classname = $val;
            if (\is_array($val)) {
                $classname = $key;
                $stack = \array_merge($stack, $val);
            }
            $refClass = new ReflectionClass($classname);
            foreach ($refClass->getMethods() as $refMethod) {
                $methodName = $refMethod->getName();
                $abs['methods'][$methodName]['implements'] = $classname;
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
        $declaredLast = $abs['className'];
        $phpDoc = $this->helper->getPhpDoc($abs['reflector'], $abs['fullyQualifyPhpDocType']);
        if (
            empty($phpDoc['method'])
            && \array_intersect_key($abs['methods'], \array_flip(array('__call', '__callStatic')))
        ) {
            // phpDoc doesn't contain any @method tags,
            // we've got __call and/or __callStatic method:  check if parent classes have @method tags
            $phpDoc = $this->addViaPhpDocInherit($abs, $declaredLast);
        }
        if (empty($phpDoc['method'])) {
            // still undefined or empty
            return;
        }
        foreach ($phpDoc['method'] as $phpDocMethod) {
            $abs['methods'][$phpDocMethod['name']] = $this->addViaPhpDocBuild($abs, $phpDocMethod, $declaredLast);
        }
    }

    /**
     * Build magic method info
     *
     * @param Abstraction $abs          Object Abstraction instance
     * @param array       $phpDoc       Parsed phpdoc method info
     * @param string      $declaredLast class-name or null
     *
     * @return array
     */
    private function addViaPhpDocBuild(Abstraction $abs, array $phpDoc, $declaredLast)
    {
        $className = $declaredLast
            ? $declaredLast
            : $abs['className'];
        return $this->buildMethodValues(array(
            'declaredLast' => $declaredLast,
            'isStatic' => $phpDoc['static'],
            'params' => $this->params->getParamsPhpDoc($abs, $phpDoc, $className),
            'phpDoc' => array(
                'desc' => null,
                'summary' => $phpDoc['desc'],
            ),
            'return' => array(
                'desc' => null,
                'type' => $phpDoc['type'],
            ),
            'visibility' => 'magic',
        ));
    }

    /**
     * Inspect inherited classes until we find methods defined in PhpDoc
     *
     * @param Abstraction $abs          Object Abstraction instance
     * @param string      $declaredLast Populated with class-name where phpdoc containing @method found
     *
     * @return string|null class where found
     */
    private function addViaPhpDocInherit(Abstraction $abs, &$declaredLast = null)
    {
        $phpDoc = array();
        $reflector = $abs['reflector'];
        while ($reflector = $reflector->getParentClass()) {
            $phpDoc = $this->helper->getPhpDoc($reflector, $abs['fullyQualifyPhpDocType']);
            if (isset($phpDoc['method'])) {
                $declaredLast = $reflector->getName();
                break;
            }
        }
        return $phpDoc;
    }

    /**
     * Add methods from reflection
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    private function addViaRef(Abstraction $abs)
    {
        $this->methods = array();
        $this->methodsWithStatic = array();
        $this->traverseAncestors($abs['reflector'], function (ReflectionClass $reflector) use ($abs) {
            $className = $this->helper->getClassName($reflector);
            foreach ($reflector->getMethods() as $refMethod) {
                $this->addViaRefBuild($abs, $refMethod, $className);
            }
        });
        $abs['methods'] = $this->methods;
        $methodsWithStatic = \array_unique($this->methodsWithStatic);
        \sort($methodsWithStatic);
        $abs['methodsWithStaticVars'] = $methodsWithStatic;
    }

    /**
     * Add/Update method info
     *
     * @param Abstraction      $abs       Object Abstraction instance
     * @param ReflectionMethod $refMethod ReflectionMethod instance
     * @param string           $className Current level className
     *
     * @return void
     */
    private function addViaRefBuild(Abstraction $abs, ReflectionMethod $refMethod, $className)
    {
        $name = $refMethod->getName();
        $info = isset($this->methods[$name])
            ? $this->methods[$name]
            : $this->addViaRefBuildInit($abs, $refMethod);
        $info = $this->updateDeclarationVals(
            $info,
            $this->helper->getClassName($refMethod->getDeclaringClass()),
            $className
        );
        $isInherited = $info['declaredLast'] && $info['declaredLast'] !== $abs['className'];
        if ($info['visibility'] === 'private' && $isInherited) {
            // getMethods() returns parent's private methods (#reasons)..  we'll skip it
            return;
        }
        if (!empty($info['hasStaticVars'])) {
            $this->methodsWithStatic[] = $name;
        }
        unset($info['hasStaticVars']);
        unset($info['phpDoc']['param']);
        unset($info['phpDoc']['return']);
        $this->methods[$name] = $info;
    }

    /**
     * Get method info
     *
     * @param Abstraction      $abs       Object Abstraction instance
     * @param ReflectionMethod $refMethod ReflectionMethod instance
     *
     * @return array
     */
    private function addViaRefBuildInit(Abstraction $abs, ReflectionMethod $refMethod)
    {
        $phpDoc = $this->helper->getPhpDoc($refMethod, $abs['fullyQualifyPhpDocType']);
        return $this->buildMethodValues(array(
            'attributes' => $abs['cfgFlags'] & AbstractObject::METHOD_ATTRIBUTE_COLLECT
                ? $this->helper->getAttributes($refMethod)
                : array(),
            'hasStaticVars' => \count($refMethod->getStaticVariables()) > 0, // temporary we don't store the values in the definition, only what methods have static vars
            'isAbstract' => $refMethod->isAbstract(),
            'isDeprecated' => $refMethod->isDeprecated() || isset($phpDoc['deprecated']),
            'isFinal' => $refMethod->isFinal(),
            'isStatic' => $refMethod->isStatic(),
            'params' => $this->params->getParams($abs, $refMethod, $phpDoc),
            'phpDoc' => $phpDoc,
            'return' => $this->getReturn($refMethod, $phpDoc),
            'visibility' => $this->helper->getVisibility($refMethod),
        ));
    }

    /**
     * Get return type & desc
     *
     * @param ReflectionMethod $refMethod ReflectionMethod
     * @param array            $phpDoc    parsed phpDoc param info
     *
     * @return array
     */
    private function getReturn(ReflectionMethod $refMethod, array $phpDoc)
    {
        $return = array(
            'desc' => null,
            'type' => null,
        );
        if (isset($phpDoc['return']['type'])) {
            $return = \array_merge($return, $phpDoc['return']);
        } elseif (PHP_VERSION_ID >= 70000) {
            $return['type'] = $this->helper->getTypeString($refMethod->getReturnType());
        }
        return $return;
    }
}
