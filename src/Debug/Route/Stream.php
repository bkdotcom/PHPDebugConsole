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
use bdk\PubSub\Event;

/**
 * Output log to a stream
 */
class Stream extends AbstractRoute
{
    protected $fileHandle;
    protected $cfg = array(
        'ansi' => 'default',        // default | true | false  (STDOUT & STDERR streams will default to true)
        'channels' => array('*'),
        'channelsExclude' => array(
            'events',
            'files',
        ),
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
        if (!$this->dumper) {
            $this->dumper = $debug->getDump('text');
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
        $this->cfg['output'] = $this->debug->isCli()
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
        $isCli = $this->debug->isCli();
        if ($isCli && isset($cfg['debug']['output'])) {
            $this->cfg['output'] = $cfg['debug']['output'];
        }
        if ($this->cfg['output']) {
            $this->openStream($this->cfg['stream']);
        }
    }

    /**
     * Debug::EVENT_LOG event subscriber
     *
     * @param LogEntry $logEntry LogEntry instance
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
     * Is file path writable?
     *
     * @param string $file file path
     *
     * @return bool
     */
    private function isWritable($file)
    {
        if (\strpos($file, 'php://') === 0 || \is_writable($file)) {
            return true;
        }
        $dir = \dirname($file);
        return !\file_exists($file) && \is_writeable($dir);
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
        $this->dumper = $this->ansiCheck()
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
        $fileExists = \file_exists($file);
        if ($this->isWritable($file) === false) {
            \trigger_error($file . ' is not writable', E_USER_NOTICE);
            return;
        }
        $this->fileHandle = \fopen($file, 'a');
        if (!$this->fileHandle) {
            return;
        }
        $meta = \stream_get_meta_data($this->fileHandle);
        if ($meta['wrapper_type'] !== 'plainfile') {
            return;
        }
        \fwrite($this->fileHandle, '***** ' . \date('Y-m-d H:i:s T') . ' *****' . "\n");
        if (!$fileExists) {
            // we just created file
            \chmod($file, 0660);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        if (\array_key_exists('stream', $cfg)) {
            // changing stream?
            $this->openStream($cfg['stream']);
        }
    }
}
