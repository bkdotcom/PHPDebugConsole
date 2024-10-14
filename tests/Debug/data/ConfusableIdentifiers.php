<?php

/**
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;

return array(
    'attributes' => PHP_VERSION_ID >= 80000
        ? array(
            array(
                'arguments' => array(
                    'nαme' => 'baг',
                ),
                'name' => 'bdk\Test\Debug\Fix𝐭ure\EⅹampleClassAttribute',
            ),
        )
        : array(),
    'cases' => array(),
    'cfgFlags' => 29360127,
    'className' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
    'constants' => array(
        'ᖴOO' => array(
            'attributes' => PHP_VERSION_ID >= 80000
                ? array(
                    array(
                        'arguments' => array(
                            'fσo' => 'baг',
                        ),
                        'name' => 'bdk\Test\Debug\Fix𝐭ure\ExampleСonstAttribute',
                    ),
                )
                : array(),
            'declaredLast' => PHP_VERSION_ID >= 70100
                ? 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers'
                : null,
            'declaredOrig' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredPrev' => null,
            'isFinal' => false,
            'phpDoc' => array(
                'desc' => '',
                'summary' => PHP_VERSION_ID >= 70100
                    ? '[𐊢]onst <b>desc</b>'
                    : '',
            ),
            'type' => PHP_VERSION_ID >= 70100
                ? Type::TYPE_STRING
                : null,
            'value' => 'fσo',
            'visibility' => 'public',
        ),
    ),
    'debugMethod' => 'log',
    'definition' => array(
        'extensionName' => false,
        'fileName' => TEST_DIR . '/Debug/Fixture/ConfusableIdentifiers.php',
        'startLine' => 35,
    ),
    'extends' => array(
        'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableIdenti𝘧iersBaꜱe',
    ),
    'implements' => PHP_VERSION_ID >= 80000
        ? array(
            'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableInteᴦface',
            'Stringable',
        )
        : array(
            'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableInteᴦface',
        ),
    'interfacesCollapse' => array(),
    'isAbstract' => false,
    'isAnonymous' => false,
    'isExcluded' => false,
    'isFinal' => false,
    'isInterface' => false,
    'isMaxDepth' => false,
    'isReadOnly' => false,
    'isRecursion' => false,
    'isTrait' => false,
    'keys' => array(),
    'methods' => array(
        '__call' => array(
            'attributes' => array(),
            'declaredLast' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredOrig' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredPrev' => null,
            'implements' => null,
            'isAbstract' => false,
            'isDeprecated' => false,
            'isFinal' => false,
            'isStatic' => false,
            'params' => array(
                array(
                    'attributes' => array(),
                    'defaultValue' => Abstracter::UNDEFINED,
                    'desc' => 'Inaccessible method name',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'method',
                    'type' => 'string',
                ),
                array(
                    'attributes' => array(),
                    'defaultValue' => Abstracter::UNDEFINED,
                    'desc' => 'Arguments passed to method',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'args',
                    'type' => 'array',
                ),
            ),
            'phpDoc' => array(
                'desc' => '',
                'summary' => 'Magic method... inaccessible method called.',
                'throws' => array(
                    array(
                        'desc' => '',
                        'type' => 'BadMethodCallException',
                    ),
                ),
            ),
            'return' => array(
                'desc' => '',
                'type' => 'mixed',
            ),
            'staticVars' => array(),
            'visibility' => 'public',
        ),
        '__set' => array(
            'attributes' => array(),
            'declaredLast' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredOrig' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredPrev' => null,
            'implements' => null,
            'isAbstract' => false,
            'isDeprecated' => false,
            'isFinal' => false,
            'isStatic' => false,
            'params' => array(
                array(
                    'attributes' => array(),
                    'defaultValue' => Abstracter::UNDEFINED,
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'property',
                    'type' => null,
                ),
                array(
                    'attributes' => array(),
                    'defaultValue' => Abstracter::UNDEFINED,
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'value',
                    'type' => null,
                ),
            ),
            'phpDoc' => array(
                'desc' => '',
                'summary' => '',
            ),
            'return' => array(
                'desc' => '',
                'type' => null,
            ),
            'staticVars' => array(),
            'visibility' => 'public',
        ),

        '__toString' => array(
            'attributes' => array(),
            'declaredLast' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredOrig' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredPrev' => null,
            'implements' => PHP_VERSION_ID >= 80000
                ? 'Stringable'
                : null,
            'isAbstract' => false,
            'isDeprecated' => false,
            'isFinal' => false,
            'isStatic' => false,
            'params' => array(),
            'phpDoc' => array(
                'desc' => '',
                'summary' => 'M[ɑ]gic <b>method</b>',
            ),
            'return' => array(
                'desc' => '<b>happy</b> [һ]appy',
                'type' => Type::TYPE_STRING,
            ),
            'returnValue' => 'Thiꮪ <b>is</b> a string',
            'staticVars' => array(),
            'visibility' => 'public',
        ),
        'baꜱe𐊗hing' => array(
            'attributes' => array(),
            'declaredLast' => 'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableIdenti𝘧iersBaꜱe',
            'declaredOrig' => 'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableIdenti𝘧iersBaꜱe',
            'declaredPrev' => null,
            'implements' => 'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableInteᴦface',
            'isAbstract' => false,
            'isDeprecated' => false,
            'isFinal' => false,
            'isStatic' => false,
            'params' => array(),
            'phpDoc' => array(
                'desc' => '',
                'summary' => '',
            ),
            'return' => array(
                'desc' => '',
                'type' => null,
            ),
            'staticVars' => array(),
            'visibility' => 'public',
        ),
        'foo' => array(
            'attributes' => array(),
            'declaredLast' => 'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableIdenti𝘧iersBaꜱe',
            'declaredOrig' => 'bdk\Test\Debug\Fix𝐭ure\Con𝘧usableIdenti𝘧iersBaꜱe',
            'declaredPrev' => null,
            'implements' => null,
            'isAbstract' => false,
            'isDeprecated' => false,
            'isFinal' => false,
            'isStatic' => false,
            'params' => array(),
            'phpDoc' => array(
                'desc' => '',
                'summary' => '',
            ),
            'return' => array(
                'desc' => '',
                'type' => null,
            ),
            'staticVars' => array(),
            'visibility' => 'public',
        ),
        'mаgicMethod' => array(
            'attributes' => array(),
            'declaredLast' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredOrig' => null,
            'declaredPrev' => null,
            'implements' => null,
            'isAbstract' => false,
            'isDeprecated' => false,
            'isFinal' => false,
            'isStatic' => false,
            'params' => array(
                array(
                    'attributes' => array(),
                    'defaultValue' => 'vаl,սe',
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => '𝕡аram',
                    'type' => Type::TYPE_STRING,
                ),
                array(
                    'attributes' => array(),
                    'defaultValue' => 1,
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'int',
                    'type' => Type::TYPE_INT,
                ),
                array(
                    'attributes' => array(),
                    'defaultValue' => true,
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'bool',
                    'type' => null,
                ),
                array(
                    'attributes' => array(),
                    'defaultValue' => null,
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'null',
                    'type' => null,
                ),
                array(
                    'attributes' => array(),
                    'defaultValue' => 'array(\'<script>alert("xss")</script>\')',
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'arr',
                    'type' => null,
                ),
                array(
                    'attributes' => array(),
                    'defaultValue' => array(
                        'backedValue' => 'fσo',
                        'debug' => Abstracter::ABSTRACTION,
                        'type' => Type::TYPE_IDENTIFIER,
                        'typeMore' => Type::TYPE_IDENTIFIER_CONST,
                        'value' => 'self::ᖴOO',
                    ),
                    'desc' => '',
                    'isOptional' => false,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => 'const',
                    'type' => null,
                ),
            ),
            'phpDoc' => array(
                'desc' => '',
                'summary' => 'T[е]st :) <b>method</b>',
            ),
            'return' => array(
                'desc' => '',
                'type' => Type::TYPE_BOOL,
            ),
            'staticVars' => array(),
            'visibility' => 'magic',
        ),
        'tℯst' => array(
            'attributes' => PHP_VERSION_ID >= 80000
                ? array(
                    array(
                        'arguments' => array(
                            'nαme' => 'baг',
                        ),
                        'name' => 'bdk\Test\Debug\Fix𝐭ure\ExampleΜethodAttribute',
                    ),
                )
                : array(),
            'declaredLast' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredOrig' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredPrev' => null,
            'implements' => null,
            'isAbstract' => false,
            'isDeprecated' => true,
            'isFinal' => false,
            'isStatic' => false,
            'params' => array(
                array(
                    'attributes' => PHP_VERSION_ID >= 80000
                        ? array(
                            array(
                                'arguments' => array(
                                    'fσo' => '<b>b</b>aг',
                                ),
                                'name' => 'bdk\Test\Debug\Fix𝐭ure\ExampleParamАttribute',
                            ),
                        )
                        : array(),
                    'defaultValue' => '<b>v</b>alսe',
                    'desc' => '',
                    'isOptional' => true,
                    'isPassedByReference' => false,
                    'isPromoted' => false,
                    'isVariadic' => false,
                    'name' => '𝕡aram',
                    'type' => null,
                ),
            ),
            'phpDoc' => array(
                'cʋstTag' => array(
                    array(
                        'desc' => 'h[ο]wdy there <b>partner</b>',
                    ),
                ),
                'deprecated' => array(
                    array(
                        'desc' => '[Ʀ]ea<b>sons</b>',
                        'version' => null,
                    ),
                ),
                'desc' => '<b>[𝖽]esc</b>ription',
                'summary' => '<b>[𐑈]um</b>mary.',
                'throws' => array(
                    array(
                        'desc' => '[Ʀ]ea<b>sons</b>',
                        'type' => 'Ьdk\𐊂ogus',
                    ),
                ),
            ),
            'return' => array(
                'desc' => '',
                'type' => Type::TYPE_BOOL,
            ),
            'staticVars' => array(),
            'visibility' => 'public',
        ),
    ),
    'methodsWithStaticVars' => array(),
    'phpDoc' => array(
        'desc' => 'CƖass <b>[𝖽]esc</b>ription',
        'summary' => 'CƖass <b>[𐑈]um</b>mary.',
        'author' => array(
            array(
                'desc' => '[Ｓ]pam <em>folder</em>',
                'email' => 'bkfake-github@уahoo.com',
                'name' => '[Β]rad Kent',
            ),
        ),
        'link' => array(
            array(
                'desc' => '[Ⅼ]ink <b>Rot</b>',
                'uri' => 'http://ᴜrl.com/?foo=bar&ding=dong',
            ),
        ),
        'see' => array(
            array(
                'desc' => '<b>Super</b> [Η]elpful',
                'fqsen' => null,
                'uri' => 'http://ᴜrl.com/?foo=bar&ding=dong',
            ),
        ),
        'ᴄustTag' => array(
            array(
                'desc' => '[ｈ]owdy <b>partner</b>',
            ),
        ),
    ),
    'properties' => array(
        'array' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredOrig' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(),
            'isDeprecated' => false,
            'isPromoted' => false,
            'isReadOnly' => false,
            'isStatic' => false,
            'isVirtual' => false,
            'phpDoc' => array(
                'desc' => '',
                'summary' => 'key =&gt; value array',
            ),
            'type' => 'array<string,mixed>',
            'value' => array(
                'debug' =>  Abstracter::ABSTRACTION,
                'keys' => array(
                    'af8af85a7694926703b9690c2eb6d1fc' => array(
                        'brief' => false,
                        'chunks' => array(
                            ['utf8', 'nοn'],
                            ['other', '80'],
                            ['utf8', 'utf8'],
                        ),
                        'debug' => Abstracter::ABSTRACTION,
                        'percentBinary' => 1 / 9 * 100,
                        'strlen' => 9,
                        'strlenValue' => 9,
                        'type' => Type::TYPE_STRING,
                        'typeMore' => Type::TYPE_STRING_BINARY,
                        'value' => '',
                    ),
                ),
                'type' => Type::TYPE_ARRAY,
                'value' => array(
                    'int' => 42,
                    'password' => 'secret',
                    'poop' => '💩',
                    'string' => "strıngy\nstring",
                    'ctrl chars and whatnot' => "\xef\xbb\xbfbom\r\n\t\x07 \x1F \x7F \x00 \xc2\xa0<i>(nbsp)</i> \xE2\x80\x89(thsp), & \xE2\x80\x8B(zwsp)",
                    'af8af85a7694926703b9690c2eb6d1fc' => 'test',
                ),
            ),
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'ցᴏɑt' => array(
            'attributes' => PHP_VERSION_ID >= 80000
                ? array(
                    array(
                        'arguments' => array(
                            'fσo' => 'baг',
                        ),
                        'name' => 'bdk\Test\Debug\Fix𝐭ure\Example𝝦ropAttribute',
                    ),
                )
                : array(),
            'debugInfoExcluded' => false,
            'declaredLast' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredOrig' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(),
            'isDeprecated' => false,
            'isPromoted' => false,
            'isReadOnly' => false,
            'isStatic' => false,
            'isVirtual' => false,
            'phpDoc' => array(
                'desc' => '',
                'summary' => '[Ⲣ]roperty <b>desc</b>',
            ),
            'type' => Type::TYPE_STRING,
            'value' => 'moun𝐭ain',
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
    ),
    'scopeClass' => 'bdk\Test\Debug\CharReplacementAndSanitizationTest',
    'sectionOrder' => array(
        'attributes',
        'extends',
        'implements',
        'constants',
        'cases',
        'properties',
        'methods',
        'phpDoc',
    ),
    'sort' => 'inheritance visibility name',
    'stringified' => null,
    'traverseValues' => array(),
    'type' => Type::TYPE_OBJECT,
    'typeMore' => null,
    'viaDebugInfo' => false,
);
