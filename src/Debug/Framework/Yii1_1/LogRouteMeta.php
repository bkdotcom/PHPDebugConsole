<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.3
 */

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use CLogger;

/**
 * Yii v1.1 log route / log entry meta
 */
class LogRouteMeta
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
     * Update logEntry meta info
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     */
    public function messageMeta(array $logEntry)
    {
        $logEntry = $this->messageMetaTrace($logEntry);
        $logEntry = $this->messageMetaCaller($logEntry);
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        $categoryFuncs = array(
            '/^system\.caching/' => 'messageMetaSystemCaching',
            'system.CModule' => 'messageMetaSystemCmodule',
            '/^system\./' => 'messageMetaSystem',
            'application' => 'messageMetaApplication',
        );
        foreach ($categoryFuncs as $match => $method) {
            $isMatch = $match[0] === '/'
                ? \preg_match($match, $logEntry['category'])
                : $match === $logEntry['category'];
            if ($isMatch) {
                return $this->{$method}($logEntry);
            }
        }
        return $logEntry;
    }

    /**
     * Find backtrace frame that called log()
     *
     * @return array backtrace frame or empty array
     */
    private function getCallerInfo()
    {
        $callerInfo = array();
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 13);
        foreach ($backtrace as $i => $frame) {
            $method = $frame['class'] . '::' . $frame['function'];
            if (\in_array($method, array('CLogger::log', 'YiiBase::log'), true) === false) {
                continue;
            }
            $callerInfo = $frame;
            // check if log called by some other wrapper method
            if (\in_array($backtrace[$i + 1]['function'], array('log', 'error', 'warn', 'warning'), true)) {
                $callerInfo = $backtrace[$i + 1];
            }
            break;
        }
        return $callerInfo;
    }

    /**
     * Add file & line meta to error and warning
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     */
    private function messageMetaCaller(array $logEntry)
    {
        if (\in_array($logEntry['level'], array(CLogger::LEVEL_ERROR, CLogger::LEVEL_WARNING), true) === false) {
            return $logEntry;
        }
        if (\array_intersect_key($logEntry['meta'], \array_flip(array('file', 'line')))) {
            return $logEntry;
        }
        $callerInfo = $this->getCallerInfo();
        if ($callerInfo) {
            $logEntry['meta']['file'] = $callerInfo['file'];
            $logEntry['meta']['line'] = $callerInfo['line'];
        }
        return $logEntry;
    }

    /**
     * Handle category: application
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaApplication(array $logEntry)
    {
        $logEntry['category'] = null;
        $logEntry['channel'] = $this->debug->getChannel('app');
        if (!empty($logEntry['trace']) && \strpos($logEntry['trace'][0]['file'], 'starship/RestfullYii') !== false) {
            $logEntry['channel'] = $this->debug->getChannel('RestfullYii');
            $logEntry['trace'] = array();
            $logEntry['meta']['icon'] = 'fa fa-code-fork';
            unset(
                $logEntry['meta']['file'],
                $logEntry['meta']['line']
            );
        }
        return $logEntry;
    }

    /**
     * Handle category: system.xxx
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaSystem(array $logEntry)
    {
        $channelName = 'system misc';
        $icon = 'fa fa-cogs';
        $logEntry['channel'] = $this->debug->getChannel($channelName, array(
            'icon' => $icon,
        ));
        $logEntry['meta']['icon'] = $icon;
        return $logEntry;
    }

    /**
     * Handle category: system.caching
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaSystemCaching(array $logEntry)
    {
        if (\preg_match('/^(Saving|Serving) "yii:dbquery/', $logEntry['message'])) {
            // Leave as is for now. We'll convert to POO / statementInfo log entry
            return $logEntry;
        }
        $channelName = \str_replace('system.caching.', '', $logEntry['category']);
        $icon = 'fa fa-cube';
        $logEntry['category'] = $channelName;
        $logEntry['channel'] = $this->debug->getChannel($channelName, array(
            'channelIcon' => $icon,
            'channelShow' => false,
        ));
        $logEntry['message'] = \preg_replace('# (to|from) cache$#', '', $logEntry['message']);
        $logEntry['meta']['icon'] = $icon;
        $logEntry['trace'] = array();
        if ($logEntry['level'] === CLogger::LEVEL_TRACE) {
            $logEntry['level'] = CLogger::LEVEL_INFO;
        }
        return $logEntry;
    }

    /**
     * Handle category: system.CModule
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaSystemCmodule(array $logEntry)
    {
        $channelName = 'CModule';
        $icon = 'fa fa-puzzle-piece';
        $logEntry['channel'] = $this->debug->getChannel($channelName, array(
            'channelIcon' => $icon,
            'channelShow' => false,
        ));
        $logEntry['meta']['icon'] = $icon;
        return $logEntry;
    }

    /**
     * If trace is present, set file & line meta
     * If CLogger::LEVEL_ERROR, move trace to meta
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     */
    private function messageMetaTrace(array $logEntry)
    {
        if ($logEntry['trace']) {
            $logEntry['meta']['file'] = $logEntry['trace'][0]['file'];
            $logEntry['meta']['line'] = $logEntry['trace'][0]['line'];
            if ($logEntry['level'] === CLogger::LEVEL_ERROR) {
                $logEntry['meta']['trace'] = $logEntry['trace'];
                $logEntry['trace'] = array();
            }
        }
        return $logEntry;
    }
}
