<?php

namespace bdk\Test\Debug\Route\Html;

use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Html Error Symmary
 *
 * @covers \bdk\Debug\Route\Html\ErrorSummary
 * @covers \bdk\Debug\Route\Html\FatalError
 */
class ErrorSummaryTest extends DebugTestFramework
{
    public function testNotInConsole()
    {
        parent::$allowError = true;
        $this->debug->setCfg(array(
            'collect' => false,
            'outputCss' => false,
            'outputScript' => false,
        ));
        $this->debug->errorHandler->handleError(E_NOTICE, 'This is a notice', __FILE__, __LINE__);
        $line = __LINE__ - 1;
        $output = $this->debug->output();
        $this->assertStringContainsString(
            '<div class="alert-error error-summary m_alert" data-channel="general.phpError" data-detect-files="true" role="alert"><h3>There was 1 error captured while not collecting debug log</h3>',
            $output
        );
        $this->assertStringContainsString(
            '<li class="error-notice">Notice: ' . __FILE__ . ' (line ' . $line . '): This is a notice</li>',
            $output
        );
    }

    public function testSingleCategory()
    {
        parent::$allowError = true;
        $this->debug->errorHandler->handleError(E_NOTICE, 'This is a notice', __FILE__, __LINE__);
        $line = __LINE__ - 1;
        $output = $this->debug->output();
        $this->assertTrue(true);
        $this->assertStringContainsString(
            '<div class="alert-error error-summary m_alert" data-channel="general.phpError" data-detect-files="true" role="alert"><h3>Notice</h3>' . "\n"
            . '<ul class="list-unstyled in-console"><li class="error-notice" data-count="1">' . __FILE__ . ' (line ' . $line . '): This is a notice</li></ul></div>',
            $output
        );
    }

    public function testFatalContext()
    {
        parent::$allowError = true;
        \ob_start();
        $backtrace = new \bdk\Test\Debug\Mock\Backtrace();
        $backtrace->setReturn(null);
        \bdk\Debug\Utility\Reflection::propSet($this->debug->errorHandler, 'backtrace', $backtrace);
        $this->debug->eventManager->publish(\bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN, new \bdk\PubSub\Event(null, array(
            'error' => array(
                'type' => E_ERROR,
                'message' => 'fatality',
                'file' => __FILE__,
                'line' => __LINE__,
            ),
        )));
        $output = \ob_get_clean();
        \bdk\Debug\Utility\Reflection::propSet($this->debug->errorHandler, 'backtrace', null);
        // $expectMatch = '%a<tr class="context" style="display:table-row;"><td colspan="4"><pre class="highlight line-numbers" data-line="%d" data-start="%d"><code class="language-php">%a';
        $expectMatch = '%a<li class="error-fatal m_error" data-channel="general.phpError" data-detect-files="true"><span class="no-quotes t_string">Fatal Error: </span><span class="t_string">fatality</span>, <span class="t_string">' . __FILE__ . ' (line %d)</span><pre class="highlight line-numbers" data-line="%d" data-start="%d"><code class="language-php">%a';
        $this->assertStringMatchesFormat($expectMatch, $output);
    }
}
