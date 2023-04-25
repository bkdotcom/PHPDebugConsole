<?php

namespace bdk\Test\Debug\Route;

use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Text
 *
 * @covers \bdk\Debug\Route\Email
 */
class EmailTest extends DebugTestFramework
{
    public function testEmail()
    {
        parent::$allowError = true;

        $this->debug->errorHandler->handleError(E_STRICT, 'your code sucks', __FILE__, __LINE__);
        $line = __LINE__ - 1;
        $this->debug->errorHandler->handleError(E_WARNING, 'hide me', __FILE__, __LINE__);
        $error = $this->debug->errorHandler->getLastError();
        $error['isSuppressed'] = true;

        $this->debug->setCfg('route', 'email');
        $this->debug->output();
        $this->assertSame('Debug Log: Error', $this->emailInfo['subject']);
        $stringExpect = 'Error(s):' . "\n"
            . __FILE__ . ':' . "\n"
            . ' Line ' . $line . ': (Strict) your code sucks';
        $this->assertStringContainsString($stringExpect, $this->emailInfo['body']);
        $data = \bdk\Debug\Utility\SerializeLog::unserialize($this->emailInfo['body']);
        $this->assertSame(array(
            'config',
            'version',
            'alerts',
            'log',
            'logSummary',
            'requestId',
            'runtime',
        ), \array_keys($data));
    }
}
