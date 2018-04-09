<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug\Output;

use bdk\PubSub\Event;

/**
 * Output log as <script> tag
 */
class Script extends Base
{

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
        $this->removeHideIfEmptyGroups();
        $this->uncollapseErrors();
        $errorStats = $this->debug->internal->errorStats();
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
        $str .= $this->processLogEntryWEvent('groupCollapsed', array(
            'PHP',
            $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'],
            $errorStr,
        ));
        $str .= $this->processAlerts();
        $str .= $this->processSummary();
        $str .= $this->processLog();
        $str .= $this->processLogEntryWEvent('groupEnd');
        $str .= '</script>'."\n";
        $this->data = array();
        $event['return'] .= $str;
    }

    /**
     * Return log entry as javascript console.xxxx
     *
     * @param string $method method
     * @param array  $args   arguments
     * @param array  $meta   meta values
     *
     * @return string
     */
    public function processLogEntry($method, $args = array(), $meta = array())
    {
        if ($method == 'assert') {
            \array_unshift($args, false);
        } elseif (\in_array($method, array('count','time'))) {
            $method = 'log';
        } elseif ($method == 'table') {
            $args = array($this->methodTable($args[0], $meta['columns']));
        } elseif ($method == 'trace') {
            $method = 'table';
            $args = array($this->methodTable($args[0], array('function','file','line')));
        } elseif (\in_array($method, array('error','warn'))) {
            if (isset($meta['file'])) {
                $args[] = $meta['file'].': line '.$meta['line'];
            }
        }
        foreach ($args as $k => $arg) {
            $args[$k] = \json_encode($this->dump($arg));
        }
        $str = 'console.'.$method.'('.\implode(',', $args).');'."\n";
        $str = \str_replace(\json_encode($this->debug->abstracter->UNDEFINED), 'undefined', $str);
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
        return $this->debug->abstracter->UNDEFINED;
    }

    /**
     * Process alerts
     *
     * @return string
     */
    protected function processAlerts()
    {
        $str = '';
        foreach ($this->data['alerts'] as $entry) {
            $args = array('%c'.$entry[0], '');
            $method = $entry[1]['class'];
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
            $str .= $this->processLogEntryWEvent($method, $args);
        }
        return $str;
    }
}
