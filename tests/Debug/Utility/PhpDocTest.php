<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Utility\PhpDoc;
use bdk\Test\Debug\Helper;
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
        // $reflector = new \ReflectionProperty('bdk\Debug\Utility\PhpDoc', 'cache');
        // $reflector->setAccessible(true);
        // $reflector->setValue(array());
        Helper::setProp('bdk\Debug\Utility\PhpDoc', 'cache', array());
    }

    public function testConstruct()
    {
        $phpDoc = new PhpDoc();
        $parsers = Helper::getProp($phpDoc, 'parsers');
        self::assertIsArray($parsers);
        self::assertNotEmpty($parsers, 'Parsers is empty');
    }

    public function testGetParsedObject()
    {
        $obj = new \bdk\Test\Debug\Fixture\Utility\PhpDocImplements();
        $parsed = Debug::getInstance()->phpDoc->getParsed($obj);
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
        self::assertSame(\json_decode($expectJson, true), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocImplements');
        self::assertSame(\json_decode($expectJson, true), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends');
        self::assertSame(\json_decode($expectJson, true), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocNoParent');
        self::assertSame(array(
            'desc' => null,
            'summary' => null,
        ), $parsed);
    }

    public function testGetParsedMethod()
    {
        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocImplements::someMethod()');
        self::assertSame(array(
            'desc' => 'SomeInterface description',
            'return' => array(
                'desc' => null,
                'type' => 'bdk\Test\Debug\Fixture\SomeInterface',
            ),
            'summary' => 'SomeInterface summary',
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod2()');
        self::assertSame(array(
            'desc' => 'PhpDocImplements desc',
            'return' => array(
                'desc' => null,
                'type' => 'void',
            ),
            'summary' => 'PhpDocImplements summary',
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod3()');
        self::assertSame(array(
            'desc' => 'PhpDocExtends desc / PhpDocImplements desc',
            'summary' => 'PhpDocExtends summary',
            /*
            'return' => array(
                'type' => 'void',
                'desc' => null,
            ),
            */
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocNoParent::someMethod()');
        self::assertSame(array(
            'desc' => null,
            'summary' => null,
        ), $parsed);
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
        $reflector = new \ReflectionClassConstant('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends', 'SOME_CONSTANT');
        $parsed = Debug::getInstance()->phpDoc->getParsed($reflector);
        self::assertSame(array(
            'desc' => null,
            'summary' => 'PhpDocImplements summary',
            /*
            'var' => array(
                array(
                    'type' => 'string',
                    'name' => 'SOME_CONSTANT',
                    'desc' => 'constant description',
                ),
            ),
            */
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::SOME_CONSTANT');
        self::assertSame(array(
            'desc' => null,
            'summary' => 'PhpDocImplements summary',
            /*
            'var' => array(
                array(
                    'type' => 'string',
                    'name' => 'SOME_CONSTANT',
                    'desc' => 'constant description',
                ),
            ),
            */
        ), $parsed);
    }

    public static function providerComments()
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
     * @dataProvider providerComments
     */
    public function testStrings($comment, $expect)
    {
        $parsed = Debug::getInstance()->phpDoc->getParsed($comment);
        self::assertSame($expect, $parsed);
    }
}
