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

namespace bdk\Debug\Framework\Yii2;

use bdk\Debug;
use yii\log\Logger;
use yii\log\Target;

/**
 * PhpDebugConsole Yii 2 log target
 */
class LogTarget extends Target
{
    public $except = [
        'yii\db\Command::query',
    ];

    public $exportInterval = 1;

    private $debug;

    private $levelMap = array(
        Logger::LEVEL_ERROR => 'error',
        Logger::LEVEL_INFO => 'log',
        Logger::LEVEL_PROFILE => 'log',
        Logger::LEVEL_PROFILE_BEGIN => 'log',
        Logger::LEVEL_PROFILE_END => 'time',
        Logger::LEVEL_TRACE => 'trace',
        Logger::LEVEL_WARNING => 'warn',
    );

    private $profileStack = array();

    /**
     * Constructor
     *
     * @param Debug|null $debug  Debug instance
     * @param array      $config Configuration
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($debug = null, $config = array())
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');

        if (!$debug) {
            $debug = Debug::getChannel('Yii');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Yii');
        }
        $debug->backtrace->addInternalClass('yii', 1);
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
        $intervalMet = $final || ($this->exportInterval > 0 && $count >= $this->exportInterval);
        if ($count > 0 && $intervalMet) {
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
     * Send log message to PhpDebugConsole
     *
     * @param array $message Yii log message
     *
     * @return void
     */
    private function handleMessage($message)
    {
        $message = $this->normalizeMessage($message);
        $message = $this->messageMeta($message);
        if ($message['level'] === Logger::LEVEL_PROFILE_BEGIN) {
            // add to stack
            $this->profileStack[] = $message;
            return;
        }
        $debug = $message['channel'];
        $method = $this->levelMap[$message['level']];
        $args = $this->handleMessageArgs($message);
        \call_user_func_array([$debug, $method], $args);
    }

    /**
     * Get debug method args from message
     *
     * @param array $message key/value'd Yii log message
     *
     * @return array method args
     */
    private function handleMessageArgs($message)
    {
        $args = \array_filter([
            \ltrim($message['category'] . ':', ':'),
            $message['text'],
        ]);
        if ($message['level'] === Logger::LEVEL_PROFILE_END) {
            $messageBegin = \array_pop($this->profileStack);
            $text = \ltrim($messageBegin['category'] . ': ' . $messageBegin['text'], ': ');
            $duration = $message['timestamp'] - $messageBegin['timestamp'];
            $args = [$text, $duration];
        } elseif ($message['level'] === Logger::LEVEL_TRACE) {
            $caption = \ltrim($message['category'] . ': ' . $message['text'], ': ');
            $message['meta']['trace'] = $message['trace'];
            $args = [false, $caption];
        }
        if ($message['meta']) {
            $args[] = $message['channel']->meta($message['meta']);
        }
        return $args;
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
        if (\preg_match('/^yii\\\\(\w+)\\\(.+)::/', $message['category'], $matches) !== 1) {
            return $message;
        }
        // Yii category
        $namespace = $matches[1];
        $category = $matches[2];
        $message['category'] = $category;
        $method = 'messageMeta' . \ucfirst($category);
        if (\method_exists($this, $method)) {
            return $this->{$method}($message);
        }
        $method = 'messageMeta' . \ucfirst($namespace);
        if (\method_exists($this, $method)) {
            return $this->{$method}($message);
        }
        $message['channel'] = $this->debug->getChannel('misc');
        return $message;
    }

    /**
     * Set meta info for Application category
     *
     * @param array $message key/value'd Yii log message
     *
     * @return array updated message
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaApplication($message)
    {
        $message['category'] = null;
        $message['channel'] = $this->debug->getChannel('App');
        return $message;
    }

    /**
     * Set meta info for Caching namespace
     *
     * @param array $message key/value'd Yii log message
     *
     * @return array updated message
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaCaching($message)
    {
        $icon = ':cache:';
        $message['category'] = null;
        $message['channel'] = $this->debug->getChannel('Cache', array(
            'channelIcon' => $icon,
        ));
        $message['meta']['icon'] = $icon;
        return $message;
    }

    /**
     * Set meta info for Connection category
     *
     * @param array $message key/value'd Yii log message
     *
     * @return array updated message
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaConnection($message)
    {
        $icon = ':database:';
        $message['category'] = null;
        $message['channel'] = $this->debug->getChannel('PDO', array(
            'channelIcon' => $icon,
            'channelShow' => false,
        ));
        return $message;
    }

    /**
     * Set meta info for Module category
     *
     * @param array $message key/value'd Yii log message
     *
     * @return array updated message
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaModule($message)
    {
        $icon = ':component:';
        $message['channel'] = $this->debug->getChannel($message['category'], array(
            'channelIcon' => $icon,
        ));
        $message['meta']['icon'] = $icon;
        return $message;
    }

    /**
     * Set meta info for View category
     *
     * @param array $message key/value'd Yii log message
     *
     * @return array updated message
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaView($message)
    {
        $icon = ':template:';
        $message['channel'] = $this->debug->getChannel($message['category'], array(
            'channelIcon' => $icon,
        ));
        $message['category'] = null;
        $message['meta']['icon'] = $icon;
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
        $message = \array_replace([
            null,
            null,
            null,
            null,
            [],
            null,
        ], $message);
        $message = \array_combine([
            'text',
            'level',
            'category',
            'timestamp',
            'trace',
            'memory',
        ], $message);
        if ($message['level'] === Logger::LEVEL_TRACE && empty($message['trace'])) {
            $message['level'] = Logger::LEVEL_INFO;
        }
        return $message;
    }
}
