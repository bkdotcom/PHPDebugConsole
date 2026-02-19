<?php

/**
 * @package   bdk/debug
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
        'group' => true,
    );

    /** @var list<string> */
    protected $consoleMethods = [
        'assert',
        // 'count', // output as log
        'error',
        'group',
        'groupCollapsed',
        'groupEnd',
        'info',
        'log',
        'table',
        // 'time',  // output as log
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
        $this->dumper = $debug->getDump('base', 'script');
        $this->dumper->setCfg('undefinedAs', Abstracter::UNDEFINED);
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
        \bdk\Debug\Utility\PhpType::assertType($event, 'bdk\PubSub\Event|null');

        $this->dumper->crateRaw = false;
        $this->data = $this->debug->data->get();
        $str = '<script>' . "\n";
        $str .= $this->wrapWithGroup($this->processChannels());
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
        $this->dumper->processLogEntry($logEntry);
        $str = $this->buildConsoleCall($logEntry);
        return \strtr($str, array(
            '</script>' => '<\\/script>',
            \json_encode(Abstracter::UNDEFINED) => 'undefined',
            \json_encode(Type::TYPE_FLOAT_INF) => 'Infinity',
            \json_encode(Type::TYPE_FLOAT_NAN) => 'NaN',
        ));
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
        $method = \in_array($logEntry['method'], $this->consoleMethods, true)
            ? $logEntry['method']
            : 'log';
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        $return = '';
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
                if (!empty($meta['caption'])) {
                    $return = 'console.log(' . \json_encode('%c' . $meta['caption']) . ', "font-size:1.20em; font-weight:bold;")' . "\n";
                }
                $args = $this->dumper->valDumper->dump($args);
                break;
        }
        $args = \json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $return . 'console.' . $method . '(' . \substr($args, 1, -1) . ');' . "\n";
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

    /**
     * Wrap script in group
     *
     * @param string $script javascript to modify
     *
     * @return string
     */
    protected function wrapWithGroup($script)
    {
        $headingArgs = ['PHP', $this->getRequestMethodUri(), $this->getErrorSummary()];
        if (!$this->cfg['group']) {
            // not wrapping in group -> prepend an info heading
            return $this->processLogEntryViaEvent(new LogEntry(
                $this->debug,
                'info',
                $headingArgs
            )) . $script;
        }
        return $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupCollapsed',
            $headingArgs
        ))
            . $script
            . $this->processLogEntryViaEvent(new LogEntry($this->debug, 'groupEnd'));
    }
}
