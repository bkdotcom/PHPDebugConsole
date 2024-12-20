<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Utility\PhpDoc;
use bdk\Debug\Utility\Reflection;
use bdk\PhpUnitPolyfill\AssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Utility\PhpDoc
 * @covers \bdk\Debug\Utility\PhpDoc\Helper
 * @covers \bdk\Debug\Utility\PhpDoc\ParseMethod
 * @covers \bdk\Debug\Utility\PhpDoc\ParseParam
 * @covers \bdk\Debug\Utility\PhpDoc\Parsers
 * @covers \bdk\Debug\Utility\PhpDoc\Type
 */
class PhpDocTest extends TestCase
{
    use AssertionTrait;

    public static function setUpBeforeClass(): void
    {
        \bdk\Debug\Utility\Reflection::propSet('bdk\Debug\Utility\PhpDoc', 'cache', array());
    }

    public function testConstruct()
    {
        $phpDoc = new PhpDoc();
        $parsersObj = \bdk\Debug\Utility\Reflection::propGet($phpDoc, 'parsers');
        $parsers = \bdk\Debug\Utility\Reflection::propGet($parsersObj, 'parsers');
        self::assertIsArray($parsers);
        self::assertNotEmpty($parsers, 'Parsers is empty');
    }

    public function testGetParsedObject()
    {
        $obj = new \bdk\Test\Debug\Fixture\Utility\PhpDocImplements();
        $expectJson = <<<'EOD'
        {
            "author": [
                {
                    "desc": "Author desc is non-standard",
                    "email": "bkfake-github@yahoo.com",
                    "name": "Brad Kent"
                }
            ],
            "desc": "",
            "link": [
                {
                    "desc": "",
                    "uri": "https:\/\/github.com\/bkdotcom\/PHPDebugConsole"
                }
            ],
            "method": [
                {
                    "desc": "I'm a magic method",
                    "name": "presto",
                    "param": [
                        {
                            "isVariadic": false,
                            "name": "foo",
                            "type": null
                        },
                        {
                            "defaultValue": "1",
                            "isVariadic": false,
                            "name": "int",
                            "type": "int"
                        },
                        {
                            "defaultValue": "true",
                            "isVariadic": false,
                            "name": "bool",
                            "type": null
                        },
                        {
                            "defaultValue": "null",
                            "isVariadic": false,
                            "name": "null",
                            "type": null
                        }
                    ],
                    "static": false,
                    "type": "void"
                },
                {
                    "desc": "I'm a static magic method",
                    "name": "prestoStatic",
                    "param": [
                        {
                            "isVariadic": false,
                            "name": "noDefault",
                            "type": "string"
                        },
                        {
                            "defaultValue": "array()",
                            "isVariadic": false,
                            "name": "arr",
                            "type": null
                        },
                        {
                            "defaultValue": "array('a'=>'a\\'y','b'=>'bee')",
                            "isVariadic": false,
                            "name": "opts",
                            "type": null
                        }
                    ],
                    "static": true,
                    "type": "void"
                }
            ],
            "property": [
                {
                    "desc": "I'm avail via __get()",
                    "name": "magicProp",
                    "type": "bool"
                }
            ],
            "property-read": [
                {
                    "desc": "Read Only!",
                    "name": "magicReadProp",
                    "type": "bool"
                }
            ],
            "see": [
                {
                    "desc": "",
                    "fqsen": "subclass::method()",
                    "uri": null
                }
            ],
            "summary": "Implement me!",
            "unknown": [
                {
                    "desc": "Some phpdoc tag"
                }
            ]
        }
EOD;

        $parsed = Debug::getInstance()->phpDoc->getParsed($obj);
        self::assertSame(\json_decode($expectJson, true), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocImplements');
        self::assertSame(\json_decode($expectJson, true), $parsed, true);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends');
        self::assertSame(\json_decode($expectJson, true), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocNoParent');
        self::assertSame(array(
            'desc' => '',
            'summary' => '',
        ), $parsed);
    }

    public static function providerMethod()
    {
        return array(
            'someMethod' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocImplements::someMethod()',
                array(
                    'desc' => 'Tests that self resolves to fully qualified SomeInterface',
                    'return' => array(
                        'desc' => '',
                        'type' => 'bdk\Test\Debug\Fixture\SomeInterface',
                    ),
                    'summary' => 'SomeInterface summary',
                ),
            ),
            'someMethod2' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocImplements::someMethod2()',
                array(
                    'desc' => '',
                    'return' => array(
                        'desc' => '',
                        'type' => 'Ding\\Dang',
                    ),
                    'summary' => 'SomeInterface summary',
                ),
            ),
            'someMethod3' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod3()',
                array(
                    'desc' => 'PhpDocExtends desc / PhpDocImplements desc',
                    'return' => array(
                        'desc' => '',
                        'type' => null,
                    ),
                    'summary' => 'PhpDocExtends summary',
                ),
            ),
            'someMethod4' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod4()',
                array(
                    'desc' => '',
                    'return' => array(
                        'desc' => '',
                        'type' => 'foo\\bar\\baz',
                    ),
                    'summary' => 'Test that baz resolves to foo\bar\baz',
                ),
            ),
            'someMethod5' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod5()',
                array(
                    'desc' => '',
                    'return' => array(
                        'desc' => '',
                        'type' => 'bdk\\Test\\Debug\\Fixture\\TestObj',
                    ),
                    'summary' => 'Test that TestObj resolves to bdk\Test\Debug\Fixture\TestObj',
                ),
            ),
            'noParent' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocNoParent::someMethod()',
                array(
                    'desc' => '',
                    'return' => array(
                        'desc' => '',
                        'type' => null,
                    ),
                    'summary' => '',
                ),
            ),
            'comment (no namespace)' => array(
                '/**
                  * some function
                  * @param stdClass $obj plain ol object
                  */',
                array(
                    'desc' => '',
                    'param' => array(
                        array(
                            'desc' => 'plain ol object',
                            'isVariadic' => false,
                            'name' => 'obj',
                            'type' => 'stdClass',
                        ),
                    ),
                    'return' => array(
                        'desc' => '',
                        'type' => null,
                    ),
                    'summary' => 'some function',
                ),
            ),
        );
    }

    /**
     * @dataProvider providerMethod
     */
    public function testGetParsedMethod($what, $expect)
    {
        $parsed = Debug::getInstance()->phpDoc->getParsed($what, PhpDoc::FULLY_QUALIFY | PhpDoc::FULLY_QUALIFY_AUTOLOAD);
        self::assertSame($expect, $parsed);
    }

    public function testGetParsedProperty()
    {
        $phpDoc = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Test2::$magicReadProp');
        self::assertSame(array(
            'desc' => '',
            'summary' => 'This property is important',
            'var' => array(
                array(
                    'desc' => '',
                    'name' => 'magicReadProp',
                    'type' => 'string',
                ),
            ),
        ), $phpDoc);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocImplements::$someProperty');
        self::assertSame(array(
            'desc' => '',
            'summary' => '$someProperty summary',
            'var' => array(
                array(
                    'desc' => 'desc',
                    'name' => 'someProperty',
                    'type' => 'string',
                ),
            ),
        ), $parsed);
    }

    public function testGetParsedConstant()
    {
        if (PHP_VERSION_ID < 70100) {
            self::markTestSkipped('ReflectionConstant is PHP >= 7.1');
        }
        $reflector = Reflection::getReflector('bdk\Test\Debug\Fixture\Utility\PhpDocExtends::SOME_CONSTANT');
        $parsed = Debug::getInstance()->phpDoc->getParsed($reflector);
        self::assertSame(array(
            'desc' => '',
            'summary' => 'Interface summary',
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::SOME_CONSTANT');
        self::assertSame(array(
            'desc' => '',
            'summary' => 'Interface summary',
        ), $parsed);
    }

    public static function providerStrings()
    {
        $tests = array(
            'basic' => array(
                '/**
                 * @var string $comment phpdoc comment
                 */',
                array(
                    'desc' => '',
                    'summary' => '',
                    'var' => array(
                        array(
                            'desc' => 'phpdoc comment',
                            'name' => 'comment',
                            'type' => 'string',
                        ),
                    ),
                ),
            ),
            'method' => array(
                '/**
                 * @method boolean magicMethod()
                 */',
                array(
                    'desc' => '',
                    'method' => array(
                        array(
                            'desc' => '',
                            'name' => 'magicMethod',
                            'param' => array(),
                            'static' => false,
                            'type' => 'bool',
                        ),
                    ),
                    'summary' => '',
                ),
            ),
            'missing $' => array(
                '/**
                 * @param string | null comment
                 */',
                array(
                    'desc' => '',
                    'param' => array(
                        array(
                            'desc' => '',
                            'isVariadic' => false,
                            'name' => 'comment',
                            'type' => 'string|null',
                        ),
                    ),
                    'return' => array(
                        'desc' => '',
                        'type' => null,
                    ),
                    'summary' => '',
                ),
            ),
            'missing $ 2' => array(
                '/**
                 * @param "some(str\"ing" comment here
                 */',
                array(
                    'desc' => '',
                    'param' => array(
                        array(
                            'desc' => 'comment here',
                            'isVariadic' => false,
                            'name' => null,
                            'type' => '"some(str\"ing"',
                        ),
                    ),
                    'return' => array(
                        'desc' => '',
                        'type' => null,
                    ),
                    'summary' => '',
                ),
            ),
            'multi lines' => array(
                '/**
                 * Ding.
                 *    Indented
                 *    Indented 2
                 *
                 * @param integer[] $number Some number
                 *                    does things
                 * @return self
                 */',
                array(
                    'desc' => "Indented\nIndented 2",
                    'param' => array(
                        array(
                            'desc' => "Some number\ndoes things",
                            'isVariadic' => false,
                            'name' => 'number',
                            'type' => 'int[]',
                        ),
                    ),
                    'return' => array(
                        'desc' => '',
                        'type' => 'self',
                    ),
                    'summary' => 'Ding.',
                ),
            ),
            'complexType 1' => array(
                '/**
                * @return array{title: string, value: string, short: false} Very clear description
                */',
                array(
                    'desc' => '',
                    'return' => array(
                        'desc' => 'Very clear description',
                        'type' => 'array{title: string, value: string, short: false}',
                    ),
                    'summary' => '',
                ),
            ),
            'complexType 2' => array(
                '/**
                * @return array<array{title: string, value: string, short: false}> mumbo jumbo
                */',
                array(
                    'desc' => '',
                    'return' => array(
                        'desc' => 'mumbo jumbo',
                        'type' => 'array<array{title: string, value: string, short: false}>',
                    ),
                    'summary' => '',
                ),
            ),
            'complexType 3' => array(
                '/**
                * @param array<string, mixed>|Foo $var blah blah
                */',
                array(
                    'desc' => '',
                    'param' => array(
                        array(
                            'desc' => 'blah blah',
                            'isVariadic' => false,
                            'name' => 'var',
                            'type' => 'array<string, mixed>|Foo',
                        ),
                    ),
                    'return' => array(
                        'desc' => '',
                        'type' => null,
                    ),
                    'summary' => '',
                ),
            ),
            'version' => array(
                '/**
                * @version 1.2.3 Version and Desc
                * @version 1.2
                * @version Just desc
                * @version
                */',
                array(
                    'desc' => '',
                    'summary' => '',
                    'version' => array(
                        array(
                            'desc' => 'Version and Desc',
                            'version' => '1.2.3',
                        ),
                        array(
                            'desc' => '',
                            'version' => '1.2',
                        ),
                        array(
                            'desc' => 'Just desc',
                            'version' => null,
                        ),
                        array(
                            'desc' => '',
                            'version' => null,
                        ),
                    ),
                ),
            ),
        );
        return $tests;
    }

    /**
     * @param string $comment
     * @param array  $expect
     *
     * @dataProvider providerStrings
     */
    public function testStrings($comment, $expect)
    {
        $parsed = Debug::getInstance()->phpDoc->getParsed($comment);
        self::assertSame($expect, $parsed);
    }
}
