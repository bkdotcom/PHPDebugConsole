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
use ReflectionMethod;
use ReflectionParameter;
use UnitEnum;

/**
 * Get method parameter info
 */
class AbstractObjectMethodParams
{
    protected $abs;
    protected $abstracter;
    protected $helper;

    private static $baseParamInfo = array(
        'attributes' => array(),
        'defaultValue' => Abstracter::UNDEFINED,
        'desc' => null,
        'isOptional' => false,
        'isPromoted' => false,
        'name' => '',
        'type' => null,
    );

    /**
     * Constructor
     *
     * @param Abstracter           $abstracter Abstracter
     * @param AbstractObjectHelper $helper     helper class
     */
    public function __construct(Abstracter $abstracter, AbstractObjectHelper $helper)
    {
        $this->abstracter = $abstracter;
        $this->helper = $helper;
    }

    /**
     * Return method info array
     *
     * @param array $values values to apply
     *
     * @return array
     */
    public static function buildParamValues($values = array())
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
        $params = $this->getParamsReflection($refMethod, $phpDoc);
        /*
            Iterate over params only defined via phpDoc
        */
        $phpDocCount = isset($phpDoc['param'])
            ? \count($phpDoc['param'])
            : 0;
        for ($i = \count($params); $i < $phpDocCount; $i++) {
            $phpDocParam = $phpDoc['param'][$i];
            $name = '$' . \ltrim($phpDocParam['name'], '$');
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
            return $this->buildParamValues(array(
                'defaultValue' => $this->phpDocParamValue($phpDocParam, $className),
                'name' => $phpDocParam['name'],
                'type' => $this->helper->resolvePhpDocType($phpDocParam['type'], $this->abs),
            ));
        }, $parsedMethodTag['param']);
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
        $collectAttribute = $this->abs['cfgFlags'] & AbstractObject::PARAM_ATTRIBUTE_COLLECT;
        \set_error_handler(static function () {
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
                'attributes' => $collectAttribute
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
            if ($defaultValue instanceof UnitEnum) {
                $defaultValue = $this->abstracter->crate($defaultValue, $this->abs['debugMethod']);
            } elseif (PHP_VERSION_ID >= 50406 && $refParameter->isDefaultValueConstant()) {
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
     * Get defaultValue from phpDoc param
     *
     * Converts the defaultValue string to php scalar
     *
     * @param array  $param     parsed param from @method tag
     * @param string $className className where phpDoc was found
     *
     * @return mixed
     */
    private function phpDocParamValue($param, $className = null)
    {
        if (\array_key_exists('defaultValue', $param) === false) {
            return Abstracter::UNDEFINED;
        }
        $defaultValue = $param['defaultValue'];
        if (\in_array($defaultValue, array('true','false','null'), true)) {
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
}
