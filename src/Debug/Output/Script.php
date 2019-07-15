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

namespace bdk\Debug\Output;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log as <script> tag
 */
class Script extends Base
{

    protected $consoleMethods = array(
        'assert',
        'count',    // output as log
        'error',
        'group',
        'groupCollapsed',
        'groupEnd',
        'info',
        'log',
        'table',
        'time',     // output as log
        'timeEnd',  // PHPDebugConsole never generates a timeEnd entry
        'trace',
        'warn',
    );

    /**
     * output the log as javascript
     *    which outputs the log to the console
     *
     * @param Event $event event object
     *
     * @return string|void
     */
    public function onOutput(Event $event)
    {
        $this->data = $this->debug->getData();
        $errorStats = $this->debug->errorStats();
        $errorStr = '';
        if ($errorStats['inConsole']) {
            $errorStr = 'Errors: ';
            foreach ($errorStats['counts'] as $category => $vals) {
                $errorStr .= $vals['inConsole'].' '.$category.', ';
            }
            $errorStr = \substr($errorStr, 0, -2);
        }
        $str = '';
        $str .= '<script type="text/javascript">'."\n";
        $str .= $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupCollapsed',
            array(
                'PHP',
                (isset($_SERVER['REQUEST_METHOD']) && isset($_SERVER['REQUEST_URI'])
                    ? $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']
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
        $str .= '</script>'."\n";
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
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($method == 'alert') {
            list($method, $args) = $this->methodAlert($args, $meta);
        } elseif ($method == 'assert') {
            \array_unshift($args, false);
        } elseif (\in_array($method, array('count','time'))) {
            $method = 'log';
        } elseif (\in_array($method, array('profileEnd','table'))) {
            $method = 'log';
            $asTable = \is_array($args[0]) || $this->debug->abstracter->isAbstraction($args[0], 'object');
            if ($asTable) {
                $method = 'table';
                $args = array($this->methodTable($args[0], $meta['columns']));
            } elseif ($meta['caption']) {
                \array_unshift($args, $meta['caption']);
            }
        } elseif ($method == 'trace') {
            $method = 'table';
            $args = array($this->methodTable($args[0], array('function','file','line')));
        } elseif (\in_array($method, array('error','warn'))) {
            if (isset($meta['file'])) {
                $args[] = $meta['file'].': line '.$meta['line'];
            }
        }
        if (!\in_array($method, $this->consoleMethods)) {
            $method = 'log';
        }
        foreach ($args as $k => $arg) {
            $args[$k] = \json_encode($this->dump($arg));
        }
        $str = 'console.'.$method.'('.\implode(',', $args).');'."\n";
        $str = \str_replace(\json_encode(Abstracter::UNDEFINED), 'undefined', $str);
        return $str;
    }

    /**
     * Dump undefined
     *
     * Returns the undefined constant, which we can replace with "undefined" after json_encoding
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return Abstracter::UNDEFINED;
    }

    /**
     * Handle alert method
     *
     * @param array $args arguments
     * @param array $meta meta info
     *
     * @return array array($method, $args)
     */
    protected function methodAlert($args, $meta)
    {
        $args = array('%c'.$args[0], '');
        $method = $meta['level'];
        $styleCommon = 'padding:5px; line-height:26px; font-size:125%; font-weight:bold;';
        switch ($method) {
            case 'danger':
                // Just use log method... Chrome adds backtrace to error(), which we don't want
                $method = 'log';
                $args[1] = $styleCommon
                    .'background-color: #ffbaba;'
                    .'border: 1px solid #d8000c;'
                    .'color: #d8000c;';
                break;
            case 'info':
                $args[1] = $styleCommon
                    .'background-color: #d9edf7;'
                    .'border: 1px solid #bce8f1;'
                    .'color: #31708f;';
                break;
            case 'success':
                $method = 'info';
                $args[1] = $styleCommon
                    .'background-color: #dff0d8;'
                    .'border: 1px solid #d6e9c6;'
                    .'color: #3c763d;';
                break;
            case 'warning':
                // Just use log method... Chrome adds backtrace to warn(), which we don't want
                $method = 'log';
                $args[1] = $styleCommon
                    .'background-color: #fcf8e3;'
                    .'border: 1px solid #faebcc;'
                    .'color: #8a6d3b;';
                break;
        }
        return array($method, $args);
    }
}
