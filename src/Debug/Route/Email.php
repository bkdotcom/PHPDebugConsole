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

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\RouteInterface;
use bdk\Debug\Utility\SerializeLog;
use bdk\PubSub\Event;

/**
 * Email log
 */
class Email implements RouteInterface
{
    public $debug;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function appendsHeaders()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => 'processLogEntries',
        );
    }

    /**
     * Serializes and emails log
     *
     * @param Event $event Generic event having Debug instance as subject
     *
     * @return void
     */
    public function processLogEntries(Event $event)
    {
        $debug = $event->getSubject();
        $this->debug->email(
            $debug->getCfg('emailTo', Debug::CONFIG_DEBUG),
            $this->buildSubject(),
            $this->buildBody()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function processLogEntry(LogEntry $logEntry)
    {
    }

    /**
     * Build email body
     *
     * @return string
     */
    private function buildBody()
    {
        $request = $this->debug->request;
        $serverParams = $request->getServerParams();
        $body = (isset($serverParams['REQUEST_METHOD'])
            ? 'Request: ' . $serverParams['REQUEST_METHOD'] . ' ' . $this->debug->redact((string) $request->getUri())
            : 'Command: ' . \implode(' ', $serverParams['argv'])
        ) . "\n\n";

        /*
            List errors that occured
        */
        $errorStr = $this->buildErrorList();
        if ($errorStr) {
            $body .= 'Error(s):' . "\n"
                . $errorStr . "\n";
        }
        /*
            "attach" serialized log to body
        */
        return $body . SerializeLog::serialize($this->debug);
    }

    /**
     * Build email subject
     *
     * @return string
     */
    private function buildSubject()
    {
        $subject = 'Debug Log';
        $subjectMore = '';
        $haveError = (bool) $this->debug->errorHandler->getLastError();
        $serverParams = $this->debug->request->getServerParams();
        if (!empty($serverParams['HTTP_HOST'])) {
            $subjectMore .= ' ' . $serverParams['HTTP_HOST'];
        }
        if ($haveError) {
            $subjectMore .= ' ' . ($subjectMore ? '(Error)' : 'Error');
        }
        $subject = \rtrim($subject . ':' . $subjectMore, ':');
        return $subject;
    }

    /**
     * Build list of errors for email
     *
     * @return string
     */
    private function buildErrorList()
    {
        $errorStr = '';
        $errors = $this->debug->errorHandler->get('errors');
        \uasort($errors, function ($err1, $err2) {
            return \strcmp($err1['file'] . $err1['line'], $err2['file'] . $err2['line']);
        });
        $lastFile = '';
        foreach ($errors as $error) {
            if ($error['isSuppressed']) {
                continue;
            }
            if ($error['file'] !== $lastFile) {
                $errorStr .= $error['file'] . ':' . "\n";
                $lastFile = $error['file'];
            }
            $typeStr = $error['type'] === E_STRICT
                ? 'Strict'
                : $error['typeStr'];
            $errorStr .= \sprintf(' Line %s: (%s) %s', $error['line'], $typeStr, $error['message']) . "\n";
        }
        return $errorStr;
    }
}
