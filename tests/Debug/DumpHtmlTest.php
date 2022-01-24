<?php

namespace bdk\Test\Debug;

/**
 * PHPUnit tests for Debug Methods
 */
class DumpHtmlTest extends DebugTestFramework
{
    /**
     * Test MarkupIdentifier
     */
    public function testMarkupIdentifier()
    {
        $valDumper = $this->debug->getDump('html')->valDumper;
        $this->assertSame(
            '<span class="classname">Foo</span>',
            $valDumper->markupIdentifier('Foo')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span>',
            $valDumper->markupIdentifier('Foo\\Bar')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span><span class="t_operator">::</span><span class="t_identifier">baz</span>',
            $valDumper->markupIdentifier('Foo\\Bar::baz')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span><span class="t_operator">-&gt;</span><span class="t_identifier">baz</span>',
            $valDumper->markupIdentifier('Foo\\Bar->baz')
        );
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\</span>Bar</span><span class="t_operator">::</span><span class="t_identifier">Baz</span>',
            $valDumper->markupIdentifier(array('Foo\\Bar', 'Baz'))
        );

        // test alt tag
        $this->assertSame(
            '<div class="classname">Foo</div>',
            $valDumper->markupIdentifier('Foo', false, 'div')
        );

        // test attribs
        $this->assertSame(
            '<span class="classname" title="test">Foo</span>',
            $valDumper->markupIdentifier('Foo', false, 'span', array('title' => 'test'))
        );

        // test wbr
        $this->assertSame(
            '<span class="classname"><span class="namespace">Foo\<wbr /></span>Bar</span><wbr /><span class="t_operator">-&gt;</span><span class="t_identifier">baz</span>',
            $valDumper->markupIdentifier('Foo\\Bar->baz', false, 'span', null, true)
        );
    }

    public function testAbstractionAttribs()
    {
        $valDumper = $this->debug->getDump('html')->valDumper;
        $abs = $this->debug->abstracter->crateWithVals('someFilePath', array(
            'attribs' => array(
                'data-file' => '/path/to/file.php',
                'class' => 'foo bar', // test that output as "bar foo"
            ),
        ));
        $this->assertSame(
            '<span class="bar foo t_string" data-file="/path/to/file.php">someFilePath</span>',
            $valDumper->dump($abs)
        );
    }
}
