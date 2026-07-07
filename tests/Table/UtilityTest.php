<?php

namespace bdk\Test\Table;

use bdk\Table\Factory;
use bdk\Table\Utility;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for bdk\Table\Utility
 *
 * @covers \bdk\Table\Utility
 */
class UtilityTest extends TestCase
{
    /**
     * Test an empty table is exported as an empty array.
     *
     * @return void
     */
    public function testEmptyTable(): void
    {
        $table = (new Factory())->create(array());

        self::assertSame(array(), Utility::asArray($table));
    }

    /**
     * Test structured rows and their original keys are preserved.
     *
     * @return void
     */
    public function testStructuredRows(): void
    {
        $data = array(
            'first' => array('name' => 'Ada', 'role' => 'admin'),
            'second' => array('name' => 'Grace', 'role' => 'user'),
        );
        $table = (new Factory())->create($data);

        self::assertSame($data, Utility::asArray($table));
    }

    /**
     * Test single scalar columns are collapsed by default.
     *
     * @return void
     */
    public function testScalarRows(): void
    {
        $data = array(10, 'ten', null, false);
        $table = (new Factory())->create($data);

        self::assertSame($data, Utility::asArray($table));
    }

    /**
     * Test the forceArray option prevents scalar row collapsing.
     *
     * @return void
     */
    public function testForceArray(): void
    {
        $table = (new Factory())->create(array(
            'first' => 10,
            'second' => 20,
        ));

        self::assertSame(array(
            'first' => array('value' => 10),
            'second' => array('value' => 20),
        ), Utility::asArray($table, array('forceArray' => true)));
    }

    /**
     * Test undefined cells are omitted by default.
     *
     * @return void
     */
    public function testUndefinedCellsAreOmitted(): void
    {
        $data = array(
            array('alpha' => 1),
            array('beta' => 2),
        );
        $table = (new Factory())->create($data);

        self::assertSame($data, Utility::asArray($table));
    }

    /**
     * Test undefined cells can be represented by a configured value.
     *
     * @return void
     */
    public function testUndefinedAsOption(): void
    {
        $table = (new Factory())->create(array(
            array('alpha' => 1),
            array('beta' => 2),
        ));

        self::assertSame(array(
            array('beta' => null, 'alpha' => 1),
            array('beta' => 2, 'alpha' => null),
        ), Utility::asArray($table, array('undefinedAs' => null)));

        self::assertSame(array(
            array('beta' => Factory::VAL_UNDEFINED, 'alpha' => 1),
            array('beta' => 2, 'alpha' => Factory::VAL_UNDEFINED),
        ), Utility::asArray($table, array('undefinedAs' => Factory::VAL_UNDEFINED)));
    }
}
