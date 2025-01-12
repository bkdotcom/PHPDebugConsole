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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\LogEntry;

/**
 * Add request/response related methods to debug
 */
trait CustomMethodTrait
{
    /** @var Debug */
    private $debug;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Debug::EVENT_CUSTOM_METHOD event subscriber
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return void
     */
    public function onCustomMethod(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if (\in_array($method, $this->methods, true) === false) {
            return;
        }
        $this->debug = $logEntry->getSubject();
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($meta) {
            $args[] = $this->debug->meta($meta);
        }
        $logEntry['handled'] = true;
        $logEntry['return'] = \call_user_func_array([$this, $method], $args);
        $logEntry->stopPropagation();
    }
}
