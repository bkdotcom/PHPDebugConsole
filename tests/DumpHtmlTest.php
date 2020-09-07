<?php

namespace bdk\DebugTests;

/**
 * PHPUnit tests for Debug Methods
 */
class DumpHtmlTest extends DebugTestFramework
{

    /**
     * Test overriding a core method
     */
    public function testMarkupIdentifier()
    {
        $dump = $this->debug->getDump('html');
        $this->assertSame(
            '<span class="classname">Foo</span>',
            $dump->markupIdentifier('Foo')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span>',
            $dump->markupIdentifier('Foo\\Bar')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span><span class="t_operator">::</span><span class="t_identifier">baz</span>',
            $dump->markupIdentifier('Foo\\Bar::baz')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span><span class="t_operator">-&gt;</span><span class="t_identifier">baz</span>',
            $dump->markupIdentifier('Foo\\Bar->baz')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span><span class="t_operator">::</span><span class="t_identifier">Baz</span>',
            $dump->markupIdentifier(array('Foo\\Bar', 'Baz'))
        );

        // test alt tag
        $this->assertSame(
            '<div class="classname">Foo</div>',
            $dump->markupIdentifier('Foo', 'div')
        );

        // test attribs
        $this->assertSame(
            '<span class="classname" title="test">Foo</span>',
            $dump->markupIdentifier('Foo', 'span', array('title' => 'test'))
        );

        // test wbr
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\<wbr /></span>Bar</span><wbr /><span class="t_operator">-&gt;</span><span class="t_identifier">baz</span>',
            $dump->markupIdentifier('Foo\\Bar->baz', 'span', null, true)
        );
    }
}
