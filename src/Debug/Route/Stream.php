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
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\Debug\Utilities;
use bdk\PubSub\Event;

/**
 * Output log to file
 */
class Stream extends Text
{

    protected $fileHandle;
    protected $streamCfg = array(
        'ansi' => 'default',    // default | true | false  (STDOUT & STDERR streams will default to true)
        'stream' => 'php://stderr',   // filepath/uri/resource
    );

    /**
     * Constructor
     *
     * @param Debug $debug Debug Instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = Utilities::arrayMergeDeep($this->cfg, $this->streamCfg);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.log' => 'onLog',
            'debug.pluginInit' => 'init',
        );
    }

    /**
     * debug.pluginInit subscriber
     *
     * @return void
     */
    public function init()
    {
        $stream = $this->cfg['stream'];
        if ($stream) {
            $this->openStream($stream);
        }
    }

    /**
     * debug.log event subscriber
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
        $method = $logEntry['method'];
        if ($method == 'groupUncollapse') {
            return;
        }
        $str = $this->processLogEntryViaEvent($logEntry);
        \fwrite($this->fileHandle, $str);
    }

    /**
     * Determine whether or not to use ANSI escape sequences (color output)
     *
     * @return boolean
     */
    private function ansiCheck()
    {
        $meta = \stream_get_meta_data($this->fileHandle);
        return $this->cfg['ansi'] === true || $this->cfg['ansi'] === 'default' && $meta['wrapper_type'] === 'PHP';
    }

    /**
     * Set file/stream we will write to
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
        }
        if ($this->fileHandle) {
            // close existing file
            \fclose($this->fileHandle);
            $this->fileHandle = null;
        }
        if (!$stream) {
            return;
        }
        $uriExists = \file_exists($stream);
        $this->fileHandle = \fopen($stream, 'a');
        $this->dump = $this->ansiCheck()
            ? $this->debug->dumpTextAnsi
            : $this->debug->dumpText;
        $meta = \stream_get_meta_data($this->fileHandle);
        if ($this->fileHandle && $meta['wrapper_type'] === 'plainfile') {
            \fwrite($this->fileHandle, '***** '.\date('Y-m-d H:i:s').' *****'."\n");
            if (!$uriExists) {
                // we just created file
                \chmod($stream, 0660);
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
