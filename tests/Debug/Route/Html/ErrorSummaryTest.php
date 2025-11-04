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
            '<div class="alert-error error-summary m_alert" data-channel="general.phpError" role="alert"><h3>There was 1 error captured while not collecting debug log</h3>',
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
            '<div class="alert-error error-summary m_alert" data-channel="general.phpError" role="alert"><h3>Notice</h3>' . "\n"
            . '<ul class="list-unstyled in-console"><li class="error-notice" data-count="1">' . __FILE__ . ' (line ' . $line . '): This is a notice</li></ul></div>',
            $output
        );
    }

    public function testFatalContextNoBacktrace()
    {
        parent::$allowError = true;
        \ob_start();
        $backtrace = new \bdk\Test\Debug\Mock\Backtrace();
        $backtrace->setReturn(null);
        \bdk\Debug\Utility\Reflection::propSet($this->debug->errorHandler, 'backtrace', $backtrace);
        $this->debug->eventManager->publish(\bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN, new \bdk\PubSub\Event(null, array(
            'error' => array(
                'file' => __FILE__,
                'line' => __LINE__,
                'message' => 'fatality',
                'type' => E_ERROR,
            ),
        )));
        $output = \ob_get_clean();
        \bdk\Debug\Utility\Reflection::propSet($this->debug->errorHandler, 'backtrace', null);
        $expectMatch = '%a<li class="error-fatal m_error" data-channel="general.phpError"><span class="no-quotes t_string">Fatal Error: </span><span class="t_string">fatality</span>, '
            . '<span class="no-quotes t_string" data-type-more="filepath"><span class="t_string"><span class="file-path-rel">' . \dirname(__FILE__) . '/' . '</span><span class="file-basename">' . \basename(__FILE__) . '</span></span> (line <span class="t_int">%d</span>)</span>'
            . '<pre class="highlight line-numbers" data-line="%d" data-line-offset="%d" data-start="%d"><code class="language-php">%a';
        // \bdk\Debug::varDump('expect', $expectMatch);
        // \bdk\Debug::varDump('actual', $output);
        $this->assertStringMatchesFormat($expectMatch, $output);
    }

    public function testFatalBacktraceWithContext()
    {
        parent::$allowError = true;
        \ob_start();
        $backtrace = new \bdk\Test\Debug\Mock\Backtrace();
        $line1 = 42;
        $line2 = 69;
        $backtrace->setReturn([
            array(
                'file' => __FILE__,
                'function' => 'Dingus::Dongus()',
                'line' => $line1,
            ),
            array(
                'file' => __FILE__,
                'function' => 'Meow::mix()',
                'line' => $line2,
            ),
        ]);
        \bdk\Debug\Utility\Reflection::propSet($this->debug->errorHandler, 'backtrace', $backtrace);
        $this->debug->eventManager->publish(\bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN, new \bdk\PubSub\Event(null, array(
            'error' => array(
                'file' => __FILE__,
                'line' => __LINE__,
                'message' => 'fatality',
                'type' => E_ERROR,
            ),
        )));
        $output = \ob_get_clean();
        \bdk\Debug\Utility\Reflection::propSet($this->debug->errorHandler, 'backtrace', null);
        $expectMatch = '%a
            <div class="alert-error error-summary have-fatal m_alert" data-channel="general.phpError" role="alert"><div class="error-fatal"><h3>Fatal Error</h3>
            <ul class="list-unstyled no-indent">
            <li>fatality</li>
            <li class="m_trace">
            <table class="table-bordered trace-context">
            <caption>trace</caption>
            <thead>
            <tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th><th scope="col">function</th></tr>
            </thead>
            <tbody>
            <tr class="expanded" data-toggle="next"><th class="t_int t_key" scope="row">0</th><td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">' . \dirname(__FILE__) . '/' . '</span><span class="file-basename">' . \basename(__FILE__) . '</span></td><td class="t_int">' . $line1 . '</td><td class="t_identifier" data-type-more="method"><span class="classname">Dingus</span><span class="t_operator">::</span><span class="t_name">Dongus()</span></td></tr>
            <tr class="context" style="display:table-row;"><td colspan="4"><pre class="highlight line-numbers" data-line="%d" data-line-offset="%d" data-start="%d"><code class="language-php">%a
            </code></pre></td></tr>

            <tr data-toggle="next"><th class="t_int t_key" scope="row">1</th><td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">' . \dirname(__FILE__) . '/' . '</span><span class="file-basename">' . \basename(__FILE__) . '</span></td><td class="t_int">' . $line2 . '</td><td class="t_identifier" data-type-more="method"><span class="classname">Meow</span><span class="t_operator">::</span><span class="t_name">mix()</span></td></tr>
            <tr class="context"><td colspan="4"><pre class="highlight line-numbers" data-line="%d" data-line-offset="%d" data-start="%d"><code class="language-php">%a
            </code></pre></td></tr>

            </tbody>
            </table>
            </li>
            %a
            <li class="error-fatal m_error" data-channel="general.phpError"><span class="no-quotes t_string">Fatal Error: </span><span class="t_string">fatality</span>, '
            . '<span class="no-quotes t_string" data-type-more="filepath"><span class="t_string"><span class="file-path-rel">' . \dirname(__FILE__) . '/</span><span class="file-basename">' . \basename(__FILE__) . '</span></span> (line <span class="t_int">%d</span>)</span>'
            . '</li>%a';
        // \bdk\Debug::varDump('expect', $expectMatch);
        // \bdk\Debug::varDump('actual', $output);
        self::assertStringMatchesFormatNormalized($expectMatch, $output);
    }
}
