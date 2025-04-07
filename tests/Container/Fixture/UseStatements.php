<?php

namespace bdk\Test\Container\Fixture;

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

class UseStatements
{
}
