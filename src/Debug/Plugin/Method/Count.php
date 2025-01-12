<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Count methods
 */
class Count implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var array<string,int> */
    private $counts = array();

    /** @var string[] */
    protected $methods = [
        'count',
        'countReset',
    ];

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * Count is maintained even when `collect` is `false`
     * If `collect` = `false`, `count()` will be performed "silently"
     *
     * @param mixed $label Label.  If omitted, logs the number of times `count()` has been called at this particular line.
     * @param int   $flags (optional) A bitmask of
     *                        `\bdk\Debug::COUNT_NO_INC` : don't increment the counter
     *                                                     (ie, just get the current count)
     *                        `\bdk\Debug::COUNT_NO_OUT` : don't output/log
     *
     * @return int The new count (or current count when using `COUNT_NO_INC`)
     *
     * @since 2.1 `$flags` argument added
     */
    public function count($label = null, $flags = 0)
    {
        return $this->doCount(new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args()
        ));
    }

    /**
     * Resets the counter
     *
     * Counter is reset even when debugging is disabled (ie `collect` is `false`).
     *
     * @param mixed $label (optional) specify the counter to reset
     * @param int   $flags (optional) currently only one option :
     *                       \bdk\Debug::COUNT_NO_OUT` : don't output/log
     *
     * @return Debug
     *
     * @since 2.3
     */
    public function countReset($label = 'default', $flags = 0)
    {
        $this->doCountReset(new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args()
        ));
        return $this->debug;
    }

    /**
     * Handle debug's count method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return int
     */
    private function doCount(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $args = $this->args($logEntry);
        $label = $args['label'];
        $flags = $args['flags'];
        $dataLabel = (string) $label;
        if ($label === null) {
            // determine dataLabel from calling file & line
            $callerInfo = $debug->backtrace->getCallerInfo();
            $logEntry['meta'] = \array_merge(array(
                'evalLine' => $callerInfo['evalLine'],
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ), $logEntry['meta']);
            $label = 'count';
            $dataLabel = $logEntry['meta']['file'] . ': ' . $logEntry['meta']['line'];
        }
        $count = $this->incCount($dataLabel, $flags & Debug::COUNT_NO_INC);
        if (!($flags & Debug::COUNT_NO_OUT)) {
            $logEntry['args'] = [
                (string) $label,
                $count,
            ];
            $debug->log($logEntry);
        }
        return $count;
    }

    /**
     * Handle debug's countReset
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function doCountReset(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        list($label, $flags) = \array_slice(\array_replace(['default', 0], $args), 0, 2);
        // label may be omitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        if (\count($args) === 1 && \is_int($args[0])) {
            $label = 'default';
            $flags = $args[0];
        }
        $logEntry['args'] = array('Counter \'' . $label . '\' doesn\'t exist.');
        if (isset($this->counts[$label])) {
            $this->counts[$label] = 0;
            $logEntry['args'] = [
                (string) $label,
                0,
            ];
        }
        if (!($flags & Debug::COUNT_NO_OUT)) {
            $debug = $logEntry->getSubject();
            $debug->log($logEntry);
        }
    }

    /**
     * Get label and flags
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array
     */
    private function args(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        list($label, $flags) = \array_slice(\array_replace([null, 0], $args), 0, 2);
        // label may be omitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        if (\count($args) === 1 && \is_int($args[0])) {
            $label = null;
            $flags = $args[0];
        }
        return array(
            'flags' => $flags,
            'label' => $label,
        );
    }

    /**
     * Increment the counter and return new value
     *
     * @param string $dataLabel counter identifier
     * @param bool   $noInc     don't increment / only return current value
     *
     * @return int
     */
    private function incCount($dataLabel, $noInc = false)
    {
        if (!isset($this->counts[$dataLabel])) {
            $this->counts[$dataLabel] = 0;
        }
        if (!$noInc) {
            $this->counts[$dataLabel]++;
        }
        return $this->counts[$dataLabel];
    }
}
