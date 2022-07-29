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
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;

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
     * @param Abstracter           $abstracter abstracter instance
     * @param AbstractObjectHelper $helper     helper class
     */
    public function __construct(Abstracter $abstracter, AbstractObjectHelper $helper)
    {
        $this->abstracter = $abstracter;
        $this->helper = $helper;
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
        if ($abs['isTraverseOnly']) {
            return;
        }
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
                : $this->addConstants($reflector);
            $reflector = $reflector->getParentClass();
        }
        foreach ($this->constants as $name => $info) {
            // crate constant values to catch recursion
            $this->constants[$name]['value'] = $this->abstracter->crate($info['value'], $abs['debugMethod'], $abs['hist']);
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
    public function addCases($abs)
    {
        $abs['cases'] = array();
        if ($abs['isTraverseOnly']) {
            return;
        }
        if (!($abs['cfgFlags'] & AbstractObject::CASE_COLLECT)) {
            return;
        }
        if (\in_array('UnitEnum', $abs['implements'], true) === false) {
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
     * Get constants (php < 7.1)
     *
     * @param ReflectionClass $reflector ReflectionClass instance
     *
     * @return void
     */
    private function addConstants(ReflectionClass $reflector)
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
            'value' => $refCase instanceof ReflectionEnumBackedCase
                ? $refCase->getBackingValue()
                : null,
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
        return array(
            'attributes' => $this->attributeCollect
                ? $this->helper->getAttributes($refConstant)
                : array(),
            'desc' => $this->phpDocCollect
                ? $this->helper->getPhpDocVar($refConstant)['desc']
                : null,
            'isFinal' => PHP_VERSION_ID >= 80100
                ? $refConstant->isFinal()
                : false,
            'value' => $refConstant->getValue(),
            'visibility' => $this->helper->getVisibility($refConstant),
        );
    }
}
