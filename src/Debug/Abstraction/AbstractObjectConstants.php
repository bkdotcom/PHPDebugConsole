<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\AbstractObjectHelper;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Get object constant info
 */
class AbstractObjectConstants
{

    protected $abstracter;
    protected $helper;

    private $constants = array();
    private $inclAttributes = true;
    private $inclPhpDoc = true;

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
     * @param Abstraction $abs Object abstraction
     *
     * @return void
     */
    public function add(Abstraction $abs)
    {
        if ($abs['isTraverseOnly']) {
            return;
        }
        if (!($abs['cfgFlags'] & AbstractObject::COLLECT_CONSTANTS)) {
            return;
        }
        $this->constants = array();
        $this->inclAttributes = ($abs['cfgFlags'] & AbstractObject::COLLECT_ATTRIBUTES_CONST) === AbstractObject::COLLECT_ATTRIBUTES_CONST;
        $this->inclPhpDoc = ($abs['cfgFlags'] & AbstractObject::COLLECT_PHPDOC) === AbstractObject::COLLECT_PHPDOC;
        $reflector = $abs['reflector'];
        while ($reflector) {
            PHP_VERSION_ID >= 70100
                ? $this->addConstantsReflection($reflector)
                : $this->addConstants($reflector);
            $reflector = $reflector->getParentClass();
        }
        $abs['constants'] = $this->constants;
    }

    /**
     * Get constant arrays
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
     * Get constant arrays via `getReflectionConstants` (php 7.1)
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
            $this->constants[$name] = $this->getConstantReflection($refConstant);
        }
    }

    /**
     * Get constant info via ReflectionClassConstant
     *
     * @param ReflectionClassConstant $refConstant ReflectionClassConstant instance
     *
     * @return array
     */
    private function getConstantReflection(ReflectionClassConstant $refConstant)
    {
        return array(
            'attributes' => $this->inclAttributes
                ? $this->helper->getAttributes($refConstant)
                : array(),
            'desc' => $this->inclPhpDoc
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
