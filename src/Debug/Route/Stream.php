<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log to a stream
 */
class Stream extends Base
{

    protected $fileHandle;
    protected $cfg = array(
        'ansi' => 'default',        // default | true | false  (STDOUT & STDERR streams will default to true)
        'output' => false,          // kept in sync with Debug->cfg['output']
        'stream' => 'php://stderr', // filepath/uri/resource
    );

    /**
     * Constructor
     *
     * @param Debug $debug Debug Instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        if (!$this->dump) {
            $this->dump = $debug->getDump('text');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_LOG => 'onLog',
            Debug::EVENT_PLUGIN_INIT => 'init',
        );
    }

    /**
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @return void
     */
    public function init()
    {
        $isCli = \strpos($this->debug->getInterface(), 'cli') !== false;
        $this->cfg['output'] = $isCli
            ? $this->debug->getCfg('output', Debug::CONFIG_DEBUG)    // if cli, only output if explicitly true
            : true;                             //  otherwise push to stream
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event->getValues();
        $isCli = \strpos($this->debug->getInterface(), 'cli') !== false;
        if (isset($cfg['debug']['output']) && $isCli) {
            // if cli, abide by global output config
            $this->cfg['output'] = $cfg['debug']['output'];
        }
    }

    /**
     * Debug::EVENT_LOG event subscriber
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        if (!$this->fileHandle) {
            return;
        }
        if (!$this->cfg['output']) {
            return;
        }
        $method = $logEntry['method'];
        if ($method === 'groupUncollapse') {
            return;
        }
        $str = $this->processLogEntryViaEvent($logEntry);
        \fwrite($this->fileHandle, $str);
    }

    /**
     * Determine whether or not to use ANSI escape sequences (color output)
     *
     * @return bool
     */
    private function ansiCheck()
    {
        $meta = \stream_get_meta_data($this->fileHandle);
        return $this->cfg['ansi'] === true || $this->cfg['ansi'] === 'default' && $meta['wrapper_type'] === 'PHP';
    }

    /**
     * Open file/stream
     *
     * @param resource|string $stream file path, uri, or stream resource
     *
     * @return void
     */
    protected function openStream($stream)
    {
        if ($this->fileHandle) {
            $meta = \stream_get_meta_data($this->fileHandle);
            if ($meta['uri'] === $stream) {
                // no change
                return;
            }
            // close existing file
            \fclose($this->fileHandle);
            $this->fileHandle = null;
        }
        if (!$stream) {
            return;
        }
        $this->setFilehandle($stream);
        if (!$this->fileHandle) {
            return;
        }
        $this->dump = $this->ansiCheck()
            ? $this->debug->getDump('textAnsi')
            : $this->debug->getDump('text');
    }

    /**
     * Open stream
     *
     * @param resource|string $stream file path, uri, or stream resource
     *
     * @return void
     */
    private function setFilehandle($stream)
    {
        if (\is_resource($stream)) {
            $this->fileHandle = $stream;
            return;
        }
        $file = $stream;
        $dir = \dirname($file);
        $fileExists = \file_exists($file);
        $isWritable = \strpos($file, 'php://') === 0 || \is_writable($file) || !\file_exists($file) && \is_writeable($dir);
        if (!$isWritable) {
            \trigger_error($file . ' is not writable', E_USER_NOTICE);
            return;
        }
        $this->fileHandle = \fopen($file, 'a');
        if (!$this->fileHandle) {
            return;
        }
        $meta = \stream_get_meta_data($this->fileHandle);
        if ($meta['wrapper_type'] === 'plainfile') {
            \fwrite($this->fileHandle, '***** ' . \date('Y-m-d H:i:s') . ' *****' . "\n");
            if (!$fileExists) {
                // we just created file
                \chmod($file, 0660);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array())
    {
        if (\array_key_exists('stream', $cfg)) {
            // changing stream?
            $this->openStream($cfg['stream']);
        }
    }
}
