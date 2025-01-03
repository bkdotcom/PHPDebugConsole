<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Html;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Utility\Html
 * @covers \bdk\Debug\Utility\HtmlBuild
 * @covers \bdk\Debug\Utility\HtmlParse
 * @covers \bdk\Debug\Utility\HtmlSanitize
 */
class HtmlTest extends DebugTestFramework
{
    /**
     * Test
     *
     * @param array|string $attribs
     * @param string       $expect
     *
     * @return void
     *
     * @dataProvider providerBuildAttribString
     */
    public function testBuildAttribString($attribs, $expect)
    {
        $attribString = Html::buildAttribString($attribs);
        self::assertSame($expect, $attribString);
    }

    /**
     * @param string $tagName   tag name
     * @param array  $attribs   tag attributes
     * @param string $innerhtml inner html
     * @param string $expect    expected built tag
     *
     * @dataProvider providerBuildTag
     *
     * @return void
     */
    public function testBuildTag($tagName, $attribs, $innerhtml, $expect)
    {
        $tag = Html::buildTag($tagName, $attribs, $innerhtml);
        self::assertSame($expect, $tag);
    }

    /**
     * @return void
     *
     * @dataProvider providerParseAttribString
     */
    public function testParseAttribString($paramString, $options, $expect)
    {
        $parsed = Html::parseAttribString($paramString, $options);
        self::assertSame($expect, $parsed);
    }

    /**
     * @param string $tag    Html tag to parse
     * @param array  $expect expected return value
     *
     * @dataProvider providerParseTag
     *
     * @return void
     */
    public function testParseTag($tag, $expect)
    {
        $parsed = Html::parseTag($tag);
        self::assertSame($expect, $parsed);
    }

    /**
     * @param string $html   Html to sanitize
     * @param array  $expect expected html
     *
     * @dataProvider providerSanitize
     *
     * @return void
     */
    public function testSanitize($html, $expect)
    {
        $sanitized = Html::sanitize($html);
        self::assertSame($expect, $sanitized);
    }

    public static function providerBuildAttribString()
    {
        return array(
            array(
                array(
                    'id' => 'unique identifier',
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
                    'baz' => null,
                    'autocapitalize' => 'off',
                    'AUTOCOMPLETE',       // autocomplete="on"
                    'translate' => false, // translate="no"
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
                ''
                    . ' autocapitalize="off"'
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
                    . ' id="unique_identifier"'
                    . ' spellcheck="true"'
                    . ' src="/path/to/image.png"'
                    . ' style="display:inline-block;position:absolute;"'
                    . ' title="Pork &amp; Beans"'
                    . ' translate="no"'
                    . ' value=""'
                    . ' width="80"',
            ),
            array(
                array(
                    'class' => array(),     // not output
                    'style' => array(),     // not output
                    'data-empty-string' => '',
                    'data-empty-array' => array(),
                    'data-empty-obj' => (object) array(),
                    'data-false' => false,
                    'data-null' => null,    // not output
                ),
                ' data-empty-array="[]" data-empty-obj="{}" data-empty-string="" data-false="false" data-null="null"',
            ),
            array(
                array(
                    'id' => null,
                    'class' => 'foo bar',     // not output
                ),
                ' class="bar foo"',
            ),
            array(
                '',
                '',
            ),
            array(
                '  foo=bar bar="baz"',
                ' bar="baz" foo="bar"',
            ),
        );
    }

    public static function providerBuildTag()
    {
        return array(
            'selfClosing' => array(
                'EMBED',
                array(
                    'type' => 'video/webm',
                    'src' => '/media/cc0-videos/flower.mp4',
                    'width' => 250,
                    'height' => 200,
                ),
                null,
                '<embed height="200" src="/media/cc0-videos/flower.mp4" type="video/webm" width="250" />',
            ),
            'innerHtml' => array(
                'a',
                array(
                    'href' => 'http://127.0.0.1/',
                    'title' => 'Pork & Beans',
                ),
                '<i class="icon"></i> Click Here!',
                '<a href="http://127.0.0.1/" title="Pork &amp; Beans"><i class="icon"></i> Click Here!</a>',
            ),
            'innerHtmlClosure' => array(
                'div',
                array(
                    'class' => array('test', 'best'),
                ),
                static function () {
                    return 'innerHtml';
                },
                '<div class="best test">innerHtml</div>',
            ),
            'bool' => array(
                'input',
                array(
                    'autocapitalize' => 'sentences', // enum : on/off  (or "arbitrary" string)
                    'autocomplete' => null, // enum : on/off
                    'autofocus' => 'focus', // bool
                    'disabled' => 'false',  // bool
                    'draggable' => 'false', // enum : true/false
                    'required' => 'required', // bool
                    'spellcheck' => true,   // enum : true/false
                    'translate' => 'false',    // enum : yes/no
                ),
                null,
                '<input autocapitalize="sentences" autocomplete="off" autofocus="autofocus" draggable="false" required="required" spellcheck="true" translate="no" />',
            ),
            'boolEnum1' => array(
                'div',
                array(
                    'autocapitalize' => 'off',
                    'contenteditable' => false,
                    'draggable' => 'false',
                    'translate' => 'no',
                ),
                'test',
                '<div autocapitalize="off" contenteditable="false" draggable="false" translate="no">test</div>',
            ),
            'boolEnum2' => array(
                'div',
                array(
                    'autocapitalize' => 'other',
                    'contenteditable' => true,
                    'translate' => false,
                ),
                'test',
                '<div autocapitalize="other" contenteditable="true" translate="no">test</div>',
            ),
        );
    }

    public static function providerParseAttribString()
    {
        return array(
            array(
                '',
                null,
                'expect' => array(
                    'class' => array(),
                ),
            ),
            array(
                ' '
                    . ' class="foo bar"'
                    . ' placeholder="&quot;quotes&quot; &amp; ampersands"'
                    . ' autocapitalize="Bob"'
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
                null,
                array(
                    'autocapitalize' => 'Bob',
                    'autocomplete' => false,
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
                'class="still string"
                    value="sun &amp; ski"
                    data-true="true"
                    data-false="false"
                    data-null="null"
                    data-list="[&quot;a&quot;,&quot;b&quot;]"',
                false,
                array(
                    'class' => 'still string',
                    'data-false' => 'false',
                    'data-list' => '["a","b"]',
                    'data-null' => 'null',
                    'data-true' => 'true',
                    'value' => 'sun & ski',
                ),
            ),
        );
    }

    public static function providerParseTag()
    {
        return array(
            'tagsAndEntities' => array(
                'tag' => '<div class="test" ><i>stuff</i> &amp; things</div>',
                'expect' => array(
                    'tagname' => 'div',
                    'attribs' => array(
                        'class' => array('test'),
                    ),
                    'innerhtml' => '<i>stuff</i> &amp; things',
                ),
            ),
            'selfCloser' => array(
                'tag' => '<INPUT name="name" value="Billy" required/>',
                'expect' => array(
                    'tagname' => 'input',
                    'attribs' => array(
                        'class' => array(),
                        'name' => 'name',
                        'required' => true,
                        'value' => 'Billy',
                    ),
                    'innerhtml' => null,
                ),
            ),
            'voidElementWithInvalidSlash' => array(
                'tag' => '</ hr>',
                'expect' => array(
                    'tagname' => 'hr',
                    'attribs' => array(
                        'class' => array(),
                    ),
                    'innerhtml' => null,
                ),
            ),
            // 1
            array(
                'tag' => 'not a tag',
                'expect' => false,
            ),
        );
    }

    public static function providerSanitize()
    {
        return array(
            'script' => array(
                'be<fore<script>if (1 < 2) alert(dang)</script>af>ter',
                \htmlspecialchars('be<fore<script>if (1 < 2) alert(dang)</script>af>ter'),
            ),
            'entity' => array(
                'foo &amp; > bar',
                'foo &amp; &gt; bar',
            ),
            'nonWhitelistAttribute' => array(
                '<p onclick="alert(&quot;boo&quot;)">Brad <em>was</em> here</p> <xmp>Now he\'s gone</xmp>',
                '<p>Brad <em>was</em> here</p> &lt;xmp&gt;Now he\'s gone&lt;/xmp&gt;',
            ),
            'notATag' => array(
                'UserName: <enter your user name>',
                'UserName: &lt;enter your user name&gt;',
            ),
            'notAVoid' => array(
                '<p/>Brad was here</p>',
                '<p>Brad was here</p>',
            ),
            'invalidClose1' => array(
                // our parseTag() method will allow/correct this, but sanitize will escape it
                '<IMG alt="text" bogus>foo & bar</IMG>',
                '<img alt="text" />foo &amp; bar&lt;/IMG&gt;',
            ),
            'invalidClose2' => array(
                // our parseTag() method will allow/correct this, but sanitize will escape it
                'ding</br>dong',
                'ding&lt;/br&gt;dong',
            ),
        );
    }
}
