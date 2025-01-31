<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Sql;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\Fixture\TestObj;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Debug\Utility\Sql
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class SqlTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    public function testReplaceParamsAssoc()
    {
        $sql = 'SELECT * FROM table WHERE is_active = :active and count > :count and type in(:types) and created >= :datetime';
        $datetime = new \DateTime('2020-01-01 12:34:56');
        $replaced = Sql::replaceParams($sql, array(
            ':active' => true,
            ':count' => 10,
            ':datetime' => $datetime,
            ':types' => array('big', 'shiney'),
        ));
        $expect = 'SELECT * FROM table WHERE is_active = 1 and count > 10 and type in(\'big\', \'shiney\') and created >= \'' . $datetime->format(\DateTime::ISO8601) . '\'';
        self::assertSame($expect, $replaced);
    }

    public function testReplaceParamsListMismatch()
    {
        $sql = 'SELECT * FROM table WHERE is_active = ? and count > ? and type in(?) and created >= ?';
        $replaced = Sql::replaceParams($sql, array(
            true,
        ));
        self::assertSame($sql, $replaced);
    }
}
