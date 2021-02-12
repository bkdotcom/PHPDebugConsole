<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework\Yii2;

use bdk\Debug;
use yii\log\Logger;
use yii\log\Target;

/**
 * PhpDebugConsole Yii 2 log target
 */
class LogTarget extends Target
{

    public $except = array(
        'yii\db\Command::query',
    );

    public $exportInterval = 1;

    private $debug;

    private $levelMap = array(
        Logger::LEVEL_ERROR => 'error',
        Logger::LEVEL_WARNING => 'warn',
        Logger::LEVEL_INFO => 'log',
        Logger::LEVEL_TRACE => 'trace',
        Logger::LEVEL_PROFILE => 'log',
        Logger::LEVEL_PROFILE_BEGIN => 'log',
        Logger::LEVEL_PROFILE_END => 'time',
    );

    private $profileStack = array();

    /**
     * Constructor
     *
     * @param Debug $debug  Debug instance
     * @param array $config Configuration
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null, $config = array())
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Yii');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Yii');
        }
        $debug->backtrace->addInternalClass('yii');
        $this->debug = $debug;
        parent::__construct($config);
    }

    /**
     * {@inheritDoc}
     */
    public function collect($messages, $final)
    {
        $this->messages = \array_merge(
            $this->messages,
            static::filterMessages($messages, $this->getLevels(), $this->categories, $this->except)
        );
        $count = \count($this->messages);
        if ($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval)) {
            $oldExportInterval = $this->exportInterval;
            $this->exportInterval = 0;
            $this->export();
            $this->exportInterval = $oldExportInterval;
            $this->messages = [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function export()
    {
        foreach ($this->messages as $message) {
            $this->handleMessage($message);
        }
    }

    /**
     * Send log meessage to PhpDebugConsole
     *
     * @param array $message Yii log message
     *
     * @return void
     */
    private function handleMessage($message)
    {
        $message = $this->normalizeMessage($message);
        $message = $this->messageMeta($message);
        $args = array();
        $debug = $message['channel'];
        $method = $this->levelMap[$message['level']];
        if ($message['level'] === Logger::LEVEL_PROFILE_BEGIN) {
            // add to stack
            $this->profileStack[] = $message;
            return;
        }
        if ($message['level'] === Logger::LEVEL_PROFILE_END) {
            $messageBegin = \array_pop($this->profileStack);
            $text = $messageBegin['category']
                ? $messageBegin['category'] . ': ' . $messageBegin['text']
                : $messageBegin['text'];
            $duration = $message['timestamp'] - $messageBegin['timestamp'];
            $args = array($text, $duration);
        }
        if ($message['level'] === Logger::LEVEL_TRACE) {
            $caption = $message['category']
                ? $message['category'] . ': ' . $message['text']
                : $message['text'];
            $message['meta']['trace'] = $message['trace'];
            $args = array(false, $caption);
        }
        if (empty($args)) {
            if ($message['category']) {
                $args[] = $message['category'] . ':';
            }
            $args[] = $message['text'];
        }
        if ($message['meta']) {
            $args[] = $debug->meta($message['meta']);
        }
        \call_user_func_array(array($debug, $method), $args);
    }

    /**
     * Add channel & meta info
     *
     * @param array $message key/value'd Yii log message
     *
     * @return array key/value'd Yii log message
     */
    private function messageMeta($message)
    {
        $message['channel'] = $this->debug;
        $message['meta'] = array();
        if (\preg_match('/^yii\\\\(\w+)\\\(.+)::/', $message['category'], $matches)) {
            // Yii category
            $namespace = $matches[1];
            $category = $matches[2];
            $message['category'] = $category;
            if ($category === 'Application') {
                $message['category'] = null;
                $message['channel'] = $this->debug->getChannel('App');
                return $message;
            }
            if ($namespace === 'caching') {
                $icon = 'fa fa-cube';
                $message['category'] = null;
                $message['channel'] = $this->debug->getChannel('Cache', array(
                    'channelIcon' => $icon,
                    // 'channelShow' => false,
                ));
                $message['meta']['icon'] = $icon;
                return $message;
            }
            if ($category === 'Connection') {
                $icon = 'fa fa-database';
                $message['category'] = null;
                $message['channel'] = $this->debug->getChannel('PDO', array(
                    'channelIcon' => $icon,
                    'channelShow' => false,
                ));
                return $message;
            }
            if ($category === 'Module') {
                $icon = 'fa fa-puzzle-piece';
                $message['channel'] = $this->debug->getChannel($category, array(
                    'channelIcon' => $icon,
                    // 'channelShow' => false,
                ));
                $message['meta']['icon'] = $icon;
                return $message;
            }
            if ($category === 'View') {
                $icon = 'fa fa-file-text-o';
                $message['category'] = null;
                $message['channel'] = $this->debug->getChannel($category, array(
                    'channelIcon' => $icon,
                    // 'channelShow' => false,
                ));
                $message['meta']['icon'] = $icon;
                return $message;
            }
            $message['channel'] = $this->debug->getChannel('misc');
        }
        return $message;
    }

    /**
     * Return key=>value'd message
     *
     * @param array $message Yii log message
     *
     * @return array key/value'd Yii log message
     */
    private function normalizeMessage($message)
    {
        $message = \array_replace(array(
            null,
            null,
            null,
            null,
            array(),
            null,
        ), $message);
        $message = \array_combine(array(
            'text',
            'level',
            'category',
            'timestamp',
            'trace',
            'memory'
        ), $message);
        if ($message['level'] === Logger::LEVEL_TRACE && empty($message['trace'])) {
            $message['level'] = Logger::LEVEL_INFO;
        }
        return $message;
    }
}
