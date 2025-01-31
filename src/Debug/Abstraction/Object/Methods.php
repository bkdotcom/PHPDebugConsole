<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
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
    /** @var array<string,mixed> */
    protected $methods = array();

    /** @var list<string> */
    protected $methodsWithStatic = array();

    /** @var MethodParams */
    protected $params;

    /** @var array<string,mixed> */
    protected static $values = array(
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
            'desc' => '',
            'summary' => '',
        ),
        'return' => array(
            'desc' => '',
            'type' => null,
        ),
        'staticVars' => array(),
        'visibility' => 'public',  // public | private | protected | magic
    );

    /** @var bool */
    private $minimal = false;

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
        $this->helper->clearPhpDoc($abs);
        \ksort($abs['methods']);
        if (isset($abs['methods']['__toString'])) {
            $info = $abs['methods']['__toString'];
            $info['returnValue'] = null;
            \ksort($info);
            $abs['methods']['__toString'] = $info;
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
            /** @var Abstraction|string */
            $val = $this->abstracter->crate($obj->__toString(), $abs['debugMethod'], $abs['hist']);
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
        $this->addViaRef($abs);
        $this->addViaPhpDoc($abs);
        $this->addImplements($abs);
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
        $this->minimal = true;
        $this->addViaRef($abs);
        $this->minimal = false;
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
            && \array_intersect_key($abs['methods'], \array_flip(['__call', '__callStatic']))
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
        return static::buildValues(array(
            'declaredLast' => $declaredLast,
            'isStatic' => $phpDoc['static'],
            'params' => $this->params->getParamsPhpDoc($abs, $phpDoc, $className),
            'phpDoc' => array(
                'desc' => '',
                'summary' => $phpDoc['desc'],
            ),
            'return' => array(
                'desc' => '',
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
        // set brief to avoid recursion with enum values
        $briefBak = $this->abstracter->debug->setCfg('brief', true, Debug::CONFIG_NO_PUBLISH);
        $this->methods = array();
        $this->methodsWithStatic = array();
        $this->traverseAncestors($abs['reflector'], function (ReflectionClass $reflector) use ($abs) {
            $className = $this->helper->getClassName($reflector);
            $refMethods = $reflector->getMethods();
            if ($this->minimal) {
                $refMethods = \array_filter($refMethods, static function (ReflectionMethod $refMethod) {
                    return \in_array($refMethod->getName(), ['__toString', '__get', '__set'], true);
                });
            }
            foreach ($refMethods as $refMethod) {
                $this->addViaRefBuild($abs, $refMethod, $className);
            }
        }, $abs['isInterface'] ? $abs['extends'] : false);
        $abs['methods'] = $this->methods;
        $methodsWithStatic = \array_unique($this->methodsWithStatic);
        \sort($methodsWithStatic);
        $abs['methodsWithStaticVars'] = $methodsWithStatic;
        $this->abstracter->debug->setCfg('brief', $briefBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
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
        $returnTag = isset($phpDoc['return']) ? $phpDoc['return'] : array(
            'desc' => '',
            'type' => null,
        );
        return static::buildValues(array(
            'attributes' => $abs['cfgFlags'] & AbstractObject::METHOD_ATTRIBUTE_COLLECT
                ? $this->helper->getAttributes($refMethod)
                : array(),
            'hasStaticVars' => \count($refMethod->getStaticVariables()) > 0, // temporary we don't store the values in the definition, only what methods have static vars
            'isAbstract' => $refMethod->isAbstract(),
            'isDeprecated' => $refMethod->isDeprecated() || isset($phpDoc['deprecated']),
            'isFinal' => $refMethod->isFinal(),
            'isStatic' => $refMethod->isStatic(),
            'params' => $this->params->getParams($abs, $refMethod, $phpDoc),
            'phpDoc' => \array_diff_key($phpDoc, \array_flip(['param', 'return'])),
            'return' => array(
                'desc' => $returnTag['desc'],
                'type' => $this->helper->getType($returnTag['type'], $refMethod),
            ),
            'visibility' => $this->getVisibility($refMethod),
        ));
    }
}
