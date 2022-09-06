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
    private $debug;
    private $prevWriter;

    /**
     * Constructor
     *
     * @param Debug  $debug      (optional) Specify PHPDebugConsole instance
     *                             if not passed, will create Slim channel on singleton instance
     *                             if root channel is specified, will create a Slim channel
     * @param object $prevWriter (optional) previous slim logWriter if desired to continue writing to existing writer
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null, $prevWriter = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Slim');
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
