<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Utility\PhpDoc;
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
        \bdk\Test\Debug\Helper::setProp('bdk\Debug\Utility\PhpDoc', 'cache', array());
    }

    public function testConstruct()
    {
        $phpDoc = new PhpDoc();
        $parsers = \bdk\Test\Debug\Helper::getProp($phpDoc, 'parsers');
        self::assertIsArray($parsers);
        self::assertNotEmpty($parsers, 'Parsers is empty');
    }

    public function testGetParsedObject()
    {
        $obj = new \bdk\Test\Debug\Fixture\Utility\PhpDocImplements();
        $parsed = Debug::getInstance()->phpDoc->getParsed($obj);
        $expectJson = <<<'EOD'
        {
            "summary": "Implement me!",
            "desc": null,
            "property": [
                {
                    "type": "bool",
                    "name": "magicProp",
                    "desc": "I'm avail via __get()"
                }
            ],
            "property-read": [
                {
                    "type": "bool",
                    "name": "magicReadProp",
                    "desc": "Read Only!"
                }
            ],
            "method": [
                {
                    "static": false,
                    "type": "void",
                    "name": "presto",
                    "param": [
                        {
                            "type": null,
                            "name": "$foo"
                        },
                        {
                            "type": "int",
                            "name": "$int",
                            "defaultValue": "1"
                        },
                        {
                            "type": null,
                            "name": "$bool",
                            "defaultValue": "true"
                        },
                        {
                            "type": null,
                            "name": "$null",
                            "defaultValue": "null"
                        }
                    ],
                    "desc": "I'm a magic method"
                },
                {
                    "static": true,
                    "type": "void",
                    "name": "prestoStatic",
                    "param": [
                        {
                            "type": "string",
                            "name": "$noDefault"
                        },
                        {
                            "type": null,
                            "name": "$arr",
                            "defaultValue": "array()"
                        },
                        {
                            "type": null,
                            "name": "$opts",
                            "defaultValue": "array('a'=>'ay','b'=>'bee')"
                        }
                    ],
                    "desc": "I'm a static magic method"
                }
            ],
            "author": [
                {
                    "name": "Brad Kent",
                    "email": "bkfake-github@yahoo.com",
                    "desc": "Author desc is non-standard"
                }
            ],
            "link": [
                {
                    "uri": "https:\/\/github.com\/bkdotcom\/PHPDebugConsole",
                    "desc": null
                }
            ],
            "see": [
                {
                    "uri": null,
                    "fqsen": "subclass::method()",
                    "desc": null
                }
            ],
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
            'summary' => null,
            'desc' => null,
        ), $parsed);
    }

    public function testGetParsedMethod()
    {
        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocImplements::someMethod()');
        self::assertSame(array(
            'summary' => 'SomeInterface summary',
            'desc' => 'SomeInterface description',
            'return' => array(
                'type' => 'bdk\Test\Debug\Fixture\SomeInterface',
                'desc' => null,
            ),
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod2()');
        self::assertSame(array(
            'summary' => 'PhpDocImplements summary',
            'desc' => 'PhpDocImplements desc',
            'return' => array(
                'type' => 'void',
                'desc' => null,
            ),
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocExtends::someMethod3()');
        self::assertSame(array(
            'summary' => 'PhpDocExtends summary',
            'desc' => 'PhpDocExtends desc / PhpDocImplements desc',
            /*
            'return' => array(
                'type' => 'void',
                'desc' => null,
            ),
            */
        ), $parsed);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocNoParent::someMethod()');
        self::assertSame(array(
            'summary' => null,
            'desc' => null,
        ), $parsed);
    }

    public function testGetParsedProperty()
    {
        $phpDoc = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Test2::$magicReadProp');
        self::assertSame(array(
            'summary' => 'This property is important',
            'desc' => null,
            'var' => array(
                array(
                    'type' => 'string',
                    'name' => 'magicReadProp',
                    'desc' => '',
                ),
            ),
        ), $phpDoc);

        $parsed = Debug::getInstance()->phpDoc->getParsed('\bdk\Test\Debug\Fixture\Utility\PhpDocImplements::$someProperty');
        self::assertSame(array(
            'summary' => '$someProperty summary',
            'desc' => null,
            'var' => array(
                array(
                    'type' => 'string',
                    'name' => 'someProperty',
                    'desc' => 'desc',
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
            'summary' => 'PhpDocImplements summary',
            'desc' => null,
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
            'summary' => 'PhpDocImplements summary',
            'desc' => null,
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

    public static function dataProviderComments()
    {
        return array(
            'basic' => array(
                '/**
                 * @var string $comment phpdoc comment
                 */',
                array(
                    'summary' => null,
                    'desc' => null,
                    'var' => array(
                        array(
                            'type' => 'string',
                            'name' => 'comment',
                            'desc' => 'phpdoc comment'
                        ),
                    ),
                ),
            ),
            'method' => array(
                '/**
                 * @method boolean magicMethod()
                 */',
                array(
                    'summary' => null,
                    'desc' => null,
                    'method' => array(
                        array(
                            'static' => false,
                            'type' => 'bool',
                            'name' => 'magicMethod',
                            'param' => array(),
                            'desc' => null,
                        ),
                    ),
                ),
            ),
            'missing $' => array(
                '/**
                 * @param string comment
                 */',
                array(
                    'summary' => null,
                    'desc' => null,
                    'param' => array(
                        array(
                            'type' => 'string',
                            'name' => 'comment',
                            'desc' => null,
                        ),
                    ),
                ),
            ),
            'missing $ 2' => array(
                '/**
                 * @param string comment here
                 */',
                array(
                    'summary' => null,
                    'desc' => null,
                    'param' => array(
                        array(
                            'type' => 'string',
                            'name' => null,
                            'desc' => 'comment here',
                        ),
                    ),
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
                    'summary' => 'Ding.',
                    'desc' => "Indented\nIndented 2",
                    'param' => array(
                        array(
                            'type' => 'int[]',
                            'name' => '$number',
                            'desc' => "Some number\ndoes things",
                        ),
                    ),
                    'return' => array(
                        'type' => 'self',
                        'desc' => null,
                    ),
                ),
            ),
            'complexType 1' => array(
                '/**
                * @return array{title: string, value: string, short: false} Very clear description
                */',
                array(
                    'summary' => null,
                    'desc' => null,
                    'return' => array(
                        'type' => 'array{title: string, value: string, short: false}',
                        'desc' => 'Very clear description',
                    ),
                ),
            ),
            'complexType 2' => array(
                '/**
                * @return array<array{title: string, value: string, short: false}> mumbo jumbo
                */',
                array(
                    'summary' => null,
                    'desc' => null,
                    'return' => array(
                        'type' => 'array<array{title: string, value: string, short: false}>',
                        'desc' => 'mumbo jumbo',
                    ),
                ),
            ),
        );
    }

    /**
     * @param string $comment
     * @param array  $expect
     *
     * @dataProvider dataProviderComments
     */
    public function testStrings($comment, $expect)
    {
        $parsed = Debug::getInstance()->phpDoc->getParsed($comment);
        self::assertSame($expect, $parsed);
    }
}
