<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug;

use bdk\PubSub\Event;
use bdk\Debug;

/**
 * Output log as via ChromeLogger headers
 *
 * ChromeLogger supports the following methods/log-types:
 * log, warn, error, info, group, groupEnd, groupCollapsed, and table
 */
class OutputChromeLogger extends OutputBase
{

    const HEADER_NAME = 'X-ChromeLogger-Data';

    /**
     * @var array header data
     */
    protected $json = array(
        'version' => Debug::VERSION,
        'columns' => array('log', 'backtrace', 'type'),
        'rows' => array()
    );

    /**
     * Output the log as chromelogger headers
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        $this->data = $this->debug->getData();
        $this->removeHideIfEmptyGroups();
        $this->uncollapseErrors();
        $this->processAlerts();
        $this->processSummary();
        $this->processLog();
        if ($this->json['rows']) {
            \array_unshift($this->json['rows'], array(
                array('PHP', $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']),
                null,
                'groupCollapsed',
            ));
            \array_push($this->json['rows'], array(
                array(),
                null,
                'groupEnd',
            ));
            $encoded = $this->encode($this->json);
            if (\headers_sent($file, $line)) {
                $this->debug->warn('chromeLogger: headers already sent: '.$file.' (line '.$line.')');
            } elseif (\strlen($encoded) > 250000) {
                $this->debug->warn('chromeLogger: output limit exceeded');
            } else {
                \header(self::HEADER_NAME . ': ' . $encoded);
            }
        }
        $this->data = array();
    }

    /**
     * process alerts
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
            \array_push($this->json['rows'], array(
                $args,
                null,
                $method,
            ));
        }
        return $str;
    }

    /**
     * Process log entry
     *
     * Transmogrify log entry to chromelogger format
     *
     * @param string $method method
     * @param array  $args   arguments
     * @param array  $meta   meta values
     *
     * @return void
     */
    protected function doProcessLogEntry($method, $args = array(), $meta = array())
    {
        if ($method === 'table') {
            $args = array($this->methodTable($args[0], $meta['columns']));
        } elseif ($method === 'trace') {
            $method = 'table';
            $args = array($this->methodTable($args[0], array('function','file','line')));
        }
        foreach ($args as $i => $arg) {
            $args[$i] = $this->dump($arg);
        }
        $this->json['rows'][] = array(
            $args,
            isset($meta['file']) ? $meta['file'].': '.$meta['line'] : null,
            $method === 'log' ? '' : $method,
        );
    }

    /**
     * Encode data for header
     *
     * @param array $data log data
     *
     * @return string encoded data for header
     */
    protected function encode($data)
    {
        return \base64_encode(\utf8_encode(\json_encode($data)));
    }
}
