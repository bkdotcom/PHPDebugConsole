<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\PubSub\Event;

/**
 * Output log as via ChromeLogger headers
 */
class ServerLog extends ChromeLogger
{
    const HEADER_NAME = 'X-ServerLog-Location';

    protected $cfg = array(
        'channels' => array('*'),
        'channelsExclude' => array(),
        'filenamePrefix' => 'serverLog_',
        'gcProb' => 0.10,               // (0-1) probability of running garbage collection
        'group' => true,                // contain/wrap log in a group?
        'lifetime' => 60,               // how long before can garbage collected (in seconds)
        'logDir' => null,               // where to store the log
                                        //   (defaults to DOCUMENT_ROOT . '/log' )
                                        //   (sys_get_temp_dir() . '/log' when CLI ... where ServerLog doesn't make sense )
        'urlTemplate' => '/log/{filename}',
    );

    protected $filename = null;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg['logDir'] = $debug->isCli()
            ? \sys_get_temp_dir() . '/log'
            : $debug->getServerParam('DOCUMENT_ROOT') . '/log';
    }

    /**
     * Output the log as chromelogger headers
     *
     * @param Event $event Debug::EVENT_OUTPUT Event object
     *
     * @return void
     */
    public function processLogEntries(Event $event)
    {
        $this->dumper->crateRaw = false;
        $this->collectGarbage();
        $this->data = $this->debug->data->get();
        $this->buildJsonData();
        if ($this->jsonData['rows']) {
            if ($this->writeLogFile()) {
                $url = $this->debug->stringUtil->interpolate(
                    $this->cfg['urlTemplate'],
                    array(
                        'filename' => $this->getFilename(),
                    )
                );
                $event['headers'][] = array(self::HEADER_NAME, $url);
            }
        }
        $this->data = array();
        $this->jsonData['rows'] = array();
        $this->dumper->crateRaw = true;
    }

    /**
     * Garbage-collects log-files
     *
     * @return void
     */
    protected function collectGarbage()
    {
        if (\rand(1, 100) / 100 > $this->cfg['gcProb']) {
            return;
        }
        $files = \glob($this->cfg['logDir'] . '/' . $this->cfg['filenamePrefix'] . '*.json');
        $now = \time();
        foreach ($files as $filePath) {
            $age = $now - \filemtime($filePath);
            if ($age >= $this->cfg['lifetime']) {
                \unlink($filePath);
            }
        }
    }

    /**
     * Generate filename
     *
     * @return string
     */
    protected function getFilename()
    {
        if (!$this->filename) {
            $this->filename = $this->cfg['filenamePrefix']
                . \gmdate('YmdHis')
                . '_'
                . $this->debug->data->get('requestId')
                . '.json';
        }
        return $this->filename;
    }

    /**
     * Return the local filepath where this request's log will be written
     *
     * @return string
     */
    protected function getFilepath()
    {
        return $this->cfg['logDir'] . '/' . $this->getFilename();
    }

    /**
     * Write jsonData to file
     *
     * @return bool
     */
    protected function writeLogFile()
    {
        $json = \json_encode($this->jsonData, JSON_UNESCAPED_SLASHES);
        $json = $this->translateJsonValues($json);
        $logDir = $this->cfg['logDir'];
        if (\file_exists($logDir) === false) {
            \set_error_handler(static function () {
                // ignore error
            });
            \mkdir($logDir, 0755, true);
            \restore_error_handler();
        }
        $localPath = $this->getFilepath();
        if (\is_writeable($logDir) && \file_put_contents($localPath, $json) !== false) {
            return true;
        }
        \trigger_error('Unable to write serverLog file: ' . $localPath);
        return false;
    }
}
