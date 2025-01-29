<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Route\Html;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\Html as RouteHtml;
use bdk\Debug\Utility\Html as HtmlUtil;
use bdk\ErrorHandler\Error;

/**
 * Output fatal error info
 */
class FatalError
{
    /** @var Debug */
    protected $debug;

    /** @var HtmlUtil */
    protected $html;

    /** @var RouteHtml */
    protected $routeHtml;

    /**
     * Constructor
     *
     * @param RouteHtml $routeHtml Route\Html instance
     */
    public function __construct(RouteHtml $routeHtml)
    {
        $this->routeHtml = $routeHtml;
        $this->debug = $this->routeHtml->debug;
        $this->html = $this->debug->html;
    }

    /**
     * Output a fatal error
     *
     * @param Error $error Error instance (fatal error)
     *
     * @return string html fragment
     */
    public function output(Error $error)
    {
        return '<div class="error-fatal">'
            . '<h3>' . $error['typeStr'] . '</h3>' . "\n"
            . '<ul class="list-unstyled no-indent">' . "\n"
            . $this->html->buildTag(
                'li',
                array(),
                $error['isHtml']
                    ? $error['message']
                    : \htmlspecialchars($error['message'])
            ) . "\n"
            . $this->buildMoreInfo($error)
            . '</ul>'
            . '</div>';
    }

    /**
     * Build fatal error's backtrace, not-avail message, or context
     *
     * @param Error $error Error instance
     *
     * @return string html snippet
     */
    protected function buildMoreInfo(Error $error)
    {
        $this->debug->addPlugin($this->debug->pluginHighlight);
        $backtrace = $error['backtrace'];
        if (\is_array($backtrace) && \count($backtrace) > 1) {
            return $this->buildBacktrace($backtrace);
        }
        if ($backtrace === false) {
            return '<li>Want to see a backtrace here?  Install/enable <a target="_blank" href="https://xdebug.org/docs/install">xdebug</a> PHP extension.</li>' . "\n";
        }
        return $this->buildContext($error);
    }

    /**
     * Build backtrace table
     *
     * @param array $trace backtrace from error object
     *
     * @return string
     */
    protected function buildBacktrace(array $trace)
    {
        $cfgWas = $this->debug->setCfg(array(
            'maxDepth' => 0,
            // Don't inspect objects when dumping trace arguments...  potentially huge objects
            'objectsExclude' => ['*'],
        ), Debug::CONFIG_NO_PUBLISH);
        $logEntry = new LogEntry(
            $this->debug,
            'table',
            [],
            array(
                'attribs' => array(
                    'class' => 'trace trace-context table-bordered',
                ),
                'inclContext' => true,
                'onBuildRow' => [
                    [$this->routeHtml->dumper->helper, 'tableTraceRow'],
                    [$this->routeHtml->dumper->helper, 'tableAddContextRow'],
                ],
                'trace' => $trace,
            )
        );
        $this->debug->rootInstance->getPlugin('methodTrace')->doTrace($logEntry);
        $this->debug->setCfg($cfgWas, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
        return '<li class="m_trace" data-detect-files="true">'
            . $this->routeHtml->dumper->table->build($logEntry['args'][0], $logEntry['meta'])
            . '</li>' . "\n";
    }

    /**
     * Build lines surrounding fatal error
     *
     * @param Error $error Error instance
     *
     * @return string html snippet
     */
    private function buildContext(Error $error)
    {
        $return = $this->html->buildTag(
            'li',
            array(
                'class' => 't_string no-quotes',
                'data-file' => $error['file'],
                'data-line' => $error['line'],
            ),
            $error['fileAndLine']
        ) . "\n";
        if ($error['context']) {
            $return .= '<li>'
                . $this->routeHtml->dumper->helper->buildContext($error['context'], $error['line'])
                . '</li>' . "\n";
        }
        return $return;
    }
}
