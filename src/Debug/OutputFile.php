<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.1
 */

namespace bdk\Debug;

use bdk\PubSub\Event;

/**
 * Output log to file
 */
class OutputFile extends OutputText
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
     * @param Event $event debug.log event object
     *
     * @return void
     */
    public function onLog(Event $event)
    {
        if (!$this->fileHandle) {
            return;
        }
        $method = $event['method'];
        if ($method == 'groupUncollapse') {
            return;
        }
        $meta = $event['meta'];
        $args = $event['args'];
        $isSummaryBookend = $method == 'groupSummary' || !empty($meta['closesSummary']);
        if ($isSummaryBookend) {
            fwrite($this->fileHandle, "=========\n");
            return;
        }
        if ($args) {
            $str = $this->processLogEntry($method, $args, $meta);
            fwrite($this->fileHandle, $str);
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
            return;
        }
        $this->file = $file;
        if (empty($file)) {
            if ($this->fileHandle) {
                fclose($this->fileHandle);
            }
            $this->fileHandle = null;
            return;
        }
        $fileExists = file_exists($file);
        $this->fileHandle = fopen($file, 'a');
        if ($this->fileHandle) {
            fwrite($this->fileHandle, '***** '.date('Y-m-d H:i:s').' *****'."\n");
            if (!$fileExists) {
                // we just created file
                chmod($file, 0660);
            }
        }
    }
}
