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

use bdk\Debug\Abstraction\Abstraction;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use UnitEnum;

/**
 * Get object constant info
 */
class AbstractObjectConstants
{
    protected $abstracter;
    protected $helper;

    private $constants = array();
    private $attributeCollect = true;
    private $phpDocCollect = true;

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
     * Add object's constants to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function add(Abstraction $abs)
    {
        if (!($abs['cfgFlags'] & AbstractObject::CONST_COLLECT)) {
            return;
        }
        $this->constants = array();
        $this->attributeCollect = ($abs['cfgFlags'] & AbstractObject::CONST_ATTRIBUTE_COLLECT) === AbstractObject::CONST_ATTRIBUTE_COLLECT;
        $this->phpDocCollect = ($abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT) === AbstractObject::PHPDOC_COLLECT;
        $reflector = $abs['reflector'];
        while ($reflector) {
            PHP_VERSION_ID >= 70100
                ? $this->addConstantsReflection($reflector)
                : $this->addConstantsLegacy($reflector);
            $reflector = $reflector->getParentClass();
        }
        $abs['constants'] = $this->constants;
    }

    /**
     * Add enum's cases to abstraction
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function addCases(Abstraction $abs)
    {
        if (!($abs['cfgFlags'] & AbstractObject::CASE_COLLECT)) {
            return;
        }
        if ($abs->getSubject() instanceof UnitEnum === false) {
            return;
        }
        $this->attributeCollect = ($abs['cfgFlags'] & AbstractObject::CASE_ATTRIBUTE_COLLECT) === AbstractObject::CASE_ATTRIBUTE_COLLECT;
        $this->phpDocCollect = ($abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT) === AbstractObject::PHPDOC_COLLECT;
        $cases = array();
        foreach ($abs['reflector']->getCases() as $refCase) {
            $name = $refCase->getName();
            $cases[$name] = $this->getCaseRefInfo($refCase);
        }
        $abs['cases'] = $cases;
    }

    /**
     * Collect constant values that are Enums
     *
     * attempting to do this at the class level leads to recursion
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function addConstantEnumValues(Abstraction $abs)
    {
        if (PHP_VERSION_ID < 80100) {
            return;
        }
        $reflector = $abs['reflector'];
        foreach ($reflector->getReflectionConstants() as $refConstant) {
            $value = $refConstant->getValue();
            if (!($value instanceof UnitEnum) || $refConstant->isEnumCase()) {
                continue;
            }
            $name = $refConstant->getName();
            $abs['constants'][$name]['value'] = $this->abstracter->crate($value, $abs['debugMethod'], $abs['hist']);
        }
    }

    /**
     * Get constants (php < 7.1)
     *
     * @param ReflectionClass $reflector ReflectionClass instance
     *
     * @return void
     */
    private function addConstantsLegacy(ReflectionClass $reflector)
    {
        foreach ($reflector->getConstants() as $name => $value) {
            if (isset($this->constants[$name])) {
                continue;
            }
            $this->constants[$name] = array(
                'attributes' => array(),
                'desc' => null,
                'isFinal' => false,
                'value' => $value,
                'visibility' => 'public',
            );
        }
    }

    /**
     * Get constant via `getReflectionConstants` (php >= 7.1)
     * This gets us visibility and access to phpDoc
     *
     * @param ReflectionClass $reflector ReflectionClass instance
     *
     * @return void
     */
    private function addConstantsReflection(ReflectionClass $reflector)
    {
        foreach ($reflector->getReflectionConstants() as $refConstant) {
            $name = $refConstant->getName();
            if (isset($this->constants[$name])) {
                continue;
            }
            if (PHP_VERSION_ID >= 80100 && $refConstant->isEnumCase()) {
                // getReflectionConstants also returns enum cases... which we don't want
                continue;
            }
            $this->constants[$name] = $this->getConstantRefInfo($refConstant);
        }
    }

    /**
     * Get Enum case info
     *
     * @param ReflectionEnumUnitCase $refCase ReflectionEnumUnitCase instance
     *
     * @return array
     */
    private function getCaseRefInfo(ReflectionEnumUnitCase $refCase)
    {
        return array(
            'attributes' => $this->attributeCollect
                ? $this->helper->getAttributes($refCase)
                : array(),
            'desc' => $this->phpDocCollect
                ? $this->helper->getPhpDocVar($refCase)['desc']
                : null,
            'isFinal' => $refCase->isFinal(),
            'value' => $refCase instanceof ReflectionEnumBackedCase
                ? $refCase->getBackingValue()
                : null,
            'visibility' => $this->helper->getVisibility($refCase),
        );
    }

    /**
     * Get constant info
     *
     * @param ReflectionClassConstant $refConstant ReflectionClassConstant instance
     *
     * @return array
     */
    private function getConstantRefInfo(ReflectionClassConstant $refConstant)
    {
        $value = $refConstant->getValue();
        if ($value instanceof UnitEnum) {
            // storing enum value at class level leads to recursion
            $value = null;
        }
        return array(
            'attributes' => $this->attributeCollect
                ? $this->helper->getAttributes($refConstant)
                : array(),
            'declaredLast' => $this->helper->getClassName($refConstant->getDeclaringClass()),
            'desc' => $this->phpDocCollect
                ? $this->helper->getPhpDocVar($refConstant)['desc']
                : null,
            'isFinal' => PHP_VERSION_ID >= 80100
                ? $refConstant->isFinal()
                : false,
            'value' => $value,
            'visibility' => $this->helper->getVisibility($refConstant),
        );
    }
}
