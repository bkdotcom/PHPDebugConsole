<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Enums
 *
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\Object\Constants
 * @covers \bdk\Debug\Abstraction\Object\MethodParams
 * @covers \bdk\Debug\Abstraction\Object\Subscriber
 * @covers \bdk\Debug\Dump\Html\HtmlObject
 * @covers \bdk\Debug\Dump\Html\Object\Cases
 */
class EnumTest extends DebugTestFramework
{
    public static function providerTestMethod()
    {
        $isEnumSupported = PHP_VERSION_ID >= 80100;
        if ($isEnumSupported === false) {
            return array(
                array(),
            );
        }

        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $tests = array(
            'basic' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        self::assertAbstractionType($abs);
                        self::assertSame(Type::TYPE_OBJECT, $abs['type']);

                        $cases = $abs['cases'];
                        \ksort($cases);
                        $expect = array(
                            'BREAKFAST' => array(
                                'attributes' => array(),
                                'isFinal' => false,
                                'phpDoc' => array(
                                    'desc' => '',
                                    'summary' => 'The <b>most</b> important meal',
                                ),
                                'value' => Abstracter::UNDEFINED,
                                'visibility' => 'public',
                            ),
                            'DINNER' => array(
                                'attributes' => array(),
                                'isFinal' => false,
                                'phpDoc' => array(
                                    'desc' => '',
                                    'summary' => 'What\'s for dinner?',
                                ),
                                'value' => Abstracter::UNDEFINED,
                                'visibility' => 'public',
                            ),
                            'LUNCH' => array(
                                'attributes' => array(
                                    array(
                                        'arguments' => array(),
                                        'name' => 'bdk\Test\Debug\Fixture\Enum\ExampleCaseAttribute',
                                    ),
                                ),
                                'isFinal' => false,
                                'phpDoc' => array(
                                    'desc' => '',
                                    'summary' => '',
                                ),
                                'value' => Abstracter::UNDEFINED,
                                'visibility' => 'public',
                            ),
                        );
                        // \bdk\Debug::varDump('expect', $expect);
                        // \bdk\Debug::varDump('actual', $cases);
                        self::assertSame($expect, $cases);
                    },
                    'html' => '<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="t_identifier" data-type-more="const" title="The &lt;b&gt;most&lt;/b&gt; important meal
                        Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_name">BREAKFAST</span></span>
                        <dl class="object-inner">
                        <dt class="modifiers">modifiers</dt>
                        <dd class="t_modifier_final">final</dd>
                        <dt class="attributes">attributes</dt>
                        <dd class="attribute"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>ExampleAttribute</span></dd>
                        <dt>implements</dt>
                        <dd class="interface t_identifier toggle-off" data-type-more="className"><span class="classname">UnitEnum</span></dd>
                        <dt class="constants">constants</dt>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">ENUM_VALUE</span> <span class="t_operator">=</span> <span class="t_identifier" data-type-more="const" title="What&#039;s for dinner?
                            Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_name">DINNER</span></span></dd>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                        <dt class="cases">cases</dt>
                        <dd class="case"><span class="no-quotes t_identifier t_string" title="The &lt;b&gt;most&lt;/b&gt; important meal">BREAKFAST</span></dd>
                        <dd class="case"><span class="no-quotes t_identifier t_string" title="What&#039;s for dinner?">DINNER</span></dd>
                        <dd class="case" data-attributes="[{&quot;arguments&quot;:[],&quot;name&quot;:&quot;bdk\\\\Test\\\\Debug\\\\Fixture\\\\Enum\\\\ExampleCaseAttribute&quot;}]"><span class="no-quotes t_identifier t_string">LUNCH</span></dd>
                        <dt class="properties">properties</dt>
                        <dd class="isReadOnly property ' . (PHP_VERSION_ID >= 80400 ? 'protected-set ' : '') . 'public">'
                            . '<span class="t_modifier_public">public</span> '
                            . (PHP_VERSION_ID >= 80400 ? '<span class="t_modifier_protected-set">protected(set)</span> ' : '')
                            . '<span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> '
                            . '<span class="no-quotes t_identifier t_string">name</span> <span class="t_operator">=</span> <span class="t_string">BREAKFAST</span></dd>
                        <dt class="methods">methods</dt>
                        <dd class="isStatic method public" data-implements="UnitEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">cases</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">array</span></dd>
                        <dd class="isStatic method public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier" title="Prepare a meal">prepare</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="enum">$meal</span> <span class="t_operator">=</span> <span class="t_identifier t_parameter-default" data-type-more="const" title="The &lt;b&gt;most&lt;/b&gt; important meal
                            Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_name">BREAKFAST</span></span></span><span class="t_punct">,</span>
                            <span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="constant">$extra</span> <span class="t_operator">=</span> <span class="t_identifier t_parameter-default" data-type-more="const" title="value: &quot;test&quot;"><span class="classname">self</span><span class="t_operator">::</span><span class="t_name">REGULAR_CONSTANT</span></span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>
                        </dl>
                        </div></li>',
                ),
            ),

            'basic.group' => array(
                'group',
                array(
                    \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                ),
                array(
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="t_identifier" data-type-more="const" title="The &lt;b&gt;most&lt;/b&gt; important meal
                        Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_name">BREAKFAST</span></span></span></div>
                        <ul class="group-body">',
                ),
            ),

            'basic.table' => array(
                'table',
                array(
                    array(
                        'enum' => \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                    )
                ),
                array(
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <thead>
                            <tr><th>&nbsp;</th><th scope="col">value</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_key t_string text-right" scope="row">enum</th><td class="t_identifier" data-type-more="const" title="The &lt;b&gt;most&lt;/b&gt; important meal
                            Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_name">BREAKFAST</span></td></tr>
                        </tbody>
                        </table>
                        </li>',
                ),
            ),

            /*
            'basicCaseCollectFalse' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                    Debug::meta('cfg', 'caseCollect', false),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        self::assertIsArray($abs['cases']);
                        self::assertEmpty($abs['cases']);
                    },
                    'html' => '%a<dt class="cases">cases <i>not collected</i></dt>%a',
                )
            ),
            */

            'basicCaseOutputFalse' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                    Debug::meta('cfg', 'caseOutput', false),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        self::assertIsArray($abs['cases']);
                        self::assertCount(3, $abs['cases']);
                    },
                    'html' => '%a<dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                        <dt class="properties">properties</dt>%a',
                ),
            ),

            'backed' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\MealsBacked::BREAKFAST,
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        self::assertAbstractionType($abs);

                        $cases = $abs['cases'];
                        \ksort($cases);
                        self::assertSame(array(
                            'BREAKFAST' => array(
                                'attributes' => array(),
                                'isFinal' => false,
                                'phpDoc' => array(
                                    'desc' => '',
                                    'summary' => '',
                                ),
                                'value' => 'breakfast',
                                'visibility' => 'public',
                            ),
                            'DINNER' => array(
                                'attributes' => array(),
                                'isFinal' => false,
                                'phpDoc' => array(
                                    'desc' => '',
                                    'summary' => '',
                                ),
                                'value' => 'dinner',
                                'visibility' => 'public',
                            ),
                            'LUNCH' => array(
                                'attributes' => array(),
                                'isFinal' => false,
                                'phpDoc' => array(
                                    'desc' => '',
                                    'summary' => '',
                                ),
                                'value' => 'lunch',
                                'visibility' => 'public',
                            ),
                        ), $cases);
                    },
                    'html' => '<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="t_identifier" data-type-more="const"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>MealsBacked</span><span class="t_operator">::</span><span class="t_name">BREAKFAST</span></span>
                        <dl class="object-inner">
                        <dt class="modifiers">modifiers</dt>
                        <dd class="t_modifier_final">final</dd>
                        <dt>implements</dt>
                        <dd class="implements">
                            <ul class="list-unstyled">
                                <li><span class="interface t_identifier toggle-off" data-type-more="className"><span class="classname">BackedEnum</span></span>
                                    <ul class="list-unstyled">
                                        <li><span class="interface t_identifier toggle-off" data-type-more="className"><span class="classname">UnitEnum</span></span></li>
                                    </ul>
                                </li>
                            </ul>
                        </dd>
                        <dt class="constants">constants</dt>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">ENUM_VALUE</span> <span class="t_operator">=</span> <span class="t_identifier" data-type-more="const"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>MealsBacked</span><span class="t_operator">::</span><span class="t_name">DINNER</span></span></dd>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                        <dt class="cases">cases</dt>
                        <dd class="case"><span class="no-quotes t_identifier t_string">BREAKFAST</span> <span class="t_operator">=</span> <span class="t_string">breakfast</span></dd>
                        <dd class="case"><span class="no-quotes t_identifier t_string">DINNER</span> <span class="t_operator">=</span> <span class="t_string">dinner</span></dd>
                        <dd class="case"><span class="no-quotes t_identifier t_string">LUNCH</span> <span class="t_operator">=</span> <span class="t_string">lunch</span></dd>
                        <dt class="properties">properties</dt>
                        <dd class="isReadOnly property ' . (PHP_VERSION_ID >= 80400 ? 'protected-set ' : '') . 'public">'
                            . '<span class="t_modifier_public">public</span> '
                            . (PHP_VERSION_ID >= 80400 ? '<span class="t_modifier_protected-set">protected(set)</span> ' : '')
                            . '<span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> '
                            . '<span class="no-quotes t_identifier t_string">name</span> <span class="t_operator">=</span> <span class="t_string">BREAKFAST</span></dd>
                        <dd class="isReadOnly property ' . (PHP_VERSION_ID >= 80400 ? 'protected-set ' : '') . 'public">'
                            . '<span class="t_modifier_public">public</span> '
                            . (PHP_VERSION_ID >= 80400 ? '<span class="t_modifier_protected-set">protected(set)</span> ' : '')
                            . '<span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> '
                            . '<span class="no-quotes t_identifier t_string">value</span> <span class="t_operator">=</span> <span class="t_string">breakfast</span></dd>
                        <dt class="methods">methods</dt>
                        <dd class="isStatic method public" data-implements="UnitEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">cases</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">array</span></dd>
                        <dd class="isStatic method public" data-implements="BackedEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">from</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">int</span> <span class="t_parameter-name">$value</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">static</span></dd>
                        <dd class="isStatic method public" data-implements="BackedEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">tryFrom</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">int</span> <span class="t_parameter-name">$value</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">static</span></dd>
                        </dl>
                        </div></li>',
                ),
            ),
        );
        // $tests = \array_intersect_key($tests, \array_flip(array('basic')));
        return $tests;
    }
}
