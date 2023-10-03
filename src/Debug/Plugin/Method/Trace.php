<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Backtrace;
use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Trace method
 */
class Trace implements SubscriberInterface
{
    use CustomMethodTrait;

    protected $methods = array(
        'trace',
    );

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * Log a stack trace
     *
     * Essentially PHP's `debug_backtrace()`, but displayed as a table
     *
     * @param bool   $inclContext Include code snippet
     * @param string $caption     (optional) Specify caption for the trace table
     *
     * @return $this
     */
    public function trace($inclContext = false, $caption = 'trace')
    {
        if (!$this->debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            return $this->debug;
        }
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->debug->rootInstance->getMethodDefaultArgs(__METHOD__),
            array(
                'caption',
                'inclContext',
            )
        );
        $this->doTrace($logEntry);
        $this->debug->log($logEntry);
        return $this->debug;
    }

    /**
     * Handle trace()
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doTrace(LogEntry $logEntry)
    {
        $this->debug = $logEntry->getSubject();
        $meta = $this->getMeta($logEntry);
        $trace = isset($meta['trace'])
            ? $meta['trace']
            : $this->debug->backtrace->get($meta['inclArgs'] ? Backtrace::INCL_ARGS : 0);
        if ($trace && $meta['inclContext']) {
            $trace = $this->debug->backtrace->addContext($trace);
            $this->debug->addPlugin($this->debug->pluginHighlight, 'highlight');
        }
        unset($meta['trace']);
        $logEntry['args'] = array($trace);
        $logEntry['meta'] = $meta;
        $this->evalRows($logEntry);
        $this->debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
    }

    /**
     * Set default meta values
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array meta values
     */
    private function getMeta(LogEntry $logEntry)
    {
        $meta = \array_merge(array(
            'caption' => 'trace',
            'columns' => array('file','line','function'),
            'detectFiles' => true,
            'inclArgs' => null,  // incl arguments with context?
                                 // will default to $inclContext
                                 //   may want to set meta['cfg']['objectsExclude'] = '*'
            'inclContext' => false,
            'sortable' => false,
            'trace' => null,  // set to specify trace
        ), $logEntry['meta']);

        if (\is_string($meta['caption']) === false) {
            $this->debug->warn(\sprintf(
                'trace caption should be a string.  %s provided',
                $this->debug->php->getDebugType($meta['caption'])
            ));
            $meta['caption'] = 'trace';
        }
        if ($meta['inclArgs'] === null) {
            $meta['inclArgs'] = $meta['inclContext'];
        }
        return $meta;
    }

    /**
     * Handle "eval()'d code" frames
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function evalRows(LogEntry $logEntry)
    {
        $meta = \array_replace_recursive(array(
            'tableInfo' => array(
                'rows' => array(),
            ),
        ), $logEntry['meta']);
        $trace = $logEntry['args'][0];
        foreach ($trace as $i => $frame) {
            if (!empty($frame['evalLine'])) {
                $meta['tableInfo']['rows'][$i]['attribs'] = array(
                    'data-file' => $frame['file'],
                    'data-line' => $frame['line'],
                );
                $trace[$i]['file'] = 'eval()\'d code';
                $trace[$i]['line'] = $frame['evalLine'];
            }
        }
        $logEntry['meta'] = $meta;
        $logEntry['args'] = array($trace);
    }
}
