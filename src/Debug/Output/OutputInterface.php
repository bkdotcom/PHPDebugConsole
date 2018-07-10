<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.3
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
     * @param mixed $val  value to dump
     * @param array $path {@internal}
     *
     * @return array|string
     */
	public function dump($val, $path = array());
}
