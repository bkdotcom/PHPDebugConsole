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

use bdk\Debug\Abstraction\AbstractObject;
use ReflectionClass;

/**
 * Base class for collecting constants, properties, & methods
 */
abstract class AbstractInheritable
{
    /** @var AbstractObject */
    protected $abstracter;

    /** @var Helper */
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
     * @param ReflectionClass $reflector      ReflectionClass instance
     * @param callable        $callable       callable to be invoked on each parent
     * @param bool            $inclInterfaces (false) Whether to also iterate over interfaces
     *
     * @return void
     */
    protected function traverseAncestors(ReflectionClass $reflector, callable $callable, $inclInterfaces = false)
    {
        $interfaces = $reflector->getInterfaceNames();
        while ($reflector) {
            $callable($reflector);
            $reflector = $reflector->getParentClass();
        }
        if ($inclInterfaces === false) {
            return;
        }
        foreach ($interfaces as $className) {
            $reflector = new ReflectionClass($className);
            $callable($reflector);
        }
    }

    /**
     * Set/update declaredLast, declaredPrev, & declaredOrig
     *
     * @param array  $info               constant/method/property info
     * @param string $declaringClassName class where declared
     * @param string $className          class or ancestor-class being iterated
     *
     * @return array updated info
     */
    protected function updateDeclarationVals(array $info, $declaringClassName, $className)
    {
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
