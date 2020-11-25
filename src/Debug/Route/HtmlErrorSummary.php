<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\Highlight;
use bdk\Debug\Route\Html as RouteHtml;
use bdk\ErrorHandler;

/**
 * Output a summary of errors
 */
class HtmlErrorSummary
{

    protected $debug;
    protected $html;
    protected $errorHandler;
    protected $routeHtml;
    protected $stats = array();

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
     * Returns an error summary
     *
     * @param array $stats error statistics
     *
     * @return LogEntry|false
     */
    public function build($stats)
    {
        $this->stats = $stats;
        $summary = ''
            . $this->buildFatal()
            . $this->buildInConsole()
            . $this->buildNotInConsole();
        if (!$summary) {
            return false;
        }
        $classes = \array_keys(\array_filter(array(
            'error-summary' => true,
            'have-fatal' => isset($this->stats['counts']['fatal']),
        )));
        return new LogEntry(
            $this->routeHtml->debug->getChannel('phpError'),
            'alert',
            array(
                $summary
            ),
            array(
                'attribs' => array(
                    'class' => \implode(' ', $classes),
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
        $haveFatal = isset($this->stats['counts']['fatal']);
        if (!$haveFatal) {
            return '';
        }
        $error = $this->errorHandler->get('lastError');
        $isHtml = $error['isHtml'];
        $html = '<div class="error-fatal">'
            . '<h3>' . $error['typeStr'] . '</h3>'
            . '<ul class="list-unstyled no-indent">';
        $html .= $this->html->buildTag(
            'li',
            array(),
            ($isHtml
                ? $error['message']
                : \htmlspecialchars($error['message'])
            )
        );
        $this->debug->addPlugin(new Highlight());
        $backtrace = $error['backtrace'];
        if (\is_array($backtrace) && \count($backtrace) > 1) {
            // more than one trace frame
            // Don't inspect objects when dumping trace arguments...  potentially huge objects
            $objectsExclude = $this->debug->getCfg('objectsExclude');
            $this->debug->setCfg('objectsExclude', \array_merge($objectsExclude, array('*')));
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
                        array($this->routeHtml->dump, 'tableMarkupFunction'),
                        array($this->routeHtml->dump, 'tableAddContextRow'),
                    ),
                )
            );
            $this->debug->methodTable->onLog($logEntry);
            $table = $this->routeHtml->dump->table->build($logEntry['args'][0], $logEntry['meta']);
            // restore previous objectsExclude
            $this->debug->setCfg('objectsExclude', $objectsExclude);
            $html .= '<li class="m_trace" data-detect-files="true">' . $table . '</li>';
        } elseif ($backtrace === false) {
            $html .= '<li>Want to see a backtrace here?  Install <a target="_blank" href="https://xdebug.org/docs/install">xdebug</a> PHP extension.</li>';
        } elseif ($backtrace === null) {
            $fileLines = $error['context'];
            $html .= $this->html->buildTag(
                'li',
                array(
                    'class' => 't_string no-quotes',
                    'data-file' => $error['file'],
                    'data-line' => $error['line'],
                ),
                \sprintf('%s (line %s)', $error['file'], $error['line'])
            );
            $html .= '<li>'
                . '<pre class="highlight line-numbers" data-line="' . $error['line'] . '" data-start="' . \key($fileLines) . '">'
                    . '<code class="language-php">'
                        . \htmlspecialchars(\implode($fileLines))
                    . '</code>'
                . '</pre>'
                . '</li>';
        }
        $html .= '</ul>'
            . '</div>';
        return $html;
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
        $header = 'There were ' . $this->stats['inConsole'] . ' errors';
        $haveFatal = isset($this->stats['counts']['fatal']);
        if ($haveFatal) {
            $countNonFatal = $this->stats['inConsole'] - $this->stats['counts']['fatal']['inConsole'];
            $header = \sprintf(
                'There %s %d additional %s',
                $countNonFatal === 1 ? 'was' : 'were',
                $countNonFatal,
                $countNonFatal === 1 ? 'error' : 'errors'
            );
        } elseif ($this->stats['inConsoleCategories'] === 1) {
            return $this->buildInConsoleOneCat();
        }
        $html = '<h3>' . $header . ':</h3>' . "\n";
        $html .= '<ul class="list-unstyled">';
        foreach ($this->stats['counts'] as $category => $a) {
            if (!$a['inConsole'] || $category === 'fatal') {
                continue;
            }
            $html .= $this->html->buildTag(
                'li',
                array(
                    'class' => 'error-' . $category,
                    'data-count' => $a['inConsole'],
                ),
                $category . ': ' . $a['inConsole']
            );
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Returns summary for errors that were logged to console (while collect = true)
     *
     * Assumes only 1 category of error was logged
     * However, multiple errors in this category may have been logged
     *
     * @return string
     */
    protected function buildInConsoleOneCat()
    {
        $category = null;
        $catStats = array();
        // find category
        foreach ($this->stats['counts'] as $category => $catStats) {
            if ($catStats['inConsole']) {
                break;
            }
        }
        if ($category === 'fatal') {
            return '';
        }
        $catStrings = array(
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
        $countInCat = $catStats['inConsole'];
        $header = $catStrings[$category]['header'];
        $msg = \sprintf($catStrings[$category]['msg'], $countInCat);
        if ($countInCat === 1) {
            $header = \ucfirst($category);
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
        return '<h3>' . $header . '</h3>'
            . '<ul class="list-unstyled">'
                . $this->html->buildTag(
                    'li',
                    array(
                        'class' => 'error-' . $category,
                        'data-count' => $countInCat,
                    ),
                    $msg
                )
                . '</ul>';
    }

    /**
     * Returns summary for errors that occurred while log collect = false
     *
     * @return string
     */
    protected function buildNotInConsole()
    {
        if (!$this->stats['notInConsole']) {
            return '';
        }
        $errors = $this->errorHandler->get('errors');
        $lis = array();
        foreach ($errors as $err) {
            if (
                \array_intersect_assoc(array(
                    // at least one of these is true
                    'category' => 'fatal',
                    'inConsole' => true,
                    'isSuppressed' => true,
                ), $err->getValues())
            ) {
                continue;
            }
            $lis[] = \sprintf(
                '<li>%s: %s (line %s): %s</li>',
                $err['typeStr'],
                $err['file'],
                $err['line'],
                $err['isHtml']
                    ? $err['message']
                    : \htmlspecialchars($err['message'])
            );
        }
        if (!$lis) {
            return '';
        }
        $count = \count($lis);
        $header = \sprintf(
            'There %s captured while not collecting debug log',
            $count === 1
                ? 'was 1 error'
                : 'were ' . $count . ' errors'
        );
        return '<h3>' . $header . '</h3>'
            . '<ul class="list-unstyled">' . "\n"
            . \implode("\n", $lis) . "\n"
            . '</ul>';
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
