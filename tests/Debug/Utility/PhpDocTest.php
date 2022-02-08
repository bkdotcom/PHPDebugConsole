<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\PhpDoc;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 */
class PhpDocTest extends TestCase
{

    public static function setUpBeforeClass(): void
    {
        $reflector = new \ReflectionProperty('bdk\Debug\Utility\PhpDoc', 'cache');
        $reflector->setAccessible(true);
        $reflector->setValue(array());
        $reflector = new \ReflectionProperty('bdk\Debug\Utility\PhpDoc', 'parsers');
        $reflector->setAccessible(true);
        $reflector->setValue(array());
    }

    public function testInheritDoc()
    {
        $obj = new \bdk\Test\Debug\Fixture\Test2();
        $phpDoc = PhpDoc::getParsed($obj);
        // echo 'parsed = ' . \json_encode($phpDoc, JSON_PRETTY_PRINT) . "\n";

        $obj = new \bdk\Test\Debug\Fixture\PhpDocInheritDoc();
        $phpDoc = PhpDoc::getParsed($obj);

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
                    "desc": null
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
        $this->assertSame(\json_decode($expectJson, true), $phpDoc);

        $phpDoc = PhpDoc::getParsed('\bdk\Test\Debug\Fixture\Test2::$magicReadProp');
        $this->assertSame(array(
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
    }

    public function testStrings()
    {
        $parsed = PhpDoc::getParsed('\bdk\Test\Debug\Fixture\PhpDocInheritDoc');
        $parsed = PhpDoc::getParsed('\bdk\Test\Debug\Fixture\PhpDocInheritDoc::SOME_CONSTANT');
        $this->assertSame(array(
            'summary' => null,
            'desc' => null,
            'var' => array(
                array(
                    'type' => 'string',
                    'name' => 'SOME_CONSTANT',
                    'desc' => 'constant description',
                ),
            ),
        ), $parsed);
        $parsed = PhpDoc::getParsed('\bdk\Test\Debug\Fixture\PhpDocInheritDoc::$someProperty');
        $this->assertSame(array(
            'summary' => null,
            'desc' => null,
            'var' => array(
                array(
                    'type' => 'string',
                    'name' => 'someProperty',
                    'desc' => 'property description',
                ),
            ),
        ), $parsed);
        $parsed = PhpDoc::getParsed('\bdk\Test\Debug\Fixture\PhpDocInheritDoc::someMethod()');
        $this->assertSame(array(
            'summary' => 'Summary',
            'desc' => 'Description',
            'return' => array(
                'type' => 'bdk\Test\Debug\Fixture\SomeInterface',
                'desc' => null,
            ),
        ), $parsed);
        $comment = <<<'EOD'
        /**
         * @var string $comment phpdoc comment
         */
EOD;
        $parsed = PhpDoc::getParsed($comment);
        $this->assertSame(array(
            'summary' => null,
            'desc' => null,
            'var' => array(
                array(
                    'type' => 'string',
                    'name' => 'comment',
                    'desc' => 'phpdoc comment'
                ),
            ),
        ), $parsed);
    }
}
