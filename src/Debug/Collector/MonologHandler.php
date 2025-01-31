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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\MonologHandler\CompatTrait;
use InvalidArgumentException;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Essentially Monolog's PsrHandler... but passes channel along via context array
 */
class MonologHandler extends PsrHandler
{
    use CompatTrait;

    /**
     * Constructor
     *
     * @param Debug|LoggerInterface $debug  Debug instance
     * @param int                   $level  The minimum logging level at which this handler will be triggered (See Monolog/Logger constants)
     * @param bool                  $bubble Whether the messages that are handled can bubble up the stack or not
     *
     * @throws InvalidArgumentException
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($debug = null, $level = Logger::DEBUG, $bubble = true)
    {
        if (!$debug) {
            $debug = Debug::getInstance();
        }
        $logger = null;
        if ($debug instanceof Debug) {
            $logger = $debug->logger;
        } elseif ($debug instanceof LoggerInterface) {
            $logger = $debug;
        }
        if ($logger === null) {
            throw new InvalidArgumentException('$debug must be instanceof bdk\Debug or Psr\Log\LoggerInterface');
        }
        parent::__construct($logger, $level, $bubble);
    }

    /**
     * the `handle` method
     *
     * Handle method provided by MonologHandlerCompatTrait (to support different method signatures in interface)
     *
     * @param array $record The record to handle
     *
     * @return bool true means that this handler handled the record, and that bubbling is not permitted.
     *                      false means the record was either not processed or that this handler allows bubbling.
     */
    protected function doHandle(array $record)
    {
        $this->logger->log(
            \strtolower($record['level_name']),
            $record['message'],
            $record['context'] + array('channel' => $record['channel'])
        );
        return $this->bubble === false;
    }
}
