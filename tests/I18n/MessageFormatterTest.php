<?php

/*
 * Copyright © 2008 by Yii Software LLC (http://www.yiisoft.com)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *  * Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *  * Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *  * Neither the name of Yii Software LLC nor the names of its
 *    contributors may be used to endorse or promote products derived
 *    from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * Originally forked from
 * https://github.com/yiisoft/yii2/blob/2.0.15/tests/framework/i18n/FallbackMessageFormatterTest.php
 */

namespace bdk\Test\I18n;

use bdk\I18n\MessageFormatter;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 *
 * @covers bdk\I18n\MessageFormatter
 */
class MessageFormatterTest extends TestCase
{
    use ExpectExceptionTrait;

    public function testConstructorException()
    {
        $this->expectException('DomainException');
        $this->expectExceptionMessage('Message pattern is invalid.');
        new MessageFormatter('en_US', 'Unmatched {braces');
    }

    public function testFormatMessageError()
    {
        $msg = MessageFormatter::formatMessage('en_US', 'Unmatched {braces', []);
        self::assertFalse($msg);
    }

    public function testGetLocale()
    {
        $formatter = MessageFormatter::create('es_MX', '{n, number}');
        self::assertSame('es_MX', $formatter->getLocale());
    }

    public function testGetPattern()
    {
        $pattern = '{n, number, percent}';
        $formatter = MessageFormatter::create('es_MX', $pattern);
        self::assertSame($pattern, $formatter->getPattern());
    }

    /**
     * @dataProvider providerFormat
     */
    public function testFormat($pattern, array $args, $expected, $errorMessage = '')
    {
        $locale = 'en_US';
        $formatter = new MessageFormatter($locale, $pattern);
        $result = $formatter->format($args);
        self::assertSame($expected, $result, $formatter->getErrorMessage());

        $result = MessageFormatter::formatMessage($locale, $pattern, $args);
        self::assertSame($expected, $result);

        if ($errorMessage) {
            self::assertSame(-1, $formatter->getErrorCode());
            self::assertSame($errorMessage, $formatter->getErrorMessage());
        }
    }

    /*
    public function testFormatError()
    {
        // $this->expectException('DomainException');
        // $this->expectExceptionMessage('Message pattern is invalid.');
        $formatter = new MessageFormatter('en_US', 'Foo {bar, bogus}');
        $return = $formatter->format(['bar' => 'baz']);
        self::assertFalse($return);
        self::assertSame(-1, $formatter->getErrorCode());
        self::assertSame('Unsupported bogus format and/or the PHP intl extension is required.', $formatter->getErrorMessage());
    }
    */

    /*
    public function testFormatInsufficientArguments()
    {
        $pattern = '{subject} is {n}';

        $formatter = new MessageFormatter('en_US', $pattern);
        $result = $formatter->format(['n' => 42]);
        $this->assertEquals('{subject} is 42', $result);
    }
    */

    public function testFormatNoParams()
    {
        $pattern = '{subject} is {n}';

        $formatter = new MessageFormatter('en_US', $pattern);
        $result = $formatter->format([]);
        $this->assertEquals($pattern, $result, $formatter->getErrorMessage());
    }

    public function testParse()
    {
        $messageFormatter = new MessageFormatter('en_US', '{n, number}');
        $return = $messageFormatter->parse('some string');
        self::assertFalse($return);
        self::assertSame(-1, $messageFormatter->getErrorCode());
        self::assertSame('The PHP intl extension is required to use "MessageFormatter::parse()".', $messageFormatter->getErrorMessage());
    }

    public function testParseMessage()
    {
        $return = MessageFormatter::parseMessage(
            'en_US',
            '{0,number,integer} monkeys on {1,number,integer} trees make {2,number} monkeys per tree',
            '4,560 monkeys on 123 trees make 37.073 monkeys per tree'
        );
        self::assertFalse($return);
    }

    public function testGridViewMessage()
    {
        $pattern = 'Showing <b>{begin, number}-{end, number}</b> of <b>{totalCount, number}</b> {totalCount, plural, one{item} other{items}}.';

        $formatter = new MessageFormatter('en_US', $pattern);
        $result = $formatter->format(['begin' => 1, 'end' => 5, 'totalCount' => 10]);
        $this->assertEquals('Showing <b>1-5</b> of <b>10</b> items.', $result);
    }

    /*
    public function testUnsupportedCurrencyException()
    {
        $pattern = 'Number {n, number, currency}';
        $formatter = new MessageFormatter('en-US', $pattern);
        $this->assertFalse($formatter->format(['n' => 42]));
    }
    */

    public static function providerFormat()
    {
        $subject = 'Answer to the Ultimate Question of Life, the Universe, and Everything';

        return [
            'basic' => [
                '{subject} is {n}',
                [
                    'n' => 42,
                    'subject' => $subject,
                ],
                $subject . ' is 42', // expected
            ],

            'number' => [
                '{subject} is {n, number}',
                [
                    'n' => 42,
                    'subject' => $subject,
                ],
                $subject . ' is 42', // expected
            ],

            'number.integer' => [
                '{subject} is {n, number, integer}',
                [
                    'n' => 42,
                    'subject' => $subject,
                ],
                $subject . ' is 42',
            ],

            'number.big' => [
                'Here is a big number: {f, number}',
                [
                    'f' => 2e+8,
                ],
                'Here is a big number: 200,000,000',
            ],

            'number.big.integer' => [
                'Here is a big number: {f, number, integer}',
                [
                    'f' => 2e+8,
                ],
                'Here is a big number: 200,000,000',
            ],

            'number.big.decimal' => [
                'Here is a big number: {d, number}',
                [
                    'd' => 200000000.101,
                ],
                'Here is a big number: 200,000,000.101',
            ],

            'number.big.toInteger' => [
                'Here is a big number: {d, number, integer}',
                [
                    'd' => 200000000.101,
                ],
                'Here is a big number: 200,000,000',
            ],

            'pattern.escaped' => [
                'This \'{isn\'\'t} very\' obvious { beans, number, integer } ain\'t good!',
                [
                    'beans' => '33.33',
                    'isn\'t' => 'test 1',
                    'isn\'\'t' => 'test 2',
                ],
                'This {isn\'t} very obvious 33 ain\'t good!',
            ],

            'select n plural' => [
                '{eye_color_of_host, select,
                brown {{num_guests, plural, offset:1
                    =0 {{host} has brown eyes and does not give a party.}
                    =1 {{host} has brown eyes and invites {guest} to their party.}
                    =2 {{host} has brown eyes and invites {guest} and one other person to their party.}
                    other {{host} has brown eyes and invites {guest} and # other people to their party.}}}
                green {{num_guests, plural, offset:1
                    =0 {{host} has green eyes and does not give a party.}
                    =1 {{host} has green eyes and invites {guest} to their party.}
                    =2 {{host} has green eyes and invites {guest} and one other person to their party.}
                    other {{host} has green eyes and invites {guest} and # other people to their party.}}}
                other {{num_guests, plural, offset:1
                    =0 {{host} has pretty eyes and does not give a party.}
                    =1 {{host} has pretty eyes and invites {guest} to their party.}
                    =2 {{host} has pretty eyes and invites {guest} and one other person to their party.}
                    other {{host} has pretty eyes and invites {guest} and # other people to their party.}}}}',
                [
                    'eye_color_of_host' => 'brown',
                    'num_guests' => 4,
                    'host' => 'Alex',
                    'guest' => 'Riley',
                ],
                'Alex has brown eyes and invites Riley and 3 other people to their party.',
            ],

            'select 2' => [
                '{name} has {eye_color} eyes like {eye_color, select, brown{wood} green{grass} other{a bird}}!',
                [
                    'name' => 'Alex',
                    'eye_color' => 'brown',
                ],
                'Alex has brown eyes like wood!',
            ],

            // some parser specific verifications
            'select.3' => [
                'Alex has {eye_color} eyes like {eye_color, select, brown{{wood}} other{a bird}} and loves {number}!',
                [
                    'number' => 42,
                    'eye_color' => 'brown',
                    'wood' => 'bears',
                    'grass' => 'plants',
                ],
                'Alex has brown eyes like bears and loves 42!',
            ],

            // verify pattern in select does not get replaced
            'select.notReplaced' => [
                '{name} has {eye_color} eyes like {eye_color, select, brown{wood} green{grass} other{a bird}}!',
                [
                    'name' => 'Alex',
                    'eye_color' => 'blue',
                    // following should not be replaced
                    'wood' => 'nothing',
                    'grass' => 'nothing',
                    'a bird' => 'nothing',
                ],
                'Alex has blue eyes like a bird!',
            ],

            // verify pattern in select message gets replaced
            'select.replacement' => [
                '{name} has {eye_color} eyes like {eye_color, select, brown{{wood}} green{grass} other{a bird}}!',
                [
                    'name' => 'Alex',
                    'eye_color' => 'brown',
                    'wood' => 'bears',
                    'grass' => 'plants',
                    'a bird' => 'the sea',
                ],
                'Alex has brown eyes like bears!',
            ],

            // formatting a message that contains params but they are not provided.
            'missing.args' => [
                'Incorrect password (length must be from { min, number } to { max, number } symbols).',
                ['attribute' => 'password'],
                'Incorrect password (length must be from {min} to {max} symbols).',
            ],

            'number.percent' => [
                'Percent {n, number, percent}',
                [
                    'n' => 0.42,
                ],
                'Percent 42%',
            ],

            'error' => [
                'Foo {bar, bogus}',
                ['bar' => 'baz'],
                false,
                'Unsupported bogus format and/or the PHP intl extension is required.',
            ],

            'plural.not-found' => [
                '{count, plural, zero{no results} one{one result}}',
                ['count' => 42],
                false,
            ],

            'plural.invalid.1' => [
                '{count, plural}',
                ['count' => 1],
                false,
                'Message pattern is invalid.',
            ],

            'plural.invalid.2' => [
                '{count, plural, valid {farts}{invalid} string}',
                ['count' => 1],
                false,
                'Message pattern is invalid.',
            ],

            'select.not-found' => [
                '{part, select, somevalue{hello}}',
                ['part' => 'giblets'],
                false,
            ],

            'select.invalid.1' => [
                '{part, select}',
                ['part' => 'giblets'],
                false,
                'Message pattern is invalid.',
            ],

            'select.invalid.2' => [
                '{part, select, valid{farts}{invalid}string}',
                ['part' => 'giblets'],
                false,
                'Message pattern is invalid.',
            ],
        ];
    }
}
