<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
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
        'logDir' => null,               // where to store the logs)
                                        //   (defaults to DOCUMENT_ROOT . '/logs' )
                                        //   (sys_get_temp_dir() . '/logs' when CLI ... where ServerLog doesn't make sense )
        'urlTemplate' => '/logs/{filename}',
    );

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg['logDir'] = $debug->isCli()
            ? \sys_get_temp_dir() . '/logs'
            : $debug->getServerParam('DOCUMENT_ROOT') . '/logs';
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
        $this->dump->crateRaw = false;
        $this->collectGarbage();
        $this->data = $this->debug->data->get();
        $this->buildJsonData();
        if ($this->jsonData['rows']) {
            $filename = $this->filename();
            if ($this->writeLogFile($filename)) {
                $url = $this->debug->stringUtil->interpolate(
                    $this->cfg['urlTemplate'],
                    array(
                        'filename' => $filename,
                    )
                );
                $event['headers'][] = array(self::HEADER_NAME, $url);
            }
        }
        $this->data = array();
        $this->jsonData['rows'] = array();
        $this->dump->crateRaw = true;
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
            if ($age > $this->cfg['lifetime']) {
                \unlink($filePath);
            }
        }
    }

    /**
     * Generate filename
     *
     * @return string
     */
    protected function filename()
    {
        return $this->cfg['filenamePrefix']
            . \gmdate('YmdHis')
            . '_'
            . $this->debug->data->get('requestId')
            . '.json';
    }

    /**
     * Handle INF, Nan, & "undefined"
     *
     * @param string $json Json string
     *
     * @return string
     */
    private function translateJsonValues($json)
    {
        return \str_replace(
            array(
                \json_encode(Abstracter::TYPE_FLOAT_INF),
                \json_encode(Abstracter::TYPE_FLOAT_NAN),
                \json_encode(Abstracter::UNDEFINED),
            ),
            array(
                '"INF"',
                '"NaN"',
                'null',
            ),
            $json
        );
    }

    /**
     * Write jsonData to file
     *
     * @param string $filename filename
     *
     * @return bool
     */
    protected function writeLogFile($filename)
    {
        $json = \json_encode($this->jsonData, JSON_UNESCAPED_SLASHES);
        $json = $this->translateJsonValues($json);
        $logDir = $this->cfg['logDir'];
        if (!\file_exists($logDir)) {
            \set_error_handler(function () {
                // ignore error
            });
            \mkdir($logDir, 0755, true);
            \restore_error_handler();
        }
        $localPath = $logDir . '/' . $filename;
        if (\is_writeable($logDir) && \file_put_contents($localPath, $json) !== false) {
            return true;
        }
        \trigger_error('Unable to write serverLog file: ' . $localPath);
        return false;
    }
}
