<?php
/**
 * PHP 8.1 "first class callable" syntax
 */

class Foo {
    public function __invoke()
    {
    }
    public function method()
    {
    }
    public static function staticmethod()
    {
    }
}

$obj = new Foo();
$classStr = 'Foo';
$methodStr = 'method';
$staticmethodStr = 'staticmethod';

return array(
    'func' => \strlen(...),
    'invokable' => $obj(...),
    'objMethod' => $obj->method(...),
    'objMethodStr' => $obj->$methodStr(...),
    'staticMethod' => Foo::staticmethod(...),
    'staticMethodStr' => $classStr::$staticmethodStr(...),

    'funcStr' => 'strlen'(...),
    'arrayObj' => [$obj, 'method'](...),
    'arrayClassname' => [Foo::class, 'staticmethod'](...),
    'arrayClassname2' => ['Foo', 'staticmethod'](...),
);
