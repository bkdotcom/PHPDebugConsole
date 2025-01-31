<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log as <script> tag
 */
class Script extends AbstractRoute
{
    /** @var array<string,mixed> */
    protected $cfg = array(
        'channels' => ['*'],
        'channelsExclude' => [
            'events',
            'files',
        ],
    );

    /** @var list<string> */
    protected $consoleMethods = [
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
    ];

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
     * @param Event|null $event Debug::EVENT_OUTPUT event object
     *
     * @return string|void
     */
    public function processLogEntries($event = null)
    {
        $this->debug->utility->assertType($event, 'bdk\PubSub\Event');

        $this->dumper->crateRaw = false;
        $this->data = $this->debug->data->get();
        $str = '<script>' . "\n";
        $str .= $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupCollapsed',
            [
                'PHP',
                $this->getRequestMethodUri(),
                $this->getErrorSummary(),
            ]
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
        if (\in_array($method, ['table', 'trace'], true)) {
            $logEntry->setMeta(array(
                'forceArray' => false,
                'undefinedAs' => Abstracter::UNDEFINED,
            ));
        }
        $this->dumper->processLogEntry($logEntry);
        $str = $this->buildConsoleCall($logEntry);
        $str = \str_replace(
            [
                // ensure that </script> doesn't appear inside our <script>
                '</script>',
                \json_encode(Type::TYPE_FLOAT_INF),
                \json_encode(Type::TYPE_FLOAT_NAN),
                \json_encode(Abstracter::UNDEFINED),
            ],
            [
                '<\\/script>',
                'Infinity',
                'NaN',
                'undefined',
            ],
            $str
        );
        return $str;
    }

    /**
     * Build the console.xxxx() call
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    protected function buildConsoleCall(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        switch ($method) {
            case 'assert':
                \array_unshift($args, false);
                break;
            case 'error':
            case 'warn':
                if (isset($meta['file'])) {
                    $args[] = \sprintf('%s: line %s', $meta['file'], $meta['line']);
                }
                break;
            case 'table':
                $args = $this->dumper->valDumper->dump($args);
                break;
            default:
                if (\in_array($method, $this->consoleMethods, true) === false) {
                    $method = 'log';
                }
        }
        $args = \json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $args = \substr($args, 1, -1);
        return 'console.' . $method . '(' . $args . ');' . "\n";
    }

    /**
     * Get number of errors per category
     *
     * @return string
     */
    private function getErrorSummary()
    {
        $errorStats = $this->debug->errorStats();
        $errorStr = '';
        if ($errorStats['inConsole']) {
            $errorStr = 'Errors: ';
            foreach ($errorStats['counts'] as $category => $vals) {
                $errorStr .= $vals['inConsole'] . ' ' . $category . ', ';
            }
            $errorStr = \substr($errorStr, 0, -2);
        }
        return $errorStr;
    }
}
