<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.0
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
     * Output the log as text
     *
     * @param Event $event event object
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
            array_unshift($this->json['rows'], array(
                array('PHP', $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']),
                null,
                'groupCollapsed',
            ));
            array_push($this->json['rows'], array(
                array(),
                null,
                'groupEnd',
            ));
            $encoded = $this->encode($this->json);
            if (headers_sent($file, $line)) {
            } elseif (strlen($encoded) > 250000) {
            } else {
                header(self::HEADER_NAME . ': ' . $encoded);
            }
        }
        $this->data = array();
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
        return base64_encode(utf8_encode(json_encode($data)));
    }

    /**
     * Process log entry
     *
     * Transmogrify log entry to chromlogger format
     *
     * @param string $method method
     * @param array  $args   arguments
     * @param array  $meta   meta values
     *
     * @return void
     */
    protected function processEntry($method, $args = array(), $meta = array())
    {
        $metaStr = isset($meta['file'])
            ? $meta['file'].': '.$meta['line']
            : null;
        if ($method == 'table') {
            $args = array($this->methodTable($args[0], $args[2]));
        } elseif ($method == 'trace') {
            $method = 'table';
            $args = array($this->methodTable($args[0], array('function','file','line')));
        }
        foreach ($args as $i => $arg) {
            $args[$i] = $this->dump($arg);
        }
        if ($method === 'log') {
            $method = '';
        }
        $this->json['rows'][] = array($args, $metaStr, $method);
    }
}
