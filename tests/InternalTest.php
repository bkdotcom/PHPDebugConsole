<?php

namespace bdk\DebugTests;

/**
 * PHPUnit tests for Debug class
 */
class InternalTest extends DebugTestFramework
{

    /**
     * Test
     *
     * @return void
     */
    public function testErrorStats()
    {
        parent::$allowError = true;

        1 / 0;    // warning

        $this->assertSame(array(
            'inConsole' => 1,
            'inConsoleCategories' => 1,
            'notInConsole' => 0,
            'counts' => array(
                'warning' => array(
                    'inConsole' => 1,
                    'notInConsole' => 0,
                )
            ),
        ), $this->debug->errorStats());
    }

    public function testHasLog()
    {
        $this->assertFalse($this->debug->hasLog());
        $this->debug->log('something');
        $this->assertTrue($this->debug->hasLog());
        $this->debug->clear();
        $this->assertFalse($this->debug->hasLog());
    }
}
