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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Helper;
use bdk\Debug\Abstraction\Type;
use ReflectionMethod;
use ReflectionParameter;
use UnitEnum;

/**
 * Get method parameter info
 */
class MethodParams
{
    /** @var Abstraction|null */
    protected $abs;

    /** @var Abstracter */
    protected $abstracter;

    /** @var Helper */
    protected $helper;

    /** @var array<string,mixed> */
    private static $baseParamInfo = array(
        'attributes' => array(),
        'defaultValue' => Abstracter::UNDEFINED,
        'desc' => '',
        'isOptional' => false,
        'isPassedByReference' => false,
        'isPromoted' => false,
        'isVariadic' => false,
        'name' => '',
        'type' => null,
    );

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
     * Return method info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildValues($values = array())
    {
        return \array_merge(static::$baseParamInfo, $values);
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
     * @param Abstraction      $abs       Object Abstraction instance
     * @param ReflectionMethod $refMethod ReflectionMethod instance
     * @param array            $phpDoc    Method's parsed phpDoc comment
     *
     * @return array
     */
    public function getParams(Abstraction $abs, ReflectionMethod $refMethod, $phpDoc = array())
    {
        $this->abs = $abs;
        $phpDocParams = isset($phpDoc['param'])
            ? $phpDoc['param']
            : array();
        $phpDocParamsByName = array();
        foreach ($phpDocParams as $info) {
            $phpDocParamsByName[$info['name']] = $info;
        }
        $params = $this->getParamsReflection($refMethod, $phpDocParamsByName);
        /*
            Iterate over params only defined via phpDoc
        */
        $phpDocCount = \count($phpDocParams);
        for ($i = \count($params); $i < $phpDocCount; $i++) {
            $phpDocParam = $phpDoc['param'][$i];
            $params[] = $this->buildValues(array(
                'defaultValue' => $this->phpDocParamValue($phpDocParam, $this->abs['className']),
                'desc' => $phpDocParam['desc'],
                'isOptional' => true,
                'isVariadic' => $phpDocParam['isVariadic'],
                'name' => $phpDocParam['name'],
                'type' => $phpDocParam['type'],
            ));
        }
        $this->abs = null;
        return $params;
    }

    /**
     * Get parameter details for phpDoc @method param
     *
     * @param Abstraction $abs             Object Abstraction instance
     * @param array       $parsedMethodTag Parsed @method tag
     * @param string      $className       Classname where @method tag found
     *
     * @return array
     */
    public function getParamsPhpDoc(Abstraction $abs, $parsedMethodTag, $className)
    {
        $this->abs = $abs;
        return \array_map(function ($phpDocParam) use ($className) {
            return $this->buildValues(array(
                'defaultValue' => $this->phpDocParamValue($phpDocParam, $className),
                'name' => $phpDocParam['name'],
                'type' => $phpDocParam['type'],
            ));
        }, $parsedMethodTag['param']);
    }

    /**
     * Get parameter info from reflection
     *
     * @param ReflectionMethod $refMethod          ReflectionMethod instance
     * @param array            $phpDocParamsByName Method's parsed phpDoc comment
     *
     * @return array
     */
    private function getParamsReflection(ReflectionMethod $refMethod, array $phpDocParamsByName)
    {
        $params = array();
        $collectAttribute = $this->abs['cfgFlags'] & AbstractObject::PARAM_ATTRIBUTE_COLLECT;
        \set_error_handler(static function () {
            // suppressing "Use of undefined constant STDERR" type notice
            // encountered on
            //    $refParameter->getDefaultValue()
            //    $refParameter->__toString()
        });
        foreach ($refMethod->getParameters() as $refParameter) {
            $name = $refParameter->getName();
            $phpDocParam = $this->phpDocParam($name, $phpDocParamsByName);
            $params[] = $this->buildValues(array(
                'attributes' => $collectAttribute
                    ? $this->helper->getAttributes($refParameter)
                    : array(),
                'defaultValue' => $this->getParamDefaultVal($refParameter),
                'desc' => $phpDocParam['desc'],
                'isOptional' => $refParameter->isOptional(),
                'isPassedByReference' => $refParameter->isPassedByReference(),
                'isPromoted' =>  PHP_VERSION_ID >= 80000 && $refParameter->isPromoted(),
                'isVariadic' => PHP_VERSION_ID >= 50600
                    ? $refParameter->isVariadic() || $phpDocParam['isVariadic']
                    : $phpDocParam['isVariadic'],
                'name' => $name,
                'type' => $this->helper->getType($phpDocParam['type'], $refParameter),
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
            if ($defaultValue instanceof UnitEnum) {
                $defaultValue = $this->abstracter->crate($defaultValue, $this->abs['debugMethod']);
            } elseif (PHP_VERSION_ID >= 50406 && $refParameter->isDefaultValueConstant()) {
                $defaultValue = new Abstraction(Type::TYPE_IDENTIFIER, array(
                    'backedValue' => $defaultValue,
                    'typeMore' => Type::TYPE_IDENTIFIER_CONST,
                    'value' => $this->getConstantName($refParameter),
                ));
            }
        }
        return $defaultValue;
    }

    /**
     * Get param's default value constant name
     *
     * @param ReflectionParameter $refParameter reflectionParameter
     *
     * @return string
     */
    private function getConstantName(ReflectionParameter $refParameter)
    {
        // getDefaultValueConstantName() :
        //    php may return something like self::CONSTANT_NAME
        $name = $refParameter->getDefaultValueConstantName();
        if (\preg_match('/^(?!.*::).*\\\\.*$/u', $name) && \defined($name) === false) {
            // constant name includes "\", but does not include "::" and is not defined
            // @see https://bugs.php.net/bug.php?id=73632
            $index = \strrpos($name, '\\');
            $name = \substr($name, $index + 1);
        }
        return $name;
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
    private function phpDocConstant($defaultValue, $className, array $matches)
    {
        if ($matches['classname'] === 'self' && \defined($className . '::' . $matches['const'])) {
            return new Abstraction(Type::TYPE_IDENTIFIER, array(
                'backedValue' => \constant($className . '::' . $matches['const']),
                'typeMore' => Type::TYPE_IDENTIFIER_CONST,
                'value' => $defaultValue, // constant name
            ));
        }
        if (\defined($defaultValue)) {
            return new Abstraction(Type::TYPE_IDENTIFIER, array(
                'backedValue' => \constant($defaultValue),
                'typeMore' => Type::TYPE_IDENTIFIER_CONST,
                'value' => $defaultValue, // constant name
            ));
        }
        return $defaultValue;
    }

    /**
     * Get PhpDoc param info
     *
     * @param string $name               Param name
     * @param array  $phpDocParamsByName Params defined in PhpDoc
     *
     * @return array
     */
    private function phpDocParam($name, array $phpDocParamsByName)
    {
        return \array_merge(array(
            'desc' => '',
            'isVariadic' => false,
            'type' => null,
        ), isset($phpDocParamsByName[$name]) ? $phpDocParamsByName[$name] : array());
    }

    /**
     * Get defaultValue from phpDoc param
     *
     * Converts the defaultValue string to php scalar
     *
     * @param array  $param     parsed param from @method tag
     * @param string $className className where phpDoc was found
     *
     * @return mixed
     */
    private function phpDocParamValue(array $param, $className = null)
    {
        if (\array_key_exists('defaultValue', $param) === false) {
            return Abstracter::UNDEFINED;
        }
        $defaultValue = $param['defaultValue'];
        if (\in_array($defaultValue, ['true', 'false', 'null'], true)) {
            return \json_decode($defaultValue);
        }
        if (\is_numeric($defaultValue)) {
            // there are no quotes around value
            return $defaultValue * 1;
        }
        if (\preg_match('/^(array\(\s*\)|\[\s*\])$/i', $defaultValue)) {
            // empty array...
            // we're not going to eval non-empty arrays...
            //    non empty array will appear as a string
            return array();
        }
        $matches = array();
        if (\preg_match('/^(:?(?P<classname>\S+)::)?(?P<const>[^()\[\]\'"]+)$/i', $defaultValue, $matches)) {
            // appears to be a constant
            return $this->phpDocConstant($defaultValue, $className, $matches);
        }
        return \trim($defaultValue, '\'"');
    }
}
