<?php

return array (
    'alerts' => array(
        0 => array(
            0 => 'alert',
            1 => array(
                0 => 'Serialize this!',
            ),
            2 => array(
                'dismissible' => false,
                'level' => 'error',
            ),
        ),
    ),
    'classDefinitions' => array(
        '' . "\0" . 'default' . "\0" . '' => array(
            '__isUsed' => true,
            'attributes' => array(
            ),
            'cases' => array(
            ),
            'cfgFlags' => 29360127,
            'className' => '' . "\0" . 'default' . "\0" . '',
            'constants' => array(
            ),
            'debugMethod' => '',
            'definition' => array(
                'extensionName' => false,
                'fileName' => false,
                'startLine' => false,
            ),
            'extends' => array(
            ),
            'implements' => array(
            ),
            'interfacesCollapse' => array(
            ),
            'isAbstract' => false,
            'isAnonymous' => false,
            'isExcluded' => false,
            'isFinal' => false,
            'isInterface' => false,
            'isLazy' => false,
            'isMaxDepth' => false,
            'isReadOnly' => false,
            'isRecursion' => false,
            'isTrait' => false,
            'keys' => array(
            ),
            'methods' => array(
            ),
            'methodsWithStaticVars' => array(
            ),
            'phpDoc' => array(
                'desc' => '',
                'summary' => '',
            ),
            'properties' => array(
            ),
            'scopeClass' => null,
            'sectionOrder' => array(
                0 => 'attributes',
                1 => 'extends',
                2 => 'implements',
                3 => 'constants',
                4 => 'cases',
                5 => 'properties',
                6 => 'methods',
                7 => 'phpDoc',
            ),
            'sort' => 'inheritance visibility name',
            'stringified' => null,
            'viaDebugInfo' => false,
        ),
        'Simple' => array(
            '__isUsed' => true,
            'className' => 'Simple',
            'debug' => \bdk\Debug\Abstraction\Abstracter::ABSTRACTION,
            'definition' => array(
                'fileName' => '/Users/bkent/Library/CloudStorage/Dropbox/git/bkdotcom/PHPDebugConsole/examples/serialize.php',
                'startLine' => 18,
            ),
            'implements' => array(
                0 => 'Stringable',
            ),
            'inheritsFrom' => '' . "\0" . 'default' . "\0" . '',
            'methods' => array(
                '__toString' => array(
                    'attributes' => array(
                    ),
                    'declaredLast' => 'Simple',
                    'declaredOrig' => 'Simple',
                    'declaredPrev' => null,
                    'implements' => 'Stringable',
                    'isAbstract' => false,
                    'isDeprecated' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'params' => array(
                    ),
                    'phpDoc' => array(
                        'desc' => '',
                        'summary' => '',
                    ),
                    'return' => array(
                        'desc' => '',
                        'type' => 'string',
                    ),
                    'returnValue' => null,
                    'staticVars' => array(
                    ),
                    'visibility' => 'public',
                ),
                'foo' => array(
                    'attributes' => array(
                    ),
                    'declaredLast' => 'Simple',
                    'declaredOrig' => 'Simple',
                    'declaredPrev' => null,
                    'implements' => null,
                    'isAbstract' => false,
                    'isDeprecated' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'params' => array(
                        0 => array(
                            'attributes' => array(
                            ),
                            'defaultValue' => \bdk\Debug\Abstraction\Abstracter::UNDEFINED,
                            'desc' => 'the string you want to foo',
                            'isOptional' => false,
                            'isPassedByReference' => false,
                            'isPromoted' => false,
                            'isVariadic' => false,
                            'name' => 'bar',
                            'type' => 'string',
                        ),
                    ),
                    'phpDoc' => array(
                        'desc' => '',
                        'summary' => 'Foo is a private method that does stuff',
                    ),
                    'return' => array(
                        'desc' => 'the fooed string',
                        'type' => 'string',
                    ),
                    'staticVars' => array(
                    ),
                    'visibility' => 'private',
                ),
            ),
            'properties' => array(
                'offLimits' => array(
                    'attributes' => array(
                    ),
                    'debugInfoExcluded' => false,
                    'declaredLast' => 'Simple',
                    'declaredOrig' => 'Simple',
                    'declaredPrev' => null,
                    'forceShow' => false,
                    'hooks' => array(
                    ),
                    'isDeprecated' => false,
                    'isFinal' => false,
                    'isPromoted' => false,
                    'isReadOnly' => false,
                    'isStatic' => false,
                    'isVirtual' => false,
                    'phpDoc' => array(
                        'desc' => '',
                        'summary' => '',
                    ),
                    'type' => null,
                    'value' => 'I\'m a private property',
                    'valueFrom' => 'value',
                    'visibility' => array(
                        0 => 'private',
                    ),
                ),
            ),
            'type' => 'object',
            'typeMore' => null,
        ),
    ),
    'config' => array(
        'channelIcon' => 'fa fa-list-ul',
        'channelKey' => 'general',
        'channelName' => 'general',
        'channels' => array(
            'php' => array(
                'channelIcon' => '<i class="fa" style="position:relative; top:2px; font-size:15px;">🐘</i>',
                'channelShow' => true,
                'channelSort' => 10,
                'nested' => false,
            ),
        ),
        'logRuntime' => true,
    ),
    'log' => array(
        0 => array(
            0 => 'group',
            1 => array(
                0 => 'array o things',
            ),
        ),
        1 => array(
            0 => 'log',
            1 => array(
                0 => array(
                    'boolean.true' => true,
                    'boolean.false' => false,
                    'int' => 7,
                    'float' => 123.45,
                    'null' => null,
                    'object' => array(
                        'debug' => \bdk\Debug\Abstraction\Abstracter::ABSTRACTION,
                        'debugMethod' => 'log',
                        'inheritsFrom' => 'Simple',
                        'methods' => array(
                            '__toString' => array(
                                'returnValue' => 'It\'s Magic',
                            ),
                        ),
                        'type' => 'object',
                    ),
                    'string' => '<em>Strings</em><br />
	get visual farts whitespace&trade; and control-char highlighting (hover over the highlights)',
                    'string (numeric)' => '42',
                    'string (timestamp)' => array(
                        'brief' => false,
                        'debug' => \bdk\Debug\Abstraction\Abstracter::ABSTRACTION,
                        'type' => 'string',
                        'typeMore' => 'timestamp',
                        'value' => '1767270896',
                    ),
                ),
            ),
        ),
        2 => array(
            0 => 'groupEnd',
            1 => array(
            ),
        ),
        3 => array(
            0 => 'table',
            1 => array(
                0 => array(
                    'caption' => 'Populations',
                    'debug' => \bdk\Debug\Abstraction\Abstracter::ABSTRACTION,
                    'header' => array(
                        0 => '',
                        1 => 'city',
                        2 => 'state',
                        3 => 'population',
                    ),
                    'meta' => array(
                        'class' => null,
                        'columns' => array(
                            0 => array(
                                'attribs' => array(
                                    'class' => array(
                                        0 => 't_key',
                                    ),
                                    'scope' => 'row',
                                ),
                                'key' => \bdk\Table\Factory::KEY_INDEX,
                                'tagName' => 'th',
                            ),
                            1 => array(
                                'key' => 'city',
                            ),
                            2 => array(
                                'key' => 'state',
                            ),
                            3 => array(
                                'key' => 'population',
                            ),
                        ),
                        'haveObjectRow' => false,
                        'sortable' => true,
                    ),
                    'rows' => array(
                        0 => array(
                            0 => 0,
                            1 => 'Atlanta',
                            2 => 'GA',
                            3 => 472522,
                        ),
                        1 => array(
                            0 => 1,
                            1 => 'Buffalo',
                            2 => 'NY',
                            3 => 256902,
                        ),
                        2 => array(
                            0 => 2,
                            1 => 'Chicago',
                            2 => 'IL',
                            3 => 2704958,
                        ),
                        3 => array(
                            0 => 3,
                            1 => 'Denver',
                            2 => 'CO',
                            3 => 693060,
                        ),
                        4 => array(
                            0 => 4,
                            1 => 'Seattle',
                            2 => 'WA',
                            3 => 704352,
                        ),
                        5 => array(
                            0 => 5,
                            1 => 'Tulsa',
                            2 => 'OK',
                            3 => 403090,
                        ),
                    ),
                    'type' => 'table',
                    'value' => null,
                ),
            ),
        ),
    ),
    'logSummary' => array(
        1 => array(
            0 => array(
                0 => 'info',
                1 => array(
                    0 => 'Built in 123.123 ms',
                ),
            ),
            1 => array(
                0 => 'info',
                1 => array(
                    0 => 'Peak memory usage...',
                ),
                2 => array(
                    'sanitize' => false,
                ),
            ),
        ),
    ),
    'requestId' => 'deadbeef',
    'runtime' => array(
        'memoryLimit' => '2G',
        'memoryPeakUsage' => '12123123',
        'runtime' => 0.123,
    ),
    'version' => '3.6',
);
