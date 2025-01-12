<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Route\Html;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\Html as RouteHtml;
use bdk\Debug\Utility\Html as HtmlUtil;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;

/**
 * Output a summary of errors
 */
class ErrorSummary
{
    /** @var Debug */
    protected $debug;

    /** @var HtmlUtil */
    protected $html;

    /** @var ErrorHandler */
    protected $errorHandler;

    /** @var RouteHtml */
    protected $routeHtml;

    /** @var array{
     *   counts: array<string,array{
     *     inConsole: int,
     *     notInConsole: int,
     *     suppressed: int}>,
     *   inConsole: int,
     *   inConsoleCategories: list<string>,
     *   notInConsole: int,
     * }
     */
    protected $stats = array();

    /** @var array<string,array<string,string>> */
    private $catStrings = array(
        'deprecated' => array(
            'header' => 'Deprecated',
            'msg' => 'There were %d deprecated notices',
        ),
        'error' => array(
            'header' => 'Errors',
            'msg' => 'There were %d errors',
        ),
        'notice' => array(
            'header' => 'Notices',
            'msg' => 'There were %d notices',
        ),
        'strict' => array(
            'header' => 'Strict',
            'msg' => 'There were %d strict errors',
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
            $summary !== ''
                ? $this->routeHtml->debug->getChannel('phpError')
                : $this->debug,
            'alert',
            [$summary],
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
        $haveFatal = \array_sum($this->stats['counts']['fatal']) > 0;
        if ($haveFatal === false) {
            // no fatal errors
            return '';
        }
        $error = $this->errorHandler->get('lastError');
        $fatal = new FatalError($this->routeHtml);
        return $fatal->output($error);
    }

    /**
     * Returns summary for errors that were logged to console (while collect = true)
     *
     * @return string
     */
    protected function buildInConsole()
    {
        $stats = $this->stats;
        if (!$stats['inConsole']) {
            return '';
        }
        $haveFatal = \array_sum($stats['counts']['fatal']) > 0;
        if (!$haveFatal && \count($stats['inConsoleCategories']) === 1) {
            // only one category of error and it's not fatal
            return $this->buildInConsoleOneCat();
        }
        $countsInConsole = $this->getCountsInConsole();
        return '<h3>' . $this->buildInConsoleHeader() . '</h3>' . "\n"
            . '<ul class="list-unstyled in-console">' . "\n"
            . \implode("\n", \array_map(function ($vals) {
                $category = $vals['name'];
                return $this->html->buildTag(
                    'li',
                    array(
                        'class' => 'error-' . $category,
                        'data-count' => $vals['inConsole'],
                    ),
                    $category . ': ' . $vals['inConsole']
                );
            }, $countsInConsole))
            . '</ul>' . "\n";
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
                    '<li class="error-%s">%s: %s: %s</li>',
                    $error['category'],
                    $error['typeStr'],
                    $error['fileAndLine'],
                    $error['isHtml']
                        ? $error['message']
                        : \htmlspecialchars($error['message'])
                );
            }, $errors)) . "\n"
            . '</ul>' . "\n";
    }

    /**
     * Get count statistics for errors logged to console
     *
     * @return array
     */
    private function getCountsInConsole()
    {
        $stats = $this->stats;
        foreach (\array_keys($stats['counts']) as $category) {
            $stats['counts'][$category]['name'] = $category;
        }
        return \array_filter($stats['counts'], static function ($vals) {
            return $vals['name'] !== 'fatal' && $vals['inConsole'];
        });
    }

    /**
     * Get all unsuppressed errors that were not logged in console
     *
     * @return Error[]
     */
    private function getErrorsNotInConsole()
    {
        if (!$this->stats['notInConsole']) {
            return array();
        }
        $errors = $this->errorHandler->get('errors');
        return \array_filter($errors, static function (Error $error) {
            $isErrorInConsole = \count(\array_intersect_assoc(array(
                // at least one of these is true
                'category' => 'fatal',
                'inConsole' => true,
                'isSuppressed' => true,
            ), $error->getValues())) > 0;
            return $isErrorInConsole === false;
        });
    }

    /**
     * Get the error for the given category
     *
     * @param string $category error category
     *
     * @return Error[]
     */
    protected function getErrorsInCategory($category)
    {
        $errors = $this->errorHandler->get('errors');
        return \array_values(\array_filter($errors, static function (Error $error) use ($category) {
            return $error['category'] === $category && $error['inConsole'];
        }));
    }
}
