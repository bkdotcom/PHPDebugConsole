<?php

namespace bdk\Proxy;

use Reflection;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

/**
 * A factory for creating class definitions.
 */
class ClassDefFactory
{
    /** @var array<string,mixed> */
    public static $defaultMethod = array(
        'modifiers' => ['public'],
        'name' => null,
        'parameters' => [],
        'proxyViaFuncGetArgs' => false, // overloaded methods may not like having default values passed (ie PDO::query)
        'returnType' => null,
    );

    /** @var array<string,mixed> */
    public static $defaultParam = array(
        'defaultValue' => null,
        // 'defaultValueConstantName' => null,
        'isDefaultValueAvailable' => false,
        'isDefaultValueConstant' => false,
        'isPassedByReference' => false,
        'isVariadic' => false,
        'name' => null,
        'type' => null,
    );

    /** @var array<string,mixed> */
    public static $defaultProperty = array(
        'hasValue' => false,
        'modifiers' => ['public'],
        'name' => null,
        'type' => null,
        'value' => null,
    );

    private $classDefModifiers = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $coreClassDefModifier = new ClassDefModifier();
        $this->addClassDefModifier([$coreClassDefModifier, 'modify']);
    }

    /**
     * Add a class definition modifier. Modifiers are applied in the order they were added and allow for updating / overriding class definitions after they are created.
     *
     * @param callable $modifier Receives a class definition and returns a modified class definition. Signature: function(array $classDef): array
     *
     * @return void
     */
    public function addClassDefModifier(callable $modifier)
    {
        $this->classDefModifiers[] = $modifier;
    }

    /**
     * Get class (or interface)definition.
     *
     * @param class-string $className Full class or interface name (including namespace).
     *
     * @throws ReflectionException In case class or interface does not exist.
     *
     * @return array Class definition with all related definitions (methods, parameters, types) linked.
     */
    public function getClassDef($className)
    {
        if (\class_exists($className) === false && \interface_exists($className) === false) {
            // explicitly throw exception here vs letting ReflectionClass throw it for consistent error message
            throw new ReflectionException('Class (or interface) "' . $className . '" does not exist');
        }
        $reflection = new ReflectionClass($className);
        $classDef = array(
            'interfaces' => $reflection->getInterfaceNames(),
            'isInterface' => $reflection->isInterface(),
            'methods' => $this->getMethodDefinitions($reflection),
            'modifiers' => Reflection::getModifierNames($reflection->getModifiers()),
            'name' => $reflection->getName(),
            'namespace' => $reflection->getNamespaceName(),
            'parentClass' => $reflection->getParentClass() ?: null,
            'properties' => [],
            'shortName' => $reflection->getShortName(),
        );
        foreach ($this->classDefModifiers as $modifier) {
            $classDef = $modifier($classDef);
        }
        return $classDef;
    }

    /**
     * Gets public method definitions for a given class reflection.
     *
     * @param ReflectionClass $refClass Class reflection
     *
     * @return array name => array of method definition (modifiers, parameters, return type)
     */
    private function getMethodDefinitions(ReflectionClass $refClass)
    {
        $methods = [];
        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $refMethod) {
            $name = $refMethod->getName();
            $methods[$name] = $this->getMethodDefinition($refMethod);
        }
        return $methods;
    }

    /**
     * Gets method definition for individual class / method reflection pair.
     *
     * @param ReflectionMethod $refMethod Method reflection
     *
     * @return array Single method definition.
     */
    private function getMethodDefinition(ReflectionMethod $refMethod)
    {
        return array(
            'modifiers' => Reflection::getModifierNames($refMethod->getModifiers()),
            'name' => $refMethod->getName(),
            'parameters' => $this->getMethodParameterDefinitions($refMethod),
            'returnType' => $this->getMethodReturnType($refMethod),
        );
    }

    /**
     * Gets the complete set of parameter definitions for a given method reflection.
     *
     * @param ReflectionMethod $refMethod Method reflection
     *
     * @return array List of parameter definitions. The order is maintained.
     */
    private function getMethodParameterDefinitions(ReflectionMethod $refMethod)
    {
        $parameters = [];
        foreach ($refMethod->getParameters() as $refParam) {
            $parameters[] = $this->getMethodParameterDefinition($refParam);
        }
        return $parameters;
    }

    /**
     * Gets single parameter definition for individual method's parameter reflection.
     *
     * @param ReflectionParameter $refParam Parameter reflection
     *
     * @return array Single parameter definition.
     */
    private function getMethodParameterDefinition(ReflectionParameter $refParam)
    {
        $hasDefaultValue = $refParam->isDefaultValueAvailable() ?: false;
        $isDefaultValueConstant = $hasDefaultValue
            ? $refParam->isDefaultValueConstant()
            : false;
        return array(
            'defaultValue' => $hasDefaultValue
                ? ($isDefaultValueConstant
                    ? $refParam->getDefaultValueConstantName()
                    : $refParam->getDefaultValue()
                )
                : null,
            // 'defaultValueConstantName' => $hasDefaultValue
                // ? $refParam->getDefaultValueConstantName()
                // : null,
            'isDefaultValueAvailable' => $hasDefaultValue,
            'isDefaultValueConstant' => $isDefaultValueConstant,
            'isPassedByReference' => $refParam->isPassedByReference(),
            'isVariadic' => PHP_VERSION_ID >= 50600
                ? $refParam->isVariadic()
                : false,
            'name' => $refParam->getName(),
            'type' => $this->getMethodParameterType($refParam),
        );
    }

    /**
     * Gets single type definition for individual method's parameter reflection.
     *
     * @param ReflectionParameter $refParam Parameter reflection
     *
     * @return string|null Type definition or `null` if not specified.
     */
    private function getMethodParameterType(ReflectionParameter $refParam)
    {
        $type = PHP_VERSION_ID >= 70000
            ? $refParam->getType()
            : $this->getTypeLegacy($refParam);
        if (!$type) {
            return null;
        }
        \set_error_handler(static function () {
            // ignore php 7.*'s deprecation
        });
        $type = (string) $type;
        \restore_error_handler();
        return $type;
    }

    /**
     * Get parameter type from legacy ReflectionParameter or ReflectionMethod
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return array{allowsNull:bool,php:string|null}
     */
    private function getTypeLegacy(ReflectionParameter $refParam)
    {
        $type = null;
        $isReflectionParameter = $refParam instanceof ReflectionParameter;
        if ($isReflectionParameter && $refParam->isArray()) {
            // isArray is deprecated in php 8.0
            // isArray is only concerned with type-hint and does not look at default value
            $type = 'array';
        } elseif (\preg_match('/\[\s<\w+>\s([\w\\\\]+)/s', $refParam->__toString(), $matches)) {
            // Parameter #0 [ <required> namespace\Type $varName ]
            $type = $matches[1];
        }
        return $type;
    }

    /**
     * Gets single return type definition for individual method reflection.
     *
     * @param ReflectionMethod $refMethod Method reflection
     *
     * @return string|null Type definition or `null` if not specified.
     */
    private function getMethodReturnType(ReflectionMethod $refMethod)
    {
        $returnType = PHP_VERSION_ID >= 70000
            ? $refMethod->getReturnType()
            : null;
        if (!$returnType && \method_exists($refMethod, 'getTentativeReturnType')) {
            $returnType = $refMethod->getTentativeReturnType();
        }
        if (!$returnType) {
            return null;
        }
        \set_error_handler(static function () {
            // ignore php 7.*'s deprecation
        });
        $type = (string) $returnType;
        \restore_error_handler();
        return $type;
    }
}
