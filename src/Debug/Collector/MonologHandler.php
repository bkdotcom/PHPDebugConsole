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

namespace bdk\Debug\Collector;

use bdk\Debug;
use InvalidArgumentException;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Essentially Monolog's PsrHandler... but passes channel along via context array
 */
class MonologHandler extends PsrHandler
{
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
     * {@inheritDoc}
     */
    public function handle(array $record)
    {
        $this->logger->log(
            \strtolower($record['level_name']),
            $record['message'],
            $record['context'] + array('channel' => $record['channel'])
        );
        return $this->bubble === false;
    }
}
