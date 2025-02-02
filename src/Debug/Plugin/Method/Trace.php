<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
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

    /** @var string[] */
    protected $methods = [
        'trace',
    ];

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
     * Params may be passed in any order
     *
     * @param bool             $inclContext (`false`) Include code snippet
     * @param string           $caption     ('trace') Specify caption for the trace table
     * @param int              $limit       (0) limit the number of stack frames returned.  By default (limit = 0) all stack frames are collected
     * @param array|\Exception $trace       Optionally specify trace
     *
     * @return Debug
     *
     * @since 3.3 added limit argument
     */
    public function trace($inclContext = false, $caption = 'trace', $limit = 0, $trace = null)
    {
        if (!$this->debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            return $this->debug;
        }
        $argsDefault = $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__);
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            $this->getTraceArgs(\func_get_args(), $argsDefault),
            array(),
            $argsDefault,
            [
                'caption',
                'inclContext',
                'limit',
                'trace',
            ]
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
        $trace = $this->getTrace($logEntry);
        $files = [];
        foreach ($trace as $frame) {
            if (empty($frame['evalLine']) && !empty($frame['file'])) {
                $files[] = $frame['file'];
            }
        }
        $meta['tableInfo']['commonRowInfo'] = array(
            'commonFilePrefix' => $this->debug->stringUtil->commonPrefix($files),
        );
        if ($meta['inclContext']) {
            $this->debug->addPlugin($this->debug->pluginHighlight, 'highlight');
        }
        unset($meta['trace']);
        $logEntry['args'] = [$trace];
        $logEntry['meta'] = $meta;
        $this->evalRows($logEntry);
        $this->debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
    }

    /**
     * Extract the trace from the log entry or generate it
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array
     */
    private function getTrace(LogEntry $logEntry)
    {
        $meta = $this->getMeta($logEntry);
        $getOptions = $meta['inclArgs'] ? Backtrace::INCL_ARGS : 0;
        $getOptions |= $meta['inclInternal'] ? Backtrace::INCL_INTERNAL : 0;
        $trace = \is_array($meta['trace'])
            ? $meta['trace']
            : $this->debug->backtrace->get(
                $getOptions,
                $meta['limit'],
                $meta['trace'] // null or Exception
            );
        if ($meta['inclInternal']) {
            $trace = $this->removeMinInternal($trace);
        }
        if ($meta['inclContext']) {
            $trace = $this->debug->backtrace->addContext($trace);
        }
        return $trace;
    }

    /**
     * Remove Internal Frames
     *
     * @param array $trace Backtrace frames
     *
     * @return array
     */
    private function removeMinInternal(array $trace)
    {
        $count = \count($trace);
        $internalClasses = [
            __CLASS__,
            'bdk\PubSub\Manager',
        ];
        for ($i = 3; $i < $count; $i++) {
            $frame = $trace[$i];
            \preg_match('/^(?P<classname>.+)->(?P<function>.+)$/', $frame['function'], $matches);
            $isInternal = \in_array($matches['classname'], $internalClasses, true) || $matches['function'] === 'publishBubbleEvent';
            if ($isInternal === false) {
                break;
            }
        }
        return \array_slice($trace, $i);
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
            'columns' => ['file','line','function'],
            'detectFiles' => true,
            'inclArgs' => null,  // incl arguments with context?
                                 // will default to $inclContext
                                 //   may want to set meta['cfg']['objectsExclude'] = '*'
            'inclContext' => false,
            'inclInternal' => false,
            'limit' => 0,
            'sortable' => false,
            'trace' => null,  // set to array or Exception to specify trace
        ), $logEntry['meta']);

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
        $logEntry['args'] = [$trace];
    }

    /**
     * Get trace args
     *
     * @param array $argsPassed  arguments passed to trace()
     * @param array $argsDefault default arguments
     *
     * @return array args passed to trace() but sorted to match method signature
     */
    private function getTraceArgs(array $argsPassed, array $argsDefault)
    {
        $argsTyped = array();
        \array_walk($argsPassed, static function ($val) use (&$argsTyped) {
            $type = \gettype($val);
            $typeToKey = array(
                'array' => 'trace',
                'boolean' => 'inclContext',
                'integer' => 'limit',
                'object' => 'trace', // exception
                'string' => 'caption',
            );
            $key = isset($typeToKey[$type])
                ? $typeToKey[$type]
                : null;
            if ($key && isset($argsTyped[$key]) === false) {
                $argsTyped[$key] = $val;
                return;
            }
            $argsTyped[] = $val;
        });
        if (!empty($argsTyped['limit'])) {
            $argsDefault['caption'] = 'trace (limited to ' . $argsTyped['limit'] . ')';
        }
        $args = \array_merge($argsDefault, $argsTyped);
        return \array_values($args);
    }
}
