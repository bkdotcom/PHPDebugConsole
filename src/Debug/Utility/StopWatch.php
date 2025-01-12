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

namespace bdk\Debug\Utility;

/**
 * Maintain timers
 */
class StopWatch
{
    /**
     * @var array{
     *   labels: array<string, array{0:float, 1:float|null}>,
     *   stack: list<float>,
     * }
     */
    protected $timers = array(
        'labels' => array(
            // label => array(accumulatedTime, lastStartedTime|null)
        ),
        'stack' => [],
    );

    /**
     * Constructor
     *
     * @param array{requestTime?:float} $vals Initial values
     */
    public function __construct($vals = array())
    {
        $requestTimeDefault = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? $_SERVER['REQUEST_TIME_FLOAT']
            : \microtime(true);
        $this->timers['labels']['requestTime'] = [
            0,
            isset($vals['requestTime'])
                ? $vals['requestTime']
                : $requestTimeDefault,
        ];
    }

    /**
     * Get elapsed time
     *
     * @param string $label     timer label
     * @param string $labelUsed set to label used
     *
     * @param-out string $labelUsed
     *
     * @return float|false
     */
    public function get($label = null, &$labelUsed = null)
    {
        $elapsedMicro = false;
        if ($label === null) {
            $label = 'time';
            $elapsedMicro = $this->timers['stack']
                ? [0, \end($this->timers['stack'])]
                : $this->timers['labels']['requestTime'];
        } elseif (isset($this->timers['labels'][$label])) {
            $elapsedMicro = $this->timers['labels'][$label];
        }
        $labelUsed = $label;
        if ($elapsedMicro === false) {
            return false;
        }
        $elapsed = $elapsedMicro[0];
        if ($elapsedMicro[1]) {
            $elapsed += \microtime(true) - $elapsedMicro[1];
        }
        return $elapsed;
    }

    /**
     * Reset timers
     *
     * @return void
     */
    public function reset()
    {
        $this->timers = array(
            'labels' => \array_intersect_key($this->timers['labels'], \array_flip(['requestTime'])),
            'stack' => [],
        );
    }

    /**
     * Start a timer
     *
     * @param string $label label
     *
     * @return void
     */
    public function start($label = null)
    {
        if ($label === null) {
            // new stack timer
            $this->timers['stack'][] = \microtime(true);
            return;
        }
        if (isset($this->timers['labels'][$label]) === false) {
            // new label timer
            $this->timers['labels'][$label] = [0, \microtime(true)];
        } elseif ($this->timers['labels'][$label][1] === null) {
            // paused timer -> unpause (no microtime)
            $this->timers['labels'][$label][1] = \microtime(true);
        }
    }

    /**
     * Stop timer
     *  * If label is passed, timer is "paused" (not ended/cleared)
     *  * If label is not passed, timer is removed from timer stack
     *
     * @param string $label timer label
     *
     * @return float|false The duration (in sec)
     */
    public function stop($label = null)
    {
        $labelOut = null;
        $elapsed = $this->get($label, $labelOut);
        if ($elapsed === false) {
            return $elapsed;
        }
        if ($label === null) {
            \array_pop($this->timers['stack']);
            return $elapsed;
        }
        $this->timers['labels'][$labelOut] = [$elapsed, null];
        return $elapsed;
    }
}
