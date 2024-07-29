<?php

namespace bdk\Test\Debug\Fixture;

const NAMESPACE_CONST = 'foo';

class ParamConstants
{
    const CLASS_CONST = 'bar';

    public function test($foo = self::CLASS_CONST, $bar = SEEK_SET, $baz = NAMESPACE_CONST, $biz = TestBase::MY_CONSTANT)
    {
        return $foo . $bar . $baz . $biz;
    }
}
