<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\Debug\LogEntry;
use bdk\Debug\Abstraction\Abstracter;

/**
 * Output log as <script> tag
 */
class Script extends Base
{

    protected $consoleMethods = array(
        'assert',
        // 'count',    // output as log
        'error',
        'group',
        'groupCollapsed',
        'groupEnd',
        'info',
        'log',
        'table',
        // 'time',     // output as log
        'timeEnd',  // PHPDebugConsole never generates a timeEnd entry
        'trace',
        'warn',
    );

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->dump = $debug->dumpBase;
    }

    /**
     * output the log as javascript
     *    which outputs the log to the console
     *
     * @param Event $event debug.output event object
     *
     * @return string|void
     */
    public function processlogEntries(Event $event)
    {
        $this->data = $this->debug->getData();
        $errorStats = $this->debug->errorStats();
        $errorStr = '';
        if ($errorStats['inConsole']) {
            $errorStr = 'Errors: ';
            foreach ($errorStats['counts'] as $category => $vals) {
                $errorStr .= $vals['inConsole'] . ' ' . $category . ', ';
            }
            $errorStr = \substr($errorStr, 0, -2);
        }
        $str = '';
        $str .= '<script type="text/javascript">' . "\n";
        $str .= $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupCollapsed',
            array(
                'PHP',
                (isset($_SERVER['REQUEST_METHOD']) && isset($_SERVER['REQUEST_URI'])
                    ? $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']
                    : ''),
                $errorStr,
            )
        ));
        $str .= $this->processAlerts();
        $str .= $this->processSummary();
        $str .= $this->processLog();
        $str .= $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupEnd'
        ));
        $str .= '</script>' . "\n";
        $this->data = array();
        $event['return'] .= $str;
    }

    /**
     * Return log entry as javascript console.xxxx
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if ($method == 'table') {
            $logEntry->setMeta('forceArray', false);
        }
        $this->dump->processLogEntry($logEntry);
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($method == 'assert') {
            \array_unshift($args, false);
        } elseif (\in_array($method, array('error','warn'))) {
            if (isset($meta['file'])) {
                $args[] = $meta['file'] . ': line ' . $meta['line'];
            }
        } elseif (!\in_array($method, $this->consoleMethods)) {
            $method = 'log';
        }
        foreach ($args as $k => $arg) {
            $arg = \json_encode($this->dump->dump($arg), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            // ensure - however unlikely - that </script> doesn't appear inside our <script>
            $arg = \str_replace('</script>', '<\\/script>', $arg);
            $args[$k] = $arg;
        }
        $str = 'console.' . $method . '(' . \implode(',', $args) . ');' . "\n";
        $str = \str_replace(\json_encode(Abstracter::UNDEFINED), 'undefined', $str);
        return $str;
    }
}
