<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeResourceTest extends DebugTestFramework
{

    public function dumpProvider()
    {
        $fh = fopen(__FILE__, 'r');
        // val, html, text script
        return array(
            array($fh,
                '<span class="t_resource">Resource id #'.(int) $fh.': stream</span>',
                'Resource id #'.(int) $fh.': stream',
                '"Resource id #'.(int) $fh.': stream"'
            ),
        );
        fclose($fh);
    }
}
