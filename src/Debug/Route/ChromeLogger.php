<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

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
     * @var array header data
     */
    protected $json = array(
        'version' => Debug::VERSION,
        'columns' => array('log', 'backtrace', 'type'),
        'rows' => array()
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
     * Output the log as chromelogger headers
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    public function processLogEntries(Event $event)
    {
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
     * {@inheritDoc}
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $this->dump->processLogEntry($logEntry);
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($method == 'assert') {
            \array_unshift($args, false);
        } elseif (!\in_array($method, $this->consoleMethods)) {
            $method = 'log';
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
        $data = \json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $data = \str_replace(\json_encode(Abstracter::UNDEFINED), 'null', $data);
        return \base64_encode(\utf8_encode($data));
    }
}
