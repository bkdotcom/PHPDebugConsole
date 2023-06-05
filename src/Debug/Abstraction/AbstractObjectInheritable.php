<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Abstraction;

use ReflectionClass;
use Reflector;

/**
 * Base class for collecting constants, properties, & methods
 */
abstract class AbstractObjectInheritable
{
    /** @var Abstracter */
    protected $abstracter;

    protected $helper;

    /**
     * Constructor
     *
     * @param AbstractObject $abstractObject Object abstracter
     */
    public function __construct(AbstractObject $abstractObject)
    {
        $this->abstracter = $abstractObject->abstracter;
        $this->helper = $abstractObject->helper;
    }

    /**
     * Pass reflector and each parent class reflector to callable
     *
     * @param ReflectionClass $reflector [description]
     * @param callable        $callable  [description]
     *
     * @return void
     */
    protected function traverseAncestors(ReflectionClass $reflector, callable $callable)
    {
        while ($reflector) {
            $callable($reflector);
            $reflector = $reflector->getParentClass();
        }
    }

    /**
     * Set/update declaredLast, declaredPrev, & declaredOrig
     *
     * @param array     $info      constant/method/property info
     * @param Reflector $reflector constant/method/property reflector
     * @param [type]    $className class or ancestor-class being iterated
     *
     * @return array updated info
     */
    protected function updateDeclarationVals(array $info, Reflector $reflector, $className)
    {
        $declaringClassName = $this->helper->getClassName($reflector->getDeclaringClass());
        // $foo = $reflector instanceof \ReflectionClassConstant;
        if ($info['declaredLast'] === null) {
            $info['declaredLast'] = $declaringClassName;
            $info['declaredOrig'] = $declaringClassName;
            return $info;
        }
        if ($declaringClassName !== $className) {
            return $info;
        }
        if ($info['declaredPrev'] === null && $info['declaredLast'] !== $className) {
            $info['declaredPrev'] = $className;
        }
        $info['declaredOrig'] = $className;
        return $info;
    }
}
