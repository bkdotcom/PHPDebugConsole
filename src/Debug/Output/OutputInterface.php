<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.1
 */

namespace bdk\Debug\Output;

use bdk\PubSub\SubscriberInterface;

/**
 * Base output plugin
 */
interface OutputInterface extends SubscriberInterface
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
     * @param string $method method
     * @param array  $args   args
     * @param array  $meta   meta values
     *
     * @return mixed
     */
    public function processLogEntry($method, $args = array(), $meta = array());
}
