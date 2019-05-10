<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3.0
 */

namespace bdk\Debug\Output;

use bdk\ErrorHandler;
use bdk\Debug\Output\Html as OutputHtml;

/**
 * Output a summary of errors
 */
class HtmlErrorSummary
{

    protected $outputHtml;
    protected $errorHandler;
    protected $stats = array();

    /**
     * Constructor
     *
     * @param OutputHtml   $outputHtml   OutputHtml instance
     * @param ErrorHandler $errorHandler ErrorHandler instance
     */
    public function __construct(OutputHtml $outputHtml, ErrorHandler $errorHandler)
    {
        $this->outputHtml = $outputHtml;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Returns an error summary
     *
     * @param array $stats error statistics
     *
     * @return string html
     */
    public function build($stats)
    {
        $this->stats = $stats;
        return ''
            .$this->buildFatal()
            .$this->buildInConsole()
            .$this->buildNotInConsole();
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
        $lastError = $this->errorHandler->get('lastError');
        $isHtml = $lastError['isHtml'];
        $backtrace = $lastError['backtrace'];
        $html = '<h3>Fatal Error</h3>';
        $html .= '<ul class="list-unstyled indent">';
        if (\count($backtrace) > 1) {
            // more than one trace frame
            $table = $this->outputHtml->buildTable(
                $backtrace,
                array(
                    'attribs' => 'trace table-bordered',
                    'caption' => 'trace',
                    'columns' => array('file','line','function'),
                )
            );
            $html .= '<li>'.$lastError['message'].'</li>';
            $html .= '<li class="m_trace">'.$table.'</li>';
            if (!$isHtml) {
                $html = \str_replace($lastError['message'], \htmlspecialchars($lastError['message']), $html);
            }
        } else {
            $keysKeep = array('typeStr','message','file','line');
            $lastError = \array_intersect_key($lastError, \array_flip($keysKeep));
            $html .= '<li>'.$this->outputHtml->dump($lastError).'</li>';
            if ($isHtml) {
                $html = \str_replace(\htmlspecialchars($lastError['message']), $lastError['message'], $html);
            }
        }
        if (!\extension_loaded('xdebug')) {
            $html .= '<li>Want to see a backtrace here?  Install <a target="_blank" href="https://xdebug.org/docs/install">xdebug</a> PHP extension.</li>';
        }
        $html .= '</ul>';
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
        } else {
            $header = 'There were '.$this->stats['inConsole'].' errors';
        }
        $html = '<h3>'.$header.':</h3>'."\n";
        $html .= '<ul class="list-unstyled indent">';
        foreach ($this->stats['counts'] as $category => $a) {
            if (!$a['inConsole'] || $category == 'fatal') {
                continue;
            }
            $html .= '<li class="error-'.$category.'">'.$category.': '.$a['inConsole'].'</li>';
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
        // find category
        foreach ($this->stats['counts'] as $category => $catStats) {
            if ($catStats['inConsole']) {
                break;
            }
        }
        if ($category == 'fatal') {
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
        if ($countInCat == 1) {
            $header = \ucfirst($category);
            $error = $this->getErrorsInCategory($category)[0];
            $msg = $error['file']. '(line '.$error['line'].'): '
                .($error['isHtml']
                    ? $error['message']
                    : \htmlspecialchars($error['message']));
        } else {
            $header = $catStrings[$category]['header'];
            $msg = \sprintf($catStrings[$category]['msg'], $countInCat);
        }
        $html = '<h3>'.$header.'</h3>'
            .'<ul class="list-unstyled indent">'
                .'<li class="error-'.$category.'">'.$msg.'</li>'
                .'</ul>';
        return $html;
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
            if (\array_intersect_assoc(array(
                // at least one of these is true
                'category' => 'fatal',
                'inConsole' => true,
                'isSuppressed' => true,
            ), $err->getValues())) {
                continue;
            }
            $lis[] = '<li>'.$err['typeStr'].': '.$err['file'].' (line '.$err['line'].'): '
                .($err['isHtml']
                    ? $err['message']
                    : \htmlspecialchars($err['message']))
                .'</li>';
        }
        if (!$lis) {
            return '';
        }
        $count = \count($lis);
        $header = \sprintf(
            '%s %s captured while not collecting debug log',
            $this->stats['inConsole'] || isset($this->stats['counts']['fatal'])
                ? 'Additionally, there'
                : 'There',
            $count === 1
                ? 'was 1 error'
                : 'were '.$count.' errors'
        );
        $html = '<h3>'.$header.'</h3>'
            .'<ul class="list-unstyled indent">'."\n"
            .\implode("\n", $lis)."\n"
            .'</ul>';
        return $html;
    }

    /**
     * Get the error for the given category
     *
     * @param string $category error category
     *
     * @return Event[]
     */
    protected function getErrorsInCategory($category)
    {
        $errors = $this->errorHandler->get('errors');
        $errorsInCat = array();
        foreach ($errors as $err) {
            if ($err['category'] == $category && $err['inConsole']) {
                $errorsInCat[] = $err;
            }
        }
        return $errorsInCat;
    }
}
