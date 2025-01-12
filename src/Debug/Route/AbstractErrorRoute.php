<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.4
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;

/**
 * common "shouldSend" method
 */
abstract class AbstractErrorRoute extends AbstractRoute
{
    /** @var string */
    protected $statsKey = '';

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = \array_merge($this->cfg, array(
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR,
        ));
        $debug->errorHandler->setCfg('enableStats', true);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            ErrorHandler::EVENT_ERROR => ['onError', -1],
        );
    }

    /**
     * ErrorHandler::EVENT_ERROR event subscriber
     *
     * @param Error $error error/event object
     *
     * @return void
     */
    public function onError(Error $error)
    {
        if ($this->shouldSend($error, $this->statsKey) === false) {
            return;
        }
        $messages = $this->buildMessages($error);
        $this->sendMessages($messages);
    }

    /**
     * Build messages to send to client
     *
     * @param Error $error Error instance
     *
     * @return array
     */
    abstract protected function buildMessages(Error $error);

    /**
     * Send messages to client (ie Discord, Slack, or Teams)
     *
     * @param array $messages array of message(s) to send to client
     *
     * @return void
     */
    abstract protected function sendMessages(array $messages);

    /**
     * Should we send a notification for this error?
     *
     * @param Error  $error    Error instance
     * @param string $statsKey name under which we store stats
     *
     * @return bool
     */
    private function shouldSend(Error $error, $statsKey)
    {
        if ($error['throw']) {
            // subscriber that set throw *should have* stopped error propagation
            return false;
        }
        if (($error['type'] & $this->cfg['errorMask']) !== $error['type']) {
            return false;
        }
        if ($error['isFirstOccur'] === false) {
            return false;
        }
        if ($error['inConsole']) {
            return false;
        }
        $error['stats'] = \array_merge(array(
            $statsKey => array(
                'countSince' => 0,
                'timestamp'  => null,
            ),
        ), $error['stats'] ?: array());
        $tsCutoff = \time() - $this->cfg['throttleMin'] * 60;
        if ($error['stats'][$statsKey]['timestamp'] > $tsCutoff) {
            // This error was recently sent
            $error['stats'][$statsKey]['countSince']++;
            return false;
        }
        $error['stats'][$statsKey]['timestamp'] = \time();
        return true;
    }
}
