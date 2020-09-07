<?php

namespace bdk\DebugTests\Utility;

use bdk\Debug\Utility\Html;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class HtmlTest extends DebugTestFramework
{

    /**
     * Test
     *
     * @return void
     */
    public function testBuildAttribString()
    {
        $testStack = array(
            array(
                'attribs' => array(
                    'src' => '/path/to/image.png',
                    'width' => 80,
                    'height' => '100',
                    'class' => array('test','dupe','dupe'),
                    'title' => 'Pork & Beans',
                    'value' => '',      // value=""
                    'style' => array('position' => 'absolute','display' => 'inline-block'),
                    'dingus' => array('unknown array'),
                    'hidden' => false,
                    'foo' => false,     // not a valid boolean attrib - we'll not output regardless
                    'bar' => true,      // not a valid boolean attrib - we'll output anyhow : bar="bar"
                    'AUTOCOMPLETE',     // autocomplete="on"
                    'disabled',         // disabled="disabled"
                    'data-null' => null,
                    'data-false' => false,
                    'data-string' => 'wassup?',
                    'data-true' => true,
                    'data-array' => array('foo' => 'bar'),
                    'data-obj' => (object) array('key' => 'val'),
                ),
                'expect' => ''
                    . ' autocomplete="on"'
                    . ' bar="bar"'
                    . ' class="dupe test"'
                    . ' data-array="{&quot;foo&quot;:&quot;bar&quot;}"'
                    . ' data-false="false"'
                    . ' data-null="null"'
                    . ' data-obj="{&quot;key&quot;:&quot;val&quot;}"'
                    . ' data-string="wassup?"'
                    . ' data-true="true"'
                    . ' disabled="disabled"'
                    . ' height="100"'
                    . ' src="/path/to/image.png"'
                    . ' style="display:inline-block;position:absolute;"'
                    . ' title="Pork &amp; Beans"'
                    . ' value=""'
                    . ' width="80"',
            ),
            array(
                'attribs' => array(
                    'class' => array(),     // not output
                    'style' => array(),     // not output
                    'data-empty-string' => '',
                    'data-empty-array' => array(),
                    'data-empty-obj' => (object) array(),
                    'data-false' => false,
                    'data-null' => null,    // not output
                ),
                'expect' => ' data-empty-array="[]" data-empty-obj="{}" data-empty-string="" data-false="false" data-null="null"',
            ),
            array(
                'attribs' => '',
                'expect' => '',
            ),
            array(
                'attribs' => '  foo=bar bar="baz"',
                'expect' => ' bar="baz" foo="bar"',
            ),
        );
        foreach ($testStack as $test) {
            $ret = Html::buildAttribString($test['attribs']);
            $this->assertSame($test['expect'], $ret);
        }
    }

    /**
     * @return void
     */
    public function testParseAttribString()
    {
        $testStack = array(
            array(
                'params' => array(''),
                'expect' => array(),
            ),
            array(
                'params' => array(
                    ' '
                    . ' placeholder="&quot;quotes&quot; &amp; ampersands"'
                    . ' autocomplete=off'
                    . ' required=required'
                    . ' autofocus'
                    . ' notabool'
                    . ' value=""'
                    . ' data-null="null"'
                    . ' data-obj="{&quot;foo&quot;:&quot;bar&quot;}"'
                    . ' data-str = "foo"'
                    . ' data-zero="0"',
                ),
                'expect' => array(
                    'autocomplete' => 'off',
                    'autofocus' => true,
                    'data-null' => null,
                    'data-obj' => array('foo' => 'bar'),
                    'data-str' => 'foo',
                    'data-zero' => 0,
                    'notabool' => '',
                    'placeholder' => '"quotes" & ampersands',
                    'required' => true,
                    'value' => '',
                ),
            ),
            array(
                'params' => array(
                    'value="sun &amp; ski" data-true="true" data-false="false" data-null="null" data-list="[&quot;a&quot;,&quot;b&quot;]"',
                    false,
                ),
                'expect' => array(
                    'data-false' => 'false',
                    'data-list' => '["a","b"]',
                    'data-null' => 'null',
                    'data-true' => 'true',
                    'value' => 'sun & ski',
                ),
            ),
        );
        foreach ($testStack as $test) {
            $ret = call_user_func_array('bdk\\Debug\\Utility\Html::parseAttribString', $test['params']);
            $this->assertSame($test['expect'], $ret);
        }
    }

    /**
     * @return void
     */
    public function testParseTag()
    {
        $testStack = array(
            array(
                'tag' => '<div class="test" ><i>stuff</i> &amp; things</div>',
                'expect' => array(
                    'tagname' => 'div',
                    'attribs' => array(
                        'class' => 'test',
                    ),
                    'innerhtml' => '<i>stuff</i> &amp; things',
                ),
            ),
            array(
                'tag' => '<input name="name" value="Billy" required/>',
                'expect' => array(
                    'tagname' => 'input',
                    'attribs' => array(
                        'name' => 'name',
                        'required' => true,
                        'value' => 'Billy',
                    ),
                    'innerhtml' => null,
                ),
            ),
            array(
                'tag' => '</ hr>',
                'expect' => array(
                    'tagname' => 'hr',
                    'attribs' => array(),
                    'innerhtml' => null,
                ),
            ),
        );
        foreach ($testStack as $test) {
            $ret = Html::parseTag($test['tag']);
            $this->assertSame($test['expect'], $ret);
        }
    }
}
