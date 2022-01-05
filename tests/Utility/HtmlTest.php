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
                    'class' => array(
                        'test' => true,
                        'nope' => false,
                        'dupe',
                        'dupe',
                    ),
                    'title' => 'Pork & Beans',
                    'value' => '',      // value=""
                    'style' => array('position' => 'absolute','display' => 'inline-block'),
                    'dingus' => array('unknown array'),
                    'hidden' => false,
                    'foo' => false,     // not a valid boolean attrib - we'll not output regardless
                    'bar' => true,      // not a valid boolean attrib - we'll output anyhow : bar="bar"
                    'AUTOCOMPLETE',     // autocomplete="on"
                    'disabled',         // disabled="disabled"
                    'draggable' => true,
                    'spellcheck',
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
                    . ' draggable="true"'
                    . ' height="100"'
                    . ' spellcheck="true"'
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
                    . ' class="foo bar"'
                    . ' placeholder="&quot;quotes&quot; &amp; ampersands"'
                    . ' autocomplete=off'
                    . ' required=required'
                    . ' autofocus'
                    . ' notabool'
                    . ' value=""'
                    . ' data-null="null"'
                    . ' data-obj="{&quot;foo&quot;:&quot;bar&quot;}"'
                    . ' data-str = "foo"'
                    . ' data-zero="0"'
                    . ' draggable="true"'
                    . ' width="100"',
                ),
                'expect' => array(
                    'autocomplete' => 'off',
                    'autofocus' => true,
                    'class' => array('bar', 'foo'),
                    'data-null' => null,
                    'data-obj' => array('foo' => 'bar'),
                    'data-str' => 'foo',
                    'data-zero' => 0,
                    'draggable' => true,
                    'notabool' => '',
                    'placeholder' => '"quotes" & ampersands',
                    'required' => true,
                    'value' => '',
                    'width' => 100,
                ),
            ),
            array(
                'params' => array(
                    'class="still string" value="sun &amp; ski" data-true="true" data-false="false" data-null="null" data-list="[&quot;a&quot;,&quot;b&quot;]"',
                    false,
                ),
                'expect' => array(
                    'class' => 'still string',
                    'data-false' => 'false',
                    'data-list' => '["a","b"]',
                    'data-null' => 'null',
                    'data-true' => 'true',
                    'value' => 'sun & ski',
                ),
            ),
        );
        foreach ($testStack as $test) {
            $ret = \call_user_func_array('bdk\\Debug\\Utility\Html::parseAttribString', $test['params']);
            $this->assertSame($test['expect'], $ret);
        }
    }

    /**
     * @param string $tagName  tag name
     * @param array  $attribs  tag attributes
     * @param string innerhtml inner html
     * @param string $expect   built tag
     *
     * @return void
     *
     * @dataProvider providerTestBuildTag
     */
    public function testBuildTag($tagName, $attribs, $innerhtml, $expect)
    {
        $tag = Html::buildTag($tagName, $attribs, $innerhtml);
        $this->assertSame($expect, $tag);
    }

    public function providerTestBuildTag()
    {
        return array(
            // 0
            array(
                'embed',
                array(
                    'type' => 'video/webm',
                    'src' => '/media/cc0-videos/flower.mp4',
                    'width' => 250,
                    'height' => 200,
                ),
                null,
                '<embed height="200" src="/media/cc0-videos/flower.mp4" type="video/webm" width="250" />',
            ),
            // 1
            array(
                'a',
                array(
                    'href' => 'http://127.0.0.1/',
                    'title' => 'Pork & Beans',
                ),
                'Click Here!',
                '<a href="http://127.0.0.1/" title="Pork &amp; Beans">Click Here!</a>',
            ),
        );
    }


    public function providerTestParseTag()
    {
        return array(
            // 0
            array(
                'tag' => '<div class="test" ><i>stuff</i> &amp; things</div>',
                'expect' => array(
                    'tagname' => 'div',
                    'attribs' => array(
                        'class' => array('test'),
                    ),
                    'innerhtml' => '<i>stuff</i> &amp; things',
                ),
            ),
            // 1
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
            // 2
            array(
                'tag' => '</ hr>',
                'expect' => array(
                    'tagname' => 'hr',
                    'attribs' => array(),
                    'innerhtml' => null,
                ),
            ),
            // 3
            array(
                'tag' => 'not a tag',
                'expect' => false,
            ),
        );
    }

    /**
     * @param string $tag    Html tag to parse
     * @param array  $expect expected return value
     *
     * @return void
     *
     * @dataProvider providerTestParseTag
     */
    public function testParseTag($tag, $expect)
    {
        $parsed = Html::parseTag($tag);
        $this->assertSame($expect, $parsed);
    }
}
