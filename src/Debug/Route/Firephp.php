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
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log via FirePHP
 */
class Firephp extends AbstractRoute
{
    const FIREPHP_PROTO_VER = '0.3';
    const MESSAGE_LIMIT = 99999;

    protected $appendsHeaders = true;

    protected $cfg = array(
        'channels' => array('*'),
        'channelsExclude' => array(
            'events',
            'files',
        ),
    );
    protected $firephpMethods = array(
        'log' => 'LOG',
        'info' => 'INFO',
        'warn' => 'WARN',
        'error' => 'ERROR',
        'table' => 'TABLE',
        'group' => 'GROUP_START',
        'groupCollapsed' => 'GROUP_START',
        'groupEnd' => 'GROUP_END',
    );
    protected $messageIndex = 0;
    protected $outputEvent;

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
     * Output the log via FirePHP headers
     *
     * @param Event $event Debug::EVENT_OUTPUT Event object
     *
     * @return void
     */
    public function processLogEntries(Event $event)
    {
        $this->dumper->crateRaw = false;
        $this->outputEvent = $event;
        $this->data = $this->debug->data->get();
        $event['headers'][] = array('X-Wf-Protocol-1', 'http://meta.wildfirehq.org/Protocol/JsonStream/0.2');
        $event['headers'][] = array('X-Wf-1-Plugin-1', 'http://meta.firephp.org/Wildfire/Plugin/FirePHP/Library-FirePHPCore/' . self::FIREPHP_PROTO_VER);
        $event['headers'][] = array('X-Wf-1-Structure-1', 'http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1');
        $request = $this->debug->serverRequest;
        $serverParams = $request->getServerParams();
        $heading = isset($serverParams['REQUEST_METHOD'])
            ? $serverParams['REQUEST_METHOD'] . ' ' . $this->debug->redact((string) $request->getUri())
            : '$: ' . \implode(' ', $serverParams['argv']);
        $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupCollapsed',
            array('PHP: ' . $heading)
        ));
        $this->processAlerts();
        $this->processSummary();
        $this->processLog();
        $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupEnd'
        ));
        $event['headers'][] = array('X-Wf-1-Index', $this->messageIndex);
        $this->data = array();
        $this->dumper->crateRaw = true;
    }

    /**
     * LogEntry to firePhp header(s)
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $this->setFirephpMeta($logEntry);
        $value = null;
        if ($method === 'alert') {
            $value = $this->methodAlert($logEntry);
        } elseif (\in_array($method, array('group','groupCollapsed'), true)) {
            $logEntry['firephpMeta']['Label'] = $args[0];
            $logEntry['firephpMeta']['Collapsed'] = $method === 'groupCollapsed'
                // yes, strings
                ? 'true'
                : 'false';
        } elseif (\in_array($method, array('profileEnd','table','trace'), true)) {
            $value = $this->methodTabular($logEntry);
        } elseif (\count($args)) {
            $this->dumper->processLogEntry($logEntry);
            $value = $this->getValue($logEntry);
        }
        if ($this->messageIndex < self::MESSAGE_LIMIT) {
            $this->setFirephpHeader($logEntry['firephpMeta'], $value);
        } elseif ($this->messageIndex === self::MESSAGE_LIMIT) {
            $this->setFirephpHeader(
                array('Type' => $this->firephpMethods['warn']),
                'FirePhp\'s limit of ' . \number_format(self::MESSAGE_LIMIT) . ' messages reached!'
            );
        }
    }

    /**
     * Get firephp value
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return mixed firephp value
     */
    private function getValue(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        if (\count($args) === 1) {
            return $args[0];
            // no label;
        }
        $logEntry['firephpMeta']['Label'] = \array_shift($args);
        return \count($args) > 1
            ? $args // firephp only supports label/value...  we'll pass multiple values as an array
            : $args[0];
    }

    /**
     * handle alert
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    private function methodAlert(LogEntry $logEntry)
    {
        $level = $logEntry->getMeta('level');
        $levelToMethod = array(
            'error' => 'error',
            'info' => 'info',
            'success' => 'info',
            'warn' => 'warn',
        );
        $method = isset($levelToMethod[$level])
            ? $levelToMethod[$level]
            : 'error';
        $logEntry['firephpMeta']['Type'] = $this->firephpMethods[$method];
        if ($logEntry->containsSubstitutions()) {
            $this->dumper->processLogEntry($logEntry);
        }
        return $this->getValue($logEntry);
    }

    /**
     * handle tabular type log entry
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return mixed
     */
    private function methodTabular(LogEntry $logEntry)
    {
        $logEntry->setMeta('undefinedAs', 'null');
        if ($logEntry['method'] === 'trace') {
            $logEntry['firephpMeta']['Label'] = 'trace';
        }
        $this->dumper->processLogEntry($logEntry);
        $method = $logEntry['method'];
        if ($method === 'table') {
            $logEntry['firephpMeta']['Type'] = $this->firephpMethods['table'];
            $caption = $logEntry->getMeta('caption');
            if ($caption) {
                $logEntry['firephpMeta']['Label'] = $caption;
            }
            $firephpTable = true;
            $args = $logEntry['args'];
            $value = $args[0];
            if ($firephpTable) {
                $value = array();
                $value[] = \array_merge(array(''), \array_keys(\current($args[0])));
                foreach ($args[0] as $k => $row) {
                    $value[] = \array_merge(array($k), \array_values($row));
                }
            }
            return $this->dumper->valDumper->dump($value);
        }
        $logEntry['firephpMeta']['Type'] = $this->firephpMethods['log'];
        return $this->getValue($logEntry);
    }

    /**
     * set FirePHP log entry header(s)
     *
     * @param array $meta  meta information
     * @param mixed $value value
     *
     * @return void
     */
    private function setFirephpHeader($meta, $value = null)
    {
        \ksort($meta);  // for consistency / testing
        $msg = \json_encode(array(
            $meta,
            $value,
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $structureIndex = 1;    // refers to X-Wf-1-Structure-1
        $parts = \explode("\n", \rtrim(\chunk_split($msg, 5000, "\n")));
        $numParts = \count($parts);
        for ($i = 0; $i < $numParts; $i++) {
            $part = $parts[$i];
            $this->messageIndex++;
            $headerName = 'X-Wf-1-' . $structureIndex . '-1-' . $this->messageIndex;
            $headerValue = ($i === 0 ? \strlen($msg) : '')
                . '|' . $part . '|'
                . ($i < $numParts - 1 ? '\\' : '');
            $this->outputEvent['headers'][] = array($headerName, $headerValue);
        }
    }

    /**
     * Initialize firephp's meta array
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function setFirephpMeta(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $meta = $logEntry['meta'];
        $firephpMeta = array(
            'Type' => isset($this->firephpMethods[$method])
                ? $this->firephpMethods[$method]
                : $this->firephpMethods['log'],
            // Label
            // File
            // Line
            // Collapsed  (for group)
        );
        if (isset($meta['file'])) {
            $firephpMeta['File'] = $meta['file'];
            $firephpMeta['Line'] = $meta['line'];
        }
        $logEntry['firephpMeta'] = $firephpMeta;
    }
}
