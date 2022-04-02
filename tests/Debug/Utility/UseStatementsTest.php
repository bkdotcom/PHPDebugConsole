<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\UseStatements;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Utility\UseStatements
 */
class UseStatementsTest extends TestCase
{
    public function testUseStatements()
    {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped('Use statement curly bracket syntax requires php 7.0+');
        }

        $reflectionClass = new \ReflectionClass('bdk\Test\Debug\Fixture\UseStatements');
        $useStatements = UseStatements::getUseStatements($reflectionClass);

        $jsonExpect = '{
    "class": {
        "Another": "My\\\Full\\\Classname",
        "Another2": "My\\\Full\\\Classname2",
        "ArrayObject": "ArrayObject",
        "C": "some\\\nspace\\\ClassC",
        "ClassA": "some\\\nspace\\\ClassA",
        "ClassB": "some\\\nspace\\\ClassB",
        "ClassD": "some\\\nspace\\\ClassD",
        "NSname": "My\\\Full\\\NSname",
        "NSname2": "My\\\Full\\\NSname2"
    },
    "const": {
        "CONSTANT": "My\\\Full\\\CONSTANT",
        "ConstA": "some\\\nspace\\\ConstA",
        "ConstB": "some\\\nspace\\\ConstB",
        "ConstC": "some\\\nspace\\\ConstC"
    },
    "function": {
        "fn_a": "some\\\nspace\\\fn_a",
        "fn_b": "some\\\nspace\\\fn_b",
        "fn_c": "some\\\nspace\\\fn_c",
        "func": "My\\\Full\\\functionName",
        "functionName": "My\\\Full\\\functionName"
    }
}';
        $this->assertSame(
            $jsonExpect,
            \json_encode($useStatements, JSON_PRETTY_PRINT)
        );

        // test cache
        $useStatements = UseStatements::getUseStatements($reflectionClass);
        $this->assertSame(
            $jsonExpect,
            \json_encode($useStatements, JSON_PRETTY_PRINT)
        );
    }

    public function testNotUserDefined()
    {
        $reflectionClass = new \ReflectionClass('PDO');
        $useStatements = UseStatements::getUseStatements($reflectionClass);
        $this->assertSame(
            array(
                'class' => array(),
                'const' => array(),
                'function' => array(),
            ),
            $useStatements
        );
    }

    public function testNoStatements()
    {
        $reflectionClass = new \ReflectionClass('bdk\Test\Debug\Fixture\Test2');
        $useStatements = UseStatements::getUseStatements($reflectionClass);
        $this->assertSame(
            array(
                'class' => array(),
                'const' => array(),
                'function' => array(),
            ),
            $useStatements
        );
    }
}
