<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route\Html;

use bdk\Debug\LogEntry;
use bdk\Debug\Route\Html as RouteHtml;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;

/**
 * Output a summary of errors
 */
class ErrorSummary
{
    protected $debug;
    protected $html;
    protected $errorHandler;
    protected $routeHtml;
    protected $stats = array();
    private $catStrings = array(
        'deprecated' => array(
            'header' => 'Deprecated',
            'msg' => 'There were %d deprecated notices',
        ),
        'strict' => array(
            'header' => 'Strict',
            'msg' => 'There were %d strict errors',
        ),
        'error' => array(
            'header' => 'Errors',
            'msg' => 'There were %d errors',
        ),
        'notice' => array(
            'header' => 'Notices',
            'msg' => 'There were %d notices',
        ),
        'warning' => array(
            'header' => 'Warnings',
            'msg' => 'There were %d warnings',
        ),
    );

    /**
     * Constructor
     *
     * @param RouteHtml    $routeHtml    Route\Html instance
     * @param ErrorHandler $errorHandler ErrorHandler instance
     */
    public function __construct(RouteHtml $routeHtml, ErrorHandler $errorHandler)
    {
        $this->routeHtml = $routeHtml;
        $this->errorHandler = $errorHandler;
        $this->debug = $routeHtml->debug;
        $this->html = $this->debug->html;
    }

    /**
     * Returns an error summary LogEntry
     * LogEntry['args'][0] could be an empty string
     *
     * @param array $stats error statistics
     *
     * @return LogEntry
     */
    public function build($stats)
    {
        $this->stats = $stats;
        $summary = ''
            . $this->buildFatal()
            . $this->buildInConsole()
            . $this->buildNotInConsole();
        return new LogEntry(
            $this->routeHtml->debug->getChannel('phpError'),
            'alert',
            array($summary),
            array(
                'attribs' => array(
                    'class' => array(
                        'error-summary' => true,
                        'have-fatal' => \array_sum($this->stats['counts']['fatal']) > 0,
                    ),
                    'data-detect-files' => true,
                ),
                'dismissible' => false,
                'level' => 'error',
                'sanitize' => false,
            )
        );
    }

    /**
     * If lastError was fatal, output the error
     *
     * @return string
     */
    protected function buildFatal()
    {
        if (\array_sum($this->stats['counts']['fatal']) === 0) {
            // no fatal errors
            return '';
        }
        $error = $this->errorHandler->get('lastError');
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
            . $this->buildFatalMoreInfo($error)
            . '</ul>'
            . '</div>';
    }

    /**
     * Build fata error's backtrace, not-avail message, or context
     *
     * @param Error $error Error instance
     *
     * @return string html snippet
     */
    protected function buildFatalMoreInfo(Error $error)
    {
        $this->debug->addPlugin($this->debug->pluginHighlight);
        $backtrace = $error['backtrace'];
        if (\is_array($backtrace) && \count($backtrace) > 1) {
            return $this->buildFatalBacktrace($backtrace);
        }
        if ($backtrace === false) {
            return '<li>Want to see a backtrace here?  Install <a target="_blank" href="https://xdebug.org/docs/install">xdebug</a> PHP extension.</li>' . "\n";
        }
        return $this->buildFatalContext($error);
    }

    /**
     * Build backtrace table
     *
     * @param array $backtrace backtrace from error object
     *
     * @return string
     */
    protected function buildFatalBacktrace($backtrace)
    {
        // more than one trace frame
        // Don't inspect objects when dumping trace arguments...  potentially huge objects
        $objectsExcludeBak = $this->debug->getCfg('objectsExclude');
        $this->debug->setCfg('objectsExclude', \array_merge($objectsExcludeBak, array('*')));
        $logEntry = new LogEntry(
            $this->debug,
            'table',
            array($backtrace),
            array(
                'attribs' => array(
                    'class' => 'trace trace-context table-bordered',
                ),
                'caption' => 'trace',
                'columns' => array('file','line','function'),
                'inclContext' => true,
                'onBuildRow' => array(
                    array($this->routeHtml->dumper->helper, 'tableMarkupFunction'),
                    array($this->routeHtml->dumper->helper, 'tableAddContextRow'),
                ),
            )
        );
        $this->debug->methodTable->doTable($logEntry);
        // restore previous objectsExclude
        $this->debug->setCfg('objectsExclude', $objectsExcludeBak);
        return '<li class="m_trace" data-detect-files="true">' . $this->routeHtml->dumper->table->build(
            $logEntry['args'][0],
            $logEntry['meta']
        ) . '</li>' . "\n";
    }

    /**
     * Build lines surrounding fatal error
     *
     * @param Error $error Error instance
     *
     * @return string html snippet
     */
    private function buildFatalContext(Error $error)
    {
        return ''
            . $this->html->buildTag(
                'li',
                array(
                    'class' => 't_string no-quotes',
                    'data-file' => $error['file'],
                    'data-line' => $error['line'],
                ),
                \sprintf('%s (line %s)', $error['file'], $error['line'])
            ) . "\n"
            . '<li>'
                . $this->routeHtml->dumper->helper->buildContext($error['context'], $error['line'])
            . '</li>' . "\n";
    }

    /**
     * Returns summary for errors that were logged to console (while collect = true)
     *
     * @return string
     */
    protected function buildInConsole()
    {
        if (!$this->stats['inConsole']) {
            return '';
        }
        $haveFatal = \array_sum($this->stats['counts']['fatal']) > 0;
        if (!$haveFatal && \count($this->stats['inConsoleCategories']) === 1) {
            // only one category of error and it's not fatal
            return $this->buildInConsoleOneCat();
        }
        $html = '<h3>' . $this->buildInConsoleHeader() . '</h3>' . "\n";
        $html .= '<ul class="list-unstyled in-console">' . "\n";
        foreach ($this->stats['counts'] as $category => $vals) {
            if ($category === 'fatal' || !$vals['inConsole']) {
                continue;
            }
            $html .= $this->html->buildTag(
                'li',
                array(
                    'class' => 'error-' . $category,
                    'data-count' => $vals['inConsole'],
                ),
                $category . ': ' . $vals['inConsole']
            ) . "\n";
        }
        $html .= '</ul>' . "\n";
        return $html;
    }

    /**
     * Build header
     *
     * @return string
     */
    private function buildInConsoleHeader()
    {
        $haveFatal = \array_sum($this->stats['counts']['fatal']) > 0;
        if ($haveFatal === false) {
            return 'There were ' . $this->stats['inConsole'] . ' errors';
        }
        $inConsoleCount = $this->stats['inConsole'] - $this->stats['counts']['fatal']['inConsole'];
        return \sprintf(
            'There %s %d additional %s',
            $inConsoleCount === 1 ? 'was' : 'were',
            $inConsoleCount,
            $inConsoleCount === 1 ? 'error' : 'errors'
        );
    }

    /**
     * Returns summary for errors that were logged to console (while collect = true)
     *
     * Assumes only 1 category of error was logged
     * (multiple errors in this category may have been logged)
     *
     * @return string
     */
    protected function buildInConsoleOneCat()
    {
        $category = $this->stats['inConsoleCategories'][0];
        $inConsoleCount = $this->stats['counts'][$category]['inConsole'];
        $msg = \sprintf($this->catStrings[$category]['msg'], $inConsoleCount);
        if ($inConsoleCount === 1) {
            $error = $this->getErrorsInCategory($category)[0];
            $msg = \sprintf(
                '%s (line %s): %s',
                $error['file'],
                $error['line'],
                $error['isHtml']
                    ? $error['message']
                    : \htmlspecialchars($error['message'])
            );
        }
        return '<h3>' . $this->buildInConsoleOneCatHeader() . '</h3>' . "\n"
            . '<ul class="list-unstyled in-console">'
                . $this->html->buildTag(
                    'li',
                    array(
                        'class' => 'error-' . $category,
                        'data-count' => $inConsoleCount,
                    ),
                    $msg
                )
            . '</ul>';
    }

    /**
     * Build header
     *
     * @return string
     */
    private function buildInConsoleOneCatHeader()
    {
        $category = $this->stats['inConsoleCategories'][0];
        $inConsoleCount = $this->stats['counts'][$category]['inConsole'];
        return $inConsoleCount === 1
            ? \ucfirst($category)
            :  $this->catStrings[$category]['header'];
    }

    /**
     * Returns summary for errors that occurred while log collect = false
     *
     * @return string
     */
    protected function buildNotInConsole()
    {
        $errors = $this->getErrorsNotInConsole();
        $count = \count($errors);
        if ($count === 0) {
            return '';
        }
        $header = \sprintf(
            'There %s captured while not collecting debug log',
            $count === 1 ? 'was 1 error' : 'were ' . $count . ' errors'
        );
        return '<h3>' . $header . '</h3>' . "\n"
            . '<ul class="list-unstyled">' . "\n"
            . \implode("\n", \array_map(static function (Error $error) {
                return \sprintf(
                    '<li class="error-%s">%s: %s (line %s): %s</li>',
                    $error['category'],
                    $error['typeStr'],
                    $error['file'],
                    $error['line'],
                    $error['isHtml']
                        ? $error['message']
                        : \htmlspecialchars($error['message'])
                );
            }, $errors)) . "\n"
            . '</ul>' . "\n";
    }

    /**
     * Get all unsupressed errors that were not logged in console
     *
     * @return Error[]
     */
    private function getErrorsNotInConsole()
    {
        if (!$this->stats['notInConsole']) {
            return array();
        }
        $errors = $this->errorHandler->get('errors');
        $errorsNotInConsole = array();
        foreach ($errors as $error) {
            if (
                \array_intersect_assoc(array(
                    // at least one of these is true
                    'category' => 'fatal',
                    'inConsole' => true,
                    'isSuppressed' => true,
                ), $error->getValues())
            ) {
                continue;
            }
            $errorsNotInConsole[] = $error;
        }
        return $errorsNotInConsole;
    }

    /**
     * Get the error for the given category
     *
     * @param string $category error category
     *
     * @return \bdk\ErrorHandler\Error[]
     */
    protected function getErrorsInCategory($category)
    {
        $errors = $this->errorHandler->get('errors');
        $errorsInCat = array();
        foreach ($errors as $err) {
            if ($err['category'] === $category && $err['inConsole']) {
                $errorsInCat[] = $err;
            }
        }
        return $errorsInCat;
    }
}
