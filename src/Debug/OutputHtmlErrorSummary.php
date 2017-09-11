<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.0
 */

namespace bdk\Debug;

/**
 * Output a summary of errors
 */
class OutputHtmlErrorSummary
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
        $html = '';
        $this->stats = $stats;
        $html .= $this->buildFatal();
        $html .= $this->buildInConsole();
        $html .= $this->buildNotInConsole();
        return $html;
    }

    /**
     * If lastError was fatal, output the error
     *
     * @return string
     */
    protected function buildFatal()
    {
        $html = '';
        $lastError = $this->errorHandler->get('lastError');
        if ($lastError && $lastError['category'] === 'fatal') {
            $backtrace = $lastError['backtrace'];
            $keysKeep = array('typeStr','message','file','line');
            $lastError = array_intersect_key($lastError, array_flip($keysKeep));
            $html .= '<h3>Fatal Error</h3>';
            $html .= '<ul class="list-unstyled indent">';
            $html .= '<li>'.(count($backtrace) > 1
                    ? $lastError['message']
                    : $this->outputHtml->dump($lastError) // no trace, or just one frame
                    )
                .'</li>';
            // if only 1 frame in backtrace, don't display trace
            if (count($backtrace) > 1) {
                $table = $this->outputHtml->buildTable($backtrace, 'trace', array('file','line','function'));
                $table = str_replace('<table>', '<table class="trace no-sort">', $table);
                $html .= '<li>'.$table.'</li>';
            } elseif (empty($backtrace)) {
                $html .= '<li>Want to see a backtrace here?  Install <a target="_blank" href="https://xdebug.org/docs/install">xdebug</a> PHP extension.</li>';
            }
            $html .= '</ul>';
        }
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
        if ($this->stats['inConsoleCategories'] == 1) {
            return $this->buildInConsoleOneCat();
        } elseif ($haveFatal) {
            $count = $this->stats['counts']['fatal']['inConsole']
                ? $this->stats['inConsole'] - 1
                : $this->stats['inConsole'];
            $header = $count == 1
                ? 'There was 1 additional error'
                : 'There were '.$count.' additional errors';
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
     * Returns summary for errors that occurred while log collect = false
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
                'msg' => 'There were %s strict errors',
            ),
            'error' => array(
                'header' => 'Errors',
                'msg' => 'There were %s errors',
            ),
            'notice' => array(
                'header' => 'Notices',
                'msg' => 'There were %s notices',
            ),
            'warning' => array(
                'header' => 'Warnings',
                'msg' => 'There were %s warnings',
            ),
        );
        $count = $this->stats['inConsole'];
        if ($count == 1) {
            $header = ucfirst($category);
            $msg .= $this->getErrorByCategory($category);
        } else {
            $header = $catStrings[$category]['header'];
            $msg = sprintf($catStrings[$category]['msg'], $count);
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
        $html = '';
        $errors = $this->errorHandler->get('errors');
        $haveFatal = isset($this->stats['counts']['fatal']);
        $count = 0;
        $lis = array();
        foreach ($errors as $err) {
            if (array_intersect_assoc(array(
                'suppressed' => true,
                'inConsole' => true,
                'category' => 'fatal'
            ), $err->getValues())) {
                continue;
            }
            $count ++;
            $lis[] = '<li>'.$err['typeStr'].': '.$err['file'].' (line '.$err['line'].'): '.$err['message'].'</li>';
        }
        if ($count == 0) {
            return '';
        }
        $html .= '<h3>'
                .($this->stats['inConsole'] || $haveFatal ? 'Additionally, there' : 'There').' '
                .($count == 1 ? 'was 1 error' : 'were '.$count.' errors').' captured while not collecting debug log'
            .'</h3>'
            .'<ul class="list-unstyled indent">'
            .implode("\n", $lis)
            .'</ul>';
        return $html;
    }

    /**
     * Get the error for the given category
     *
     * @param string $category error category
     *
     * @return string|null
     */
    protected function getErrorByCategory($category)
    {
        $errors = $this->errorHandler->get('errors');
        foreach ($errors as $err) {
            if ($err['category'] == $category && $err['inConsole']) {
                return $err['file']. '(line '.$err['line'].'): '.$err['message'];
            }
        }
        return null;
    }
}
