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

namespace bdk\Debug\Collector;

use bdk\Debug;
use Slim\Log;

/**
 * A Slim v2 log writer
 */
class Slim2
{

    private $debug;
    private $prevWriter;

    /**
     * Constructor
     *
     * @param Debug  $debug      (optional) Specify PHPDebugConsole instance
     *                             if not passed, will create Slim channnel on singleton instance
     *                             if root channel is specified, will create a Slim channel
     * @param object $prevWriter (optional) previous slim logWriter if desired to continue writing to existing writer
     */
    public function __construct(Debug $debug = null, $prevWriter = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Slim');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Slim');
        }
        /*
            Determine filepath for slim's logger so we skipp over it when determining where warn/errors originate
        */
        $refClass = new \ReflectionClass(\Slim\Slim::getInstance()->log);
        \bdk\Debug\Utilities::addCallerBreaker('path', $refClass->getFileName());
        $this->debug = $debug;
        $this->prevWriter = $prevWriter;
    }

    /**
     * "Write" a slim log message
     *
     * @param mixed   $message message
     * @param integer $level   slim error level
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
     * @param integer $level Slim Log level constant
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
