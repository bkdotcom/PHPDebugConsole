<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\AbstractObject;
use ReflectionClass;
use Reflector;

/**
 * Base class for collecting constants, properties, & methods
 */
abstract class AbstractInheritable
{
    /** @var AbstractObject */
    protected $abstracter;

    /** @var Helper */
    protected $helper;

    /** @var array<string,mixed> */
    protected static $values = array();

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
     * Build (constant,method,property) info by passing values
     *
     * @param array $values Values to apply
     *
     * @return array
     */
    public static function buildValues($values = array())
    {
        return \array_merge(static::$values, $values);
    }

    /**
     * Get constant/method/property visibility
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return list<string>|'public'|'private'|'protected'
     */
    protected static function getVisibility(Reflector $reflector)
    {
        if ($reflector->isPrivate()) {
            return 'private';
        }
        if ($reflector->isProtected()) {
            return 'protected';
        }
        return 'public';
    }

    /**
     * Pass reflector and ancestor reflectors to callable
     *
     * @param ReflectionClass $reflector      ReflectionClass instance
     * @param callable        $callable       callable to be invoked on each parent
     * @param array|bool      $inclInterfaces (false)
     *                                          bool:  Whether to also iterate over interfaces
     *                                          array:  interfaces/classes to iterate over
     *                                          interfaces have multiple inheritance / getParentClass returns false
     *                                          constants may be inherited from interfaces
     *                                          properties only from parent classes
     *
     * @return void
     */
    protected function traverseAncestors(ReflectionClass $reflector, callable $callable, $inclInterfaces = false)
    {
        $interfaces = array();
        if ($inclInterfaces === true) {
            $interfaces = $reflector->getInterfaceNames();
        } elseif (\is_array($inclInterfaces)) {
            // flatten interface tree
            \preg_match_all('/("[^"]+")/', \json_encode($inclInterfaces), $matches);
            $interfaces = \array_map('json_decode', $matches[1]);
        }
        while ($reflector) {
            $callable($reflector);
            $reflector = $reflector->getParentClass();
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
