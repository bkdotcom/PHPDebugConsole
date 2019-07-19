<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug\LogEntry;
use bdk\PubSub\SubscriberInterface;

/**
 * Route Interface
 */
interface RouteInterface extends SubscriberInterface
{

    /**
     * Dump value
     *
     * @param mixed $val value to dump
     *
     * @return array|string
     */
	public function dump($val);

    /**
     * Process log entry without publishing `debug.outputLogEntry` event
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return mixed
     */
    public function processLogEntry(LogEntry $logEntry);
}
