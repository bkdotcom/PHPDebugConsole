<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Framework;

use bdk\Debug;
use Slim\Log;

/**
 * A Slim v2 log writer
 *
 * `$app->log->setWriter(new \bdk\Debug\Framework\Slim2($debug));`
 */
class Slim2
{
    /** @var Debug */
    private $debug;

    /** @var object */
    private $prevWriter;

    /**
     * Constructor
     *
     * @param Debug|null $debug      (optional) Specify PHPDebugConsole instance
     *                                 if not passed, will create Slim channel on singleton instance
     *                                 if root channel is specified, will create a Slim channel
     * @param object     $prevWriter (optional) previous slim logWriter if desired to continue writing to existing writer
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($debug = null, $prevWriter = null)
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');
        \bdk\Debug\Utility::assertType($prevWriter, 'object'); // object not avail as type-hint until php 7.2

        if (!$debug) {
            $debug = Debug::getChannel('Slim');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Slim');
        }
        /*
            Determine filepath for slim's logger so we skip over it when determining where warn/errors originate
        */
        $debug->backtrace->addInternalClass(\get_class(\Slim\Slim::getInstance()->log));
        $this->debug = $debug;
        $this->prevWriter = $prevWriter;
    }

    /**
     * "Write" a slim log message
     *
     * @param mixed $message message
     * @param int   $level   slim error level
     *
     * @return void
     */
    public function write($message, $level)
    {
        if ($this->prevWriter) {
            $this->prevWriter->write($message, $level);
        }
        $method = $this->levelToMethod($level);
        $this->debug->{$method}($message);
    }

    /**
     * Slim Log level to debug message
     *
     * @param int $level Slim Log level constant
     *
     * @return string method name
     */
    protected function levelToMethod($level)
    {
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $map = array(
            Log::EMERGENCY => 'error',
            Log::ALERT => 'alert',
            Log::CRITICAL => 'error',
            Log::ERROR => 'error',
            Log::WARN => 'warn',
            Log::NOTICE => 'warn',
            Log::INFO => 'info',
            Log::DEBUG => 'log',
        );
        return $map[$level];
    }
}
