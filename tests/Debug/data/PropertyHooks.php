<?php

/**
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Test\Debug\Fixture\PropertyHooks;

return array(
    'attributes' => array(),
    'cases' => array(),
    'cfgFlags' => 29360127,
    'className' => PropertyHooks::class,
    'constants' => array(),
    'debugMethod' => 'log',
    'definition' => array(
        'extensionName' => false,
        'fileName' => TEST_DIR . '/Debug/Fixture/PropertyHooks.php',
        'startLine' => 8,
    ),
    'extends' => array(),
    'implements' => array(),
    'interfacesCollapse' => array(),
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
    'keys' => array(),
    'methods' => array(),
    'methodsWithStaticVars' => array(),
    'phpDoc' => array(
        'desc' => '',
        'summary' => 'PHP 8.4 property hooks',
    ),
    'properties' => array(
        'backedGetAndSet' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(
                'get',
                'set',
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
            'type' => Type::TYPE_STRING,
            'value' => Abstracter::UNDEFINED,
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'backedGetOnly' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(
                'get',
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
            'type' => Type::TYPE_STRING,
            'value' => Abstracter::UNDEFINED,
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'backedSetOnly' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(
                'set',
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
            'type' => Type::TYPE_STRING,
            'value' => Abstracter::UNDEFINED,
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'static' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(),
            'isDeprecated' => false,
            'isFinal' => false,
            'isPromoted' => false,
            'isReadOnly' => false,
            'isStatic' => true,
            'isVirtual' => false,
            'phpDoc' => array(
                'desc' => '',
                'summary' => '',
            ),
            'type' => Type::TYPE_ARRAY,
            'value' => array(),
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'things' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(),
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
            'value' => array(),
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'virtualGetAndSet' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(
                'get',
                'set',
            ),
            'isDeprecated' => false,
            'isFinal' => false,
            'isPromoted' => false,
            'isReadOnly' => false,
            'isStatic' => false,
            'isVirtual' => true,
            'phpDoc' => array(
                'desc' => '',
                'summary' => '',
            ),
            'type' => Type::TYPE_STRING,
            'value' => Abstracter::NOT_INSPECTED,
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'virtualGetOnly' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(
                'get',
            ),
            'isDeprecated' => false,
            'isFinal' => false,
            'isPromoted' => false,
            'isReadOnly' => false,
            'isStatic' => false,
            'isVirtual' => true,
            'phpDoc' => array(
                'desc' => '',
                'summary' => '',
            ),
            'type' => Type::TYPE_STRING,
            'value' => Abstracter::NOT_INSPECTED,
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
        'virtualSetOnly' => array(
            'attributes' => array(),
            'debugInfoExcluded' => false,
            'declaredLast' => PropertyHooks::class,
            'declaredOrig' => PropertyHooks::class,
            'declaredPrev' => null,
            'forceShow' => false,
            'hooks' => array(
                'set',
            ),
            'isDeprecated' => false,
            'isFinal' => false,
            'isPromoted' => false,
            'isReadOnly' => false,
            'isStatic' => false,
            'isVirtual' => true,
            'phpDoc' => array(
                'desc' => '',
                'summary' => 'Write only property',
            ),
            'type' => Type::TYPE_STRING,
            'value' => Abstracter::UNDEFINED,
            'valueFrom' => 'value',
            'visibility' => ['public'],
        ),
    ),
    'scopeClass' => 'bdk\Test\Debug\Type\ObjectTest',
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
