<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\Fact;
use bdk\Teams\Elements\FactSet;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\FactSet
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
 */
class FactSetTest extends AbstractTestCaseWith
{
    public function testConstruct()
    {
        $factSet = new FactSet(array(
            'foo' => 'bar',
            new Fact('zip', 'zap'),
        ));
        self::assertSame(array(
            'type' => 'FactSet',
            'facts' => array(
                array(
                    'title' => 'foo',
                    'value' => 'bar',
                ),
                array(
                    'title' => 'zip',
                    'value' => 'zap',
                ),
            ),
        ), $factSet->getContent(1.2));
    }

    protected static function itemFactory()
    {
        return new FactSet();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedFact', [new Fact('title1', 'value1')], false, null, static function (FactSet $factSet) {
                $factSet = $factSet->withAddedFact(new Fact('title2', 'value2'));
                self::assertSame(array(
                    'type' => 'FactSet',
                    'facts' => array(
                        array(
                            'title' => 'title1',
                            'value' => 'value1',
                        ),
                        array(
                            'title' => 'title2',
                            'value' => 'value2',
                        ),
                    ),
                ), $factSet->getContent(1.2));
            }),
            array('facts', [array(
                'title1' => 'value1',
                new Fact('title2', 'value2'),
            )], false, null, static function (FactSet $factSet) {
                $factSet = $factSet->withFacts(array(
                    new Fact('title3', 'value3'),
                ));
                self::assertSame(array(
                    'type' => 'FactSet',
                    'facts' => array(
                        array(
                            'title' => 'title3',
                            'value' => 'value3',
                        ),
                    ),
                ), $factSet->getContent(1.2));
            }),
            array('facts', [[['bogus']]], true, 'Invalid Fact or value encountered at 0. Expected Fact, string, or numeric. array provided.'),
        );
    }
}
