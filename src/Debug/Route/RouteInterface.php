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

use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Route Interface
 *
 * Both processLogEntries and processLogEntry must be available for use,
 * although only one or the other will likely be used the interface
 *    processLogEntries : log is processed at once
 *    processLogEntry : log is processed one logEntry at a time
 */
interface RouteInterface extends SubscriberInterface
{
    /**
     * Does this route append headers?
     *
     * @return bool
     */
    public function appendsHeaders();

    /**
     * Process log collectively (alerts, summary, log...)
     * likely implemented as a subscriber for the Debug::EVENT_OUTPUT event
     *
     * @param Event $event Event instance
     *
     * @return mixed
     */
    public function processLogEntries(Event $event);

    /**
     * Process log entry
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return mixed|void
     */
    public function processLogEntry(LogEntry $logEntry);
}
