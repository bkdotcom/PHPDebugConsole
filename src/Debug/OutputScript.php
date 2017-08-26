<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

use bdk\PubSub\Event;

/**
 * Output log as <script> tag
 */
class OutputScript extends OutputBase
{

    /**
     * output the log as javascript
     *    which outputs the log to the console
     *
     * @param Event $event event object
     *
     * @return string|void
     */
    public function onOutput(Event $event = null)
    {
        $this->data = $this->debug->getData();
        $this->removeHideIfEmptyGroups();
        $this->uncollapseErrors();
        $errorStats = $this->debug->output->errorStats();
        $errorStr = '';
        if ($errorStats['inConsole']) {
            $errorStr = 'Errors: ';
            foreach ($errorStats['counts'] as $category => $vals) {
                $errorStr .= $vals['inConsole'].' '.$category.', ';
            }
            $errorStr = substr($errorStr, 0, -2);
        }
        $str = '';
        $str .= '<script type="text/javascript">'."\n";
        $str .= $this->processEntry('groupCollapsed', array(
            'PHP',
            $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'],
            $errorStr,
        ));
        $str .= $this->processAlerts();
        $str .= $this->processSummary();
        $str .= $this->processLog();
        $str .= $this->processEntry('groupEnd');
        $str .= '</script>'."\n";
        $this->data = array();
        if ($event) {
            $event['output'] .= $str;
        } else {
            return $str;
        }
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
     * Return log entry as text
     *
     * @param string $method method
     * @param array  $args   arguments
     * @param array  $meta   meta values
     *
     * @return string
     */
    protected function processEntry($method, $args = array(), $meta = array())
    {
        if ($method == 'assert') {
            array_unshift($args, false);
        } elseif ($method == 'count' || $method == 'time') {
            $method = 'log';
        } elseif ($method == 'table') {
            $args = array($this->methodTable($args[0], $args[2]));
        } elseif ($method == 'trace') {
            $method = 'table';
            $args = array($this->methodTable($args[0], array('function','file','line')));
        } elseif (in_array($method, array('error','warn'))) {
            if (isset($meta['file'])) {
                $args[] = $meta['file'].': line '.$meta['line'];
            }
        }
        foreach ($args as $k => $arg) {
            $args[$k] = json_encode($this->dump($arg));
        }
        $str = 'console.'.$method.'('.implode(',', $args).');'."\n";
        $str = str_replace(json_encode($this->debug->abstracter->UNDEFINED), 'undefined', $str);
        return $str;
    }
}
