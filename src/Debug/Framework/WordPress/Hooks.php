<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log cache info
 */
class Hooks extends AbstractComponent implements SubscriberInterface
{
    /** @var array<string,mixed> */
    protected $cfg = array(
        'enabled' => true,
    );

    /** @var Debug */
    protected $debug;

    /** @var array<array-key,int> */
    protected $hooks = array();

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => 'onOutput',
        );
    }

    /**
     * Called on each emitted hook
     *
     * @param string $hook Hook name
     *
     * @return void
     */
    public function onHook($hook)
    {
        if (!isset($this->hooks[$hook])) {
            $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
            $functions = \array_column($trace, 'function');
            $isFilter = \strpos(\json_encode($functions), 'apply_filter') !== false;
            $this->hooks[$hook] = array(
                'count' => 0,
                'isFilter' => $isFilter,
            );
        }
        $this->hooks[$hook]['count']++;
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Debug::EVENT_BOOTSTRAP event object
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        if (empty($this->hooks)) {
            return;
        }
        \ksort($this->hooks);
        $debug = $event->getSubject();
        $this->debug = $debug->getChannel('hooks', array(
            'channelIcon' => 'fa fa-arrow-up',
            'channelName' => \_x('Hooks', 'channel.hooks', 'debug-console-php'),
            'channelSort' => 1,
            'nested' => false,
        ));
        $this->debug->table(\_x('Hooks', 'channel.hooks', 'debug-console-php'), $this->hooks, $this->debug->meta(array(
            'columns' => ['isFilter', 'count'],
            'tableInfo' => array(
                'columns' => array(
                    'isFilter' => array(
                        'attribs' => array('class' => ['text-center']),
                        'falseAs' => '',
                        'key' => \_x('isFilter', 'hooks.isFilter.label', 'debug-console-php'),
                        'trueAs' => '<i class="fa fa-check"></i>',
                    ),
                    'count' => array(
                        'key' => \_x('count', 'hooks.count.label', 'debug-console-php'),
                    ),
                ),
            ),
            'totalCols' => ['count'],
        )));
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $isFirstConfig = empty($this->cfg['configured']);
        $enabledChanged = isset($cfg['enabled']) && $cfg['enabled'] !== $prev['enabled'];
        if ($enabledChanged === false && $isFirstConfig === false) {
            return;
        }
        $this->cfg['configured'] = true;
        if ($cfg['enabled']) {
            \add_action('all', [$this, 'onHook']);
            return;
        }
        \remove_action('all', [$this, 'onHook']);
    }
}
