<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;

/**
 * Record and output runtime values
 *  - memoryLimit
 *  - memoryPeakUsage
 *  - runtime
 */
class Runtime extends AbstractComponent implements SubscriberInterface
{
    /** @var Debug|null */
    private $debug;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => 'onOutput',
            Debug::EVENT_PLUGIN_INIT => 'onPluginInit',
            EventManager::EVENT_PHP_SHUTDOWN => ['onShutdown', PHP_INT_MAX],
        );
    }

    /**
     * Log our runtime info in a summary group
     *
     * As we're only subscribed to root debug instance's Debug::EVENT_OUTPUT event, this info
     *   will not be output for any sub-channels output directly
     *
     * @param Event $event Debug::EVENT_OUTPUT event object
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        if ($event['isTarget'] === false) {
            return;
        }
        if (!$this->debug->getCfg('logRuntime', Debug::CONFIG_DEBUG)) {
            return;
        }
        $vals = $this->runtimeVals();
        $route = $this->debug->getCfg('route');
        /** @psalm-suppress TypeDoesNotContainType */
        $isRouteHtml = $route && \get_class($route) === 'bdk\\Debug\\Route\\Html';
        $this->debug->groupSummary(1);
        $this->debug->info('Built In ' . $this->debug->utility->formatDuration($vals['runtime']));
        $this->debug->info(
            'Peak Memory Usage'
                . ($isRouteHtml
                    ? ' <i class="fa fa-question-circle-o" title="Includes debug overhead"></i>'
                    : '')
                . ': '
                . $this->debug->utility->getBytes($vals['memoryPeakUsage']) . ' / '
                . ($vals['memoryLimit'] === '-1'
                    ? '∞'
                    : $this->debug->utility->getBytes($vals['memoryLimit'])
                ),
            $this->debug->meta('sanitize', false)
        );
        $this->debug->groupEnd();
    }

    /**
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @param Event $event Debug::EVENT_PLUGIN_INIT Event instance
     *
     * @return void
     */
    public function onPluginInit(Event $event)
    {
        $this->debug = $event->getSubject();
    }

    /**
     * Debug::EVENT_OUTPUT SUBSCRIBER
     *
     * @return void
     */
    public function onShutdown()
    {
        $this->runtimeVals();
    }

    /**
     * Get/store values such as runtime & peak memory usage
     *
     * @return array<string,float|int>
     */
    private function runtimeVals()
    {
        $vals = $this->debug->data->get('runtime');
        if (!$vals) {
            $vals = array(
                'memoryLimit' => $this->debug->php->memoryLimit(),
                'memoryPeakUsage' => \memory_get_peak_usage(true),
                'runtime' => $this->debug->timeEnd('requestTime', false, true),
            );
            $this->debug->data->set('runtime', $vals);
        }
        return $vals;
    }
}
