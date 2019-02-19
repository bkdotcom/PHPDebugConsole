<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug\Output;

use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log to file
 */
class File extends Text
{

    protected $file;
    protected $fileHandle;

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        $file = $this->debug->getCfg('file');
        $this->setFile($file);
        return array(
            'debug.config' => 'onConfig',
            'debug.log' => 'onLog',
        );
    }

    /**
     * debug.config event subscriber
     *
     * @param Event $event debug.config event object
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $file = $this->debug->getCfg('file');
        $this->setFile($file);
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
        $isSummaryBookend = $method == 'groupSummary' || !empty($logEntry['meta']['closesSummary']);
        if ($isSummaryBookend) {
            \fwrite($this->fileHandle, "=========\n");
            return;
        }
        if ($logEntry['args']) {
            $str = $this->processLogEntryWEvent($logEntry);
            \fwrite($this->fileHandle, $str);
        } elseif ($method == 'groupEnd' && $this->depth > 0) {
            $this->depth --;
        }
    }

    /**
     * Set file we will write to
     *
     * @param string $file file path
     *
     * @return void
     */
    protected function setFile($file)
    {
        if ($file == $this->file) {
            // no change
            return;
        }
        if ($this->fileHandle) {
            // close existing file
            \fclose($this->fileHandle);
            $this->fileHandle = null;
        }
        $this->file = $file;
        if (empty($file)) {
            return;
        }
        $fileExists = \file_exists($file);
        $this->fileHandle = \fopen($file, 'a');
        if ($this->fileHandle) {
            \fwrite($this->fileHandle, '***** '.\date('Y-m-d H:i:s').' *****'."\n");
            if (!$fileExists) {
                // we just created file
                \chmod($file, 0660);
            }
        }
    }
}
