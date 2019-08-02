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

use bdk\Debug;
use bdk\Debug\Utilities;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Email log
 */
class Email implements RouteInterface
{

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
     * {@inheritDot}
     */
    public function getSubscriptions()
    {
        return array();
    }

    /**
     * Serializes and emails log
     *
     * @param event $event Generic event having Debug instance as subject
     *
     * @return void
     */
    public function processLogEntries(Event $event = null)
    {
        $subject = $this->buildSubject();
        $body = $this->buildBody();
        $this->debug->email($this->debug->getCfg('emailTo'), $subject, $body);
    }

    /**
     * {@inheritDoc}
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        return;
    }

    /**
     * serialize log for emailing
     *
     * @param array $data log data to serialize
     *
     * @return string
     */
    public static function serializeLog($data)
    {
        foreach (array('alerts','log','logSummary') as $what) {
            foreach ($data[$what] as $i => $v) {
                if ($what == 'logSummary') {
                    foreach ($v as $i2 => $v2) {
                        $v2 = $v2->export();
                        $data['logSummary'][$i][$i2] = \array_values($v2);
                    }
                } else {
                    $v = $v->export();
                    $data[$what][$i] = \array_values($v);
                }
            }
        }
        $str = \serialize($data);
        if (\function_exists('gzdeflate')) {
            $str = \gzdeflate($str);
        }
        $str = \chunk_split(\base64_encode($str), 124);
        return "START DEBUG\n"
            .$str    // chunk_split appends a "\r\n"
            .'END DEBUG';
    }

    /**
     * Build email body
     *
     * @return string
     */
    private function buildBody()
    {
        $body = (!isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['argv'])
            ? 'Command: '. \implode(' ', $_SERVER['argv'])
            : 'Request: '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']
        )."\n\n";

        /*
            List errors that occured
        */
        $errorStr = $this->buildErrorList();
        if ($errorStr) {
            $body .= 'Error(s):'."\n"
                .$errorStr."\n";
        }
        /*
            "attach" serialized log to body
        */
        $data = \array_intersect_key($this->debug->getData(), \array_flip(array(
            'alerts',
            'log',
            'logSummary',
            'requestId',
            'runtime',
        )));
        $data['rootChannel'] = $this->debug->getCfg('channelName');
        $data['channels'] = \array_map(function (Debug $channel) {
            return array(
                'channelIcon' => $channel->getCfg('channelIcon'),
                'channelShow' => $channel->getCfg('channelShow'),
            );
        }, $this->debug->getChannels(true));
        $body .= self::serializeLog($data);
        return $body;
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
        if (!empty($_SERVER['HTTP_HOST'])) {
            $subjectMore .= ' '.$_SERVER['HTTP_HOST'];
        }
        if ($haveError) {
            $subjectMore .= ' '.($subjectMore ? '(Error)' : 'Error');
        }
        $subject = \rtrim($subject.':'.$subjectMore, ':');
        return $subject;
    }

    /**
     * Use to unserialize the log serialized by emailLog
     *
     * @param string $str   serialized log data
     * @param Debug  $debug (optional) Debug instance
     *
     * @return array | false
     */
    public static function unserializeLog($str, Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::getInstance();
        }
        $strStart = 'START DEBUG';
        $strEnd = 'END DEBUG';
        if (\preg_match('/'.$strStart.'[\r\n]+(.+)[\r\n]+'.$strEnd.'/s', $str, $matches)) {
            $str = $matches[1];
        }
        $str = Utilities::isBase64Encoded($str)
            ? \base64_decode($str)
            : false;
        if ($str && \function_exists('gzinflate')) {
            $strInflated = \gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        $data = self::unserializeSafe($str, array(
            'bdk\\Debug\\Abstraction\\Abstraction',
        ));
        $data = self::unserializeLogLogEntrify($debug, $data);
        return $data;
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
            return \strcmp($err1['file'].$err1['line'], $err2['file'].$err2['line']);
        });
        $lastFile = '';
        foreach ($errors as $error) {
            if ($error['isSuppressed']) {
                continue;
            }
            if ($error['file'] !== $lastFile) {
                $errorStr .= $error['file'].':'."\n";
                $lastFile = $error['file'];
            }
            $typeStr = $error['type'] === E_STRICT
                ? 'Strict'
                : $error['typeStr'];
            $errorStr .= '  Line '.$error['line'].': ('.$typeStr.') '.$error['message']."\n";
        }
        return $errorStr;
    }

    /**
     * for unserialized log, Convert logEntry arrays to log entry objects
     *
     * @param Debug $debug Debug instance
     * @param array $data  unserialized log data
     *
     * @return log data
     */
    private static function unserializeLogLogEntrify(Debug $debug, $data)
    {
        foreach (array('alerts','log','logSummary') as $what) {
            foreach ($data[$what] as $i => $v) {
                if ($what == 'logSummary') {
                    foreach ($v as $i2 => $v2) {
                        $data['logSummary'][$i][$i2] = new LogEntry($debug, $v2[0], $v2[1], $v2[2]);
                    }
                } else {
                    $data[$what][$i] = new LogEntry($debug, $v[0], $v[1], $v[2]);
                }
            }
        }
        return $data;
    }

    /**
     * Unserialize while only allowing the specified classes to be unserialized
     *
     * @param string $str            serialized string
     * @param array  $allowedClasses allowed class names
     *
     * @return mixed
     */
    private static function unserializeSafe($str, $allowedClasses = array())
    {
        if (\version_compare(PHP_VERSION, '7.0', '>=')) {
            // 2nd param is PHP >= 7.0 (get a warning: unserialize() expects exactly 1 parameter, 2 given)
            return \unserialize($str, array(
                'allowed_classes' => $allowedClasses,
            ));
        }
        // There's a possibility this pattern may be found inside a string (false positive)
        $regex = '#[CO]:(\d+):"([\w\\\\]+)":\d+:#';
        \preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $set) {
            if (\strlen($set[2]) !== $set[1]) {
                continue;
            } elseif (!\in_array($set[2], $allowedClasses)) {
                return false;
            }
        }
        return \unserialize($str);
    }
}
