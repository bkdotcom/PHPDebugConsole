<?php

namespace bdk\Test\Debug\Route;

use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Text
 *
 * @covers \bdk\Debug\Route\AbstractRoute
 * @covers \bdk\Debug\Route\Text
 */
class TextTest extends DebugTestFramework
{
    public function testProcessLogEntries()
    {
        \bdk\Test\Debug\Helper::setPrivateProp(\bdk\Debug::getInstance()->getRoute('text'), 'shouldIncludeCache', array());
        $this->debug->alert('This will self destruct');
        $this->outputTest(array(
            'text' => 'This will self destruct',
        ));
    }

    public function testGetDumper()
    {
        $dumper = $this->debug->getRoute('text')->dumper;
        $this->assertInstanceOf('bdk\\Debug\\Dump\\Text', $dumper);
    }
}
