<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\UseStatements;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 */
class UseStatementsTest extends TestCase
{
    public function testUseStatements()
    {
        $source = '<?php
        namespace foo;
        use My\Full\Classname as Another;

        // this is the same as use My\Full\NSname as NSname
        use My\Full\NSname;

        // importing a global class
        use ArrayObject;

        // importing a function (PHP 5.6+)
        use function My\Full\functionName;

        // aliasing a function (PHP 5.6+)
        use function My\Full\functionName as func;

        // importing a constant (PHP 5.6+)
        use const My\Full\CONSTANT;

        use My\Full\Classname2 as Another2, My\Full\NSname2;

        use some\nspace\{ClassA, ClassB, ClassC as C};      // PHP 7+
        use some\nspace\{ClassD};                           // PHP 7+
        use function some\nspace\{fn_a, fn_b, fn_c};        // PHP 7+
        use const some\nspace\{ConstA, ConstB, ConstC};     // PHP 7+
        ';

        $useStatements = UseStatements::extractUse($source);

        $jsonExpect = '{
    "foo": {
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
    }
}';
        $expect = \json_decode($jsonExpect, true);
        $this->assertSame(
            \json_encode($expect, JSON_PRETTY_PRINT),
            \json_encode($useStatements, JSON_PRETTY_PRINT)
        );
    }
}
