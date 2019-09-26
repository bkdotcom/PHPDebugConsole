<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.1.1
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug\Output;

use bdk\PubSub\Event;
use bdk\Debug;

/**
 * Output log as via ChromeLogger headers
 *
 * ChromeLogger supports the following methods/log-types:
 * log, warn, error, info, group, groupEnd, groupCollapsed, and table
 */
class ChromeLogger extends Base
{

    const HEADER_NAME = 'X-ChromeLogger-Data';

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
        $this->channelName = $this->debug->getCfg('channelName');
        $this->data = $this->debug->getData();
        $this->processAlerts();
        $this->processSummary();
        $this->processLog();
        if ($this->json['rows']) {
            \array_unshift($this->json['rows'], array(
                array('PHP', isset($_SERVER['REQUEST_METHOD'])
                    ? $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']
                    : '$: '. \implode(' ', $_SERVER['argv'])
                ),
                null,
                'groupCollapsed',
            ));
            \array_push($this->json['rows'], array(
                array(),
                null,
                'groupEnd',
            ));
            $encoded = $this->encode($this->json);
            if (\strlen($encoded) > 250000) {
                $this->debug->warn('chromeLogger: output limit exceeded');
            } else {
                $event['headers'][] = array(self::HEADER_NAME, $encoded);
            }
        }
        $this->data = array();
        $this->json['rows'] = array();
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
    public function processLogEntry($method, $args = array(), $meta = array())
    {
        if ($method === 'alert') {
            list($method, $args) = $this->methodAlert($args, $meta);
        } elseif ($method == 'assert') {
            \array_unshift($args, false);
        } elseif (\in_array($method, array('count','time'))) {
            $method = 'log';
        } elseif (\in_array($method, array('profileEnd','table'))) {
            $method = 'log';
            if (\is_array($args[0])) {
                $method = 'table';
                $args = array($this->methodTable($args[0], $meta['columns']));
            } elseif ($meta['caption']) {
                \array_unshift($args, $meta['caption']);
            }
        } elseif ($method === 'trace') {
            $method = 'table';
            $args = array($this->methodTable($args[0], array('function','file','line')));
        }
        if (!\in_array($method, $this->consoleMethods)) {
            $method = 'log';
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
        $method = $meta['class'];
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
