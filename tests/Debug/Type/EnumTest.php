<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Enums
 *
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\AbstractObjectConstants
 * @covers \bdk\Debug\Dump\Html\HtmlObject
 * @covers \bdk\Debug\Dump\Html\ObjectConstants
 */
class EnumTest extends DebugTestFramework
{
    public function providerTestMethod()
    {
        $isEnumSupported = PHP_VERSION_ID >= 80100;
        if ($isEnumSupported === false) {
            return array(
                array(),
            );
        }

        // $regexTrim = '#^\s+#m';
        return array(
            'basic' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                ),
                array(
                    'entry' => function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        $this->assertAbstractionType($abs);
                        $this->assertSame(Abstracter::TYPE_OBJECT, $abs['type']);
                        $this->assertSame(array(
                            'BREAKFAST' => array(
                                'attributes' => array(),
                                'desc' => 'The most important meal',
                                'value' => null,
                            ),
                            'LUNCH' => array(
                                'attributes' => array(
                                    array(
                                        'name' => 'bdk\Test\Debug\Fixture\Enum\ExampleCaseAttribute',
                                        'arguments' => array(),
                                    ),
                                ),
                                'desc' => null,
                                'value' => null,
                            ),
                            'DINNER' => array(
                                'attributes' => array(),
                                'desc' => null,
                                'value' => null,
                            )
                        ), $abs['cases']);
                    },
                    'html' => '<li class="m_log"><div class="t_object" data-accessible="public"><span class="t_const" title="The most important meal
                        Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_identifier">BREAKFAST</span></span>
                        <dl class="object-inner">
                        <dt class="t_modifier_final">final</dt>
                        <dt>implements</dt>
                        <dd class="interface"><span class="classname">UnitEnum</span></dd>
                        <dt class="attributes">attributes</dt>
                        <dd class="attribute"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>ExampleAttribute</span></dd>
                        <dt class="constants">constants</dt>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">ENUM_VALUE</span> <span class="t_operator">=</span> <div class="t_object" data-accessible="private"><span class="t_const" title="Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_identifier">DINNER</span></span>
                            <dl class="object-inner">
                            <dt class="t_modifier_final">final</dt>
                            <dt>implements</dt>
                            <dd class="interface"><span class="classname">UnitEnum</span></dd>
                            <dt class="attributes">attributes</dt>
                            <dd class="attribute"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>ExampleAttribute</span></dd>
                            <dt class="constants">constants</dt>
                            <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">ENUM_VALUE</span> <span class="t_operator">=</span> <div class="t_object" data-accessible="private"><span class="t_const"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_identifier">DINNER</span></span>
                                <span class="t_recursion">*RECURSION*</span></div></dd>
                            <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                            <dt class="cases">cases</dt>
                            <dd class="case"><span class="t_identifier" title="The most important meal">BREAKFAST</span></dd>
                            <dd class="case" data-attributes="[{&quot;name&quot;:&quot;bdk\\\\Test\\\\Debug\\\\Fixture\\\\Enum\\\\ExampleCaseAttribute&quot;,&quot;arguments&quot;:[]}]"><span class="t_identifier">LUNCH</span></dd>
                            <dd class="case"><span class="t_identifier">DINNER</span></dd>
                            <dt class="properties">properties</dt>
                            <dd class="isReadOnly property public"><span class="t_modifier_public">public</span> <span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> <span class="t_identifier">name</span> <span class="t_operator">=</span> <span class="t_string">DINNER</span></dd>
                            <dt class="methods">methods</dt>
                            <dd class="isStatic method public" data-implements="UnitEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">cases</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">array</span></dd>
                            <dd class="isStatic method public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">prepare</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$meal</span> <span class="t_operator">=</span> <span class="t_object t_parameter-default" data-accessible="public"><span class="t_const" title="The most important meal
                                Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_identifier">BREAKFAST</span></span></span></span><span class="t_punct">)</span></dd>
                            </dl>
                            </div></dd>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                        <dt class="cases">cases</dt>
                        <dd class="case"><span class="t_identifier" title="The most important meal">BREAKFAST</span></dd>
                        <dd class="case" data-attributes="[{&quot;name&quot;:&quot;bdk\\\\Test\\\\Debug\\\\Fixture\\\\Enum\\\\ExampleCaseAttribute&quot;,&quot;arguments&quot;:[]}]"><span class="t_identifier">LUNCH</span></dd>
                        <dd class="case"><span class="t_identifier">DINNER</span></dd>
                        <dt class="properties">properties</dt>
                        <dd class="isReadOnly property public"><span class="t_modifier_public">public</span> <span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> <span class="t_identifier">name</span> <span class="t_operator">=</span> <span class="t_string">BREAKFAST</span></dd>
                        <dt class="methods">methods</dt>
                        <dd class="isStatic method public" data-implements="UnitEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">cases</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">array</span></dd>
                        <dd class="isStatic method public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">prepare</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$meal</span> <span class="t_operator">=</span> <span class="t_object t_parameter-default" data-accessible="public"><span class="t_const" title="The most important meal
                            Meals PHPDoc"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>Meals</span><span class="t_operator">::</span><span class="t_identifier">BREAKFAST</span></span></span></span><span class="t_punct">)</span></dd>
                        </dl>
                        </div></li>',
                ),
            ),

            'basicCaseCollectFalse' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                    Debug::meta('cfg', 'caseCollect', false),
                ),
                array(
                    'entry' => function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        $this->assertIsArray($abs['cases']);
                        $this->assertEmpty($abs['cases']);
                    },
                    'html' => '%a<dt class="cases">cases <i>not collected</i></dt>%a',
                )
            ),

            'basicCaseOutputFalse' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST,
                    Debug::meta('cfg', 'caseOutput', false),
                ),
                array(
                    'entry' => function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        $this->assertIsArray($abs['cases']);
                        $this->assertCount(3, $abs['cases']);
                    },
                    'html' => '%a<dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                        <dt class="properties">properties</dt>%a',
                )
            ),


            'backed' => array(
                'log',
                array(
                    \bdk\Test\Debug\Fixture\Enum\MealsBacked::BREAKFAST,
                ),
                array(
                    'entry' => function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        $this->assertAbstractionType($abs);
                        $this->assertSame(array(
                            'BREAKFAST' => array(
                                'attributes' => array(),
                                'desc' => null,
                                'value' => 'breakfast',
                            ),
                            'LUNCH' => array(
                                'attributes' => array(),
                                'desc' => null,
                                'value' => 'lunch',
                            ),
                            'DINNER' => array(
                                'attributes' => array(),
                                'desc' => null,
                                'value' => 'dinner',
                            )
                        ), $abs['cases']);
                    },
                    'html' => '<li class="m_log"><div class="t_object" data-accessible="public"><span class="t_const"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>MealsBacked</span><span class="t_operator">::</span><span class="t_identifier">BREAKFAST</span></span>
                        <dl class="object-inner">
                        <dt class="t_modifier_final">final</dt>
                        <dt>implements</dt>
                        <dd class="interface"><span class="classname">BackedEnum</span></dd>
                        <dd class="interface"><span class="classname">UnitEnum</span></dd>
                        <dt class="constants">constants</dt>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">ENUM_VALUE</span> <span class="t_operator">=</span> <div class="t_object" data-accessible="private"><span class="t_const"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>MealsBacked</span><span class="t_operator">::</span><span class="t_identifier">DINNER</span></span>
                            <dl class="object-inner">
                            <dt class="t_modifier_final">final</dt>
                            <dt>implements</dt>
                            <dd class="interface"><span class="classname">BackedEnum</span></dd>
                            <dd class="interface"><span class="classname">UnitEnum</span></dd>
                            <dt class="constants">constants</dt>
                            <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">ENUM_VALUE</span> <span class="t_operator">=</span> <div class="t_object" data-accessible="private"><span class="t_const"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Enum\</span>MealsBacked</span><span class="t_operator">::</span><span class="t_identifier">DINNER</span></span>
                                <span class="t_recursion">*RECURSION*</span></div></dd>
                            <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                            <dt class="cases">cases</dt>
                            <dd class="case"><span class="t_identifier">BREAKFAST</span> <span class="t_operator">=</span> <span class="t_string">breakfast</span></dd>
                            <dd class="case"><span class="t_identifier">LUNCH</span> <span class="t_operator">=</span> <span class="t_string">lunch</span></dd>
                            <dd class="case"><span class="t_identifier">DINNER</span> <span class="t_operator">=</span> <span class="t_string">dinner</span></dd>
                            <dt class="properties">properties</dt>
                            <dd class="isReadOnly property public"><span class="t_modifier_public">public</span> <span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> <span class="t_identifier">name</span> <span class="t_operator">=</span> <span class="t_string">DINNER</span></dd>
                            <dd class="isReadOnly property public"><span class="t_modifier_public">public</span> <span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> <span class="t_identifier">value</span> <span class="t_operator">=</span> <span class="t_string">dinner</span></dd>
                            <dt class="methods">methods</dt>
                            <dd class="isStatic method public" data-implements="UnitEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">cases</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">array</span></dd>
                            <dd class="isStatic method public" data-implements="BackedEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">from</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">int</span> <span class="t_parameter-name">$value</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">static</span></dd>
                            <dd class="isStatic method public" data-implements="BackedEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">tryFrom</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">int</span> <span class="t_parameter-name">$value</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">static</span></dd>
                            </dl>
                            </div></dd>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier">REGULAR_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">test</span></dd>
                        <dt class="cases">cases</dt>
                        <dd class="case"><span class="t_identifier">BREAKFAST</span> <span class="t_operator">=</span> <span class="t_string">breakfast</span></dd>
                        <dd class="case"><span class="t_identifier">LUNCH</span> <span class="t_operator">=</span> <span class="t_string">lunch</span></dd>
                        <dd class="case"><span class="t_identifier">DINNER</span> <span class="t_operator">=</span> <span class="t_string">dinner</span></dd>
                        <dt class="properties">properties</dt>
                        <dd class="isReadOnly property public"><span class="t_modifier_public">public</span> <span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> <span class="t_identifier">name</span> <span class="t_operator">=</span> <span class="t_string">BREAKFAST</span></dd>
                        <dd class="isReadOnly property public"><span class="t_modifier_public">public</span> <span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> <span class="t_identifier">value</span> <span class="t_operator">=</span> <span class="t_string">breakfast</span></dd>
                        <dt class="methods">methods</dt>
                        <dd class="isStatic method public" data-implements="UnitEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">cases</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">array</span></dd>
                        <dd class="isStatic method public" data-implements="BackedEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">from</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">int</span> <span class="t_parameter-name">$value</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">static</span></dd>
                        <dd class="isStatic method public" data-implements="BackedEnum"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">tryFrom</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">int</span> <span class="t_parameter-name">$value</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">static</span></dd>
                        </dl>
                        </div></li>',
                ),
            ),
        );
    }
}
