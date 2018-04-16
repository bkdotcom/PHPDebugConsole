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
            array(
                'log',
                array( $fh ),
                array(
                    'html' => '<div class="m_log"><span class="t_resource">Resource id #'.(int) $fh.': stream</span></div>',
                    'text' => 'Resource id #'.(int) $fh.': stream',
                    'script' => 'console.log("Resource id #'.(int) $fh.': stream");',
                )
            ),
        );
        fclose($fh);
    }
}
