<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Utility\PhpDoc;
use bdk\Debug\Utility\Reflection;
use bdk\Test\PolyFill\AssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Utility\PhpDoc
 * @covers \bdk\Debug\Utility\PhpDocBase
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
        $parsers = \bdk\Debug\Utility\Reflection::propGet($phpDoc, 'parsers');
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
            "desc": null,
            "link": [
                {
                    "desc": null,
                    "uri": "https:\/\/github.com\/bkdotcom\/PHPDebugConsole"
                }
            ],
            "method": [
                {
                    "desc": "I'm a magic method",
                    "name": "presto",
                    "param": [
                        {
                            "name": "$foo",
                            "type": null
                        },
                        {
                            "defaultValue": "1",
                            "name": "$int",
                            "type": "int"
                        },
                        {
                            "defaultValue": "true",
                            "name": "$bool",
                            "type": null
                        },
                        {
                            "defaultValue": "null",
                            "name": "$null",
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
                            "name": "$noDefault",
                            "type": "string"
                        },
                        {
                            "defaultValue": "array()",
                            "name": "$arr",
                            "type": null
                        },
                        {
                            "defaultValue": "array('a'=>'ay','b'=>'bee')",
                            "name": "$opts",
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
                    "desc": null,
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
            'desc' => null,
            'summary' => null,
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
                        'desc' => null,
                        'type' => 'bdk\Test\Debug\Fixture\SomeInterface',
                    ),
                    'summary' => 'SomeInterface summary',
                ),
            ),
            'someMethod2' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocImplements::someMethod2()',
                array(
                    'desc' => null,
                    'return' => array(
                        'desc' => null,
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
                        'desc' => null,
                        'type' => null,
                    ),
                    'summary' => 'PhpDocExtends summary',
                ),
            ),
            'someMethod4' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod4()',
                array(
                    'desc' => null,
                    'return' => array(
                        'desc' => null,
                        'type' => 'foo\\bar\\baz',
                    ),
                    'summary' => 'Test that baz resolves to foo\bar\baz',
                ),
            ),
            'someMethod5' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod5()',
                array(
                    'desc' => null,
                    'return' => array(
                        'desc' => null,
                        'type' => 'bdk\\Test\\Debug\\Fixture\\TestObj',
                    ),
                    'summary' => 'Test that TestObj resolves to bdk\Test\Debug\Fixture\TestObj',
                ),
            ),
            'noParent' => array(
                '\bdk\Test\Debug\Fixture\Utility\PhpDocNoParent::someMethod()',
                array(
                    'desc' => null,
                    'return' => array(
                        'desc' => null,
                        'type' => null,
                    ),
                    'summary' => null,
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
            'desc' => null,
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
            'desc' => null,
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
            'desc' => null,
            'summary' => 'Interface summary',
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::SOME_CONSTANT');
        self::assertSame(array(
            'desc' => null,
            'summary' => 'Interface summary',
        ), $parsed);
    }

    public static function providerStrings()
    {
        return array(
            'basic' => array(
                '/**
                 * @var string $comment phpdoc comment
                 */',
                array(
                    'desc' => null,
                    'summary' => null,
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
                    'desc' => null,
                    'method' => array(
                        array(
                            'desc' => null,
                            'name' => 'magicMethod',
                            'param' => array(),
                            'static' => false,
                            'type' => 'bool',
                        ),
                    ),
                    'summary' => null,
                ),
            ),
            'missing $' => array(
                '/**
                 * @param string comment
                 */',
                array(
                    'desc' => null,
                    'param' => array(
                        array(
                            'desc' => null,
                            'name' => 'comment',
                            'type' => 'string',
                        ),
                    ),
                    'return' => array(
                        'desc' => null,
                        'type' => null,
                    ),
                    'summary' => null,
                ),
            ),
            'missing $ 2' => array(
                '/**
                 * @param string comment here
                 */',
                array(
                    'desc' => null,
                    'param' => array(
                        array(
                            'desc' => 'comment here',
                            'name' => null,
                            'type' => 'string',
                        ),
                    ),
                    'return' => array(
                        'desc' => null,
                        'type' => null,
                    ),
                    'summary' => null,
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
                            'name' => '$number',
                            'type' => 'int[]',
                        ),
                    ),
                    'return' => array(
                        'desc' => null,
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
                    'desc' => null,
                    'return' => array(
                        'desc' => 'Very clear description',
                        'type' => 'array{title: string, value: string, short: false}',
                    ),
                    'summary' => null,
                ),
            ),
            'complexType 2' => array(
                '/**
                * @return array<array{title: string, value: string, short: false}> mumbo jumbo
                */',
                array(
                    'desc' => null,
                    'return' => array(
                        'desc' => 'mumbo jumbo',
                        'type' => 'array<array{title: string, value: string, short: false}>',
                    ),
                    'summary' => null,
                ),
            ),
        );
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
