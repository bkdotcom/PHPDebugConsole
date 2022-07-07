<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log as <script> tag
 */
class Script extends AbstractRoute
{
    protected $cfg = array(
        'channels' => array('*'),
        'channelsExclude' => array(
            'events',
            'files',
        ),
    );
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
        $this->dumper = $debug->getDump('base');
    }

    /**
     * output the log as javascript
     *    which outputs the log to the console
     *
     * @param Event $event Debug::EVENT_OUTPUT event object
     *
     * @return string|void
     */
    public function processlogEntries(Event $event)
    {
        $this->dumper->crateRaw = false;
        $this->data = $this->debug->data->get();
        $errorStats = $this->debug->errorStats();
        $serverParams = $this->debug->serverRequest->getServerParams();
        $errorStr = '';
        if ($errorStats['inConsole']) {
            $errorStr = 'Errors: ';
            foreach ($errorStats['counts'] as $category => $vals) {
                $errorStr .= $vals['inConsole'] . ' ' . $category . ', ';
            }
            $errorStr = \substr($errorStr, 0, -2);
        }
        $str = '';
        $str .= '<script>' . "\n";
        $str .= $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupCollapsed',
            array(
                'PHP',
                (isset($serverParams['REQUEST_METHOD']) && isset($serverParams['REQUEST_URI'])
                    ? $serverParams['REQUEST_METHOD'] . ' ' . $serverParams['REQUEST_URI']
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
        $this->dumper->crateRaw = true;
    }

    /**
     * Return log entry as javascript console.xxxx
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if (\in_array($method, array('table', 'trace'), true)) {
            $logEntry->setMeta(array(
                'forceArray' => false,
                'undefinedAs' => Abstracter::UNDEFINED,
            ));
        }
        $this->dumper->processLogEntry($logEntry);
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($method === 'assert') {
            \array_unshift($args, false);
        } elseif (\in_array($method, array('error','warn'), true)) {
            if (isset($meta['file'])) {
                $args[] = \sprintf('%s: line %s', $meta['file'], $meta['line']);
            }
        } elseif ($method === 'table') {
            $args = $this->dumper->valDumper->dump($args);
        } elseif (\in_array($method, $this->consoleMethods, true) === false) {
            $method = 'log';
        }
        $args = \json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $args = \substr($args, 1, -1);
        $str = 'console.' . $method . '(' . $args . ');' . "\n";
        $str = \str_replace(
            array(
                // ensure that </script> doesn't appear inside our <script>
                '</script>',
                \json_encode(Abstracter::TYPE_FLOAT_INF),
                \json_encode(Abstracter::TYPE_FLOAT_NAN),
                \json_encode(Abstracter::UNDEFINED),
            ),
            array(
                '<\\/script>',
                'Infinity',
                'NaN',
                'undefined',
            ),
            $str
        );
        return $str;
    }
}
