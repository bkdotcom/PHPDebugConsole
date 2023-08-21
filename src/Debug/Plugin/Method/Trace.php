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
            array(
                'columns' => array('file','line','function'),
                'detectFiles' => true,
                'inclArgs' => null,  // incl arguments with context?
                                     // will default to $inclContext
                                     //   may want to set meta['cfg']['objectsExclude'] = '*'
                'sortable' => false,
                'trace' => null,  // set to specify trace
            ),
            $this->debug->rootInstance->getMethodDefaultArgs(__METHOD__),
            array(
                'caption',
                'inclContext',
            )
        );
        if ($logEntry->getMeta('inclArgs') === null) {
            $logEntry->setMeta('inclArgs', $logEntry->getMeta('inclContext'));
        }
        $this->doTrace($logEntry);
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
        $caption = $logEntry->getMeta('caption');
        if (\is_string($caption) === false) {
            $this->debug->warn(\sprintf(
                'trace caption should be a string.  %s provided',
                $this->debug->php->getDebugType($caption)
            ));
            $logEntry->setMeta('caption', 'trace');
        }
        // Get trace and include args if we're including context
        $inclContext = $logEntry->getMeta('inclContext');
        $inclArgs = $logEntry->getMeta('inclArgs');
        $backtrace = isset($logEntry['meta']['trace'])
            ? $logEntry['meta']['trace']
            : $this->debug->backtrace->get($inclArgs ? Backtrace::INCL_ARGS : 0);
        $logEntry->setMeta('trace', null);
        if ($backtrace && $inclContext) {
            $backtrace = $this->debug->backtrace->addContext($backtrace);
            $this->debug->addPlugin($this->debug->pluginHighlight, 'highlight');
        }
        $logEntry['args'] = array($backtrace);
        $this->debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
        $this->debug->log($logEntry);
    }
}
