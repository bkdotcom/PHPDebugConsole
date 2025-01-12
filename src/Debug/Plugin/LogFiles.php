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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log files that were included during request
 */
class LogFiles extends AbstractComponent implements SubscriberInterface
{
    /** @var array<string,mixed> */
    protected $cfg = array(
        'asTree' => true,
        'channelOpts' => array(
            'channelIcon' => ':files:',
            'channelSort' => -10,
            'nested' => false,
        ),
        'condense' => true,
        'filesExclude' => [
            'closure://function',
            '/vendor/',
        ],
    );

    /** @var array<string,int> */
    private $excludedCounts = array();
    /** @var Debug|null */
    private $debug;
    /** @var string[] */
    private $files = array();

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_OUTPUT => ['onOutput', PHP_INT_MAX],
        );
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
        if (empty($event['debug']['logFiles'])) {
            return;
        }
        $cfg = $event['debug']['logFiles'];
        $this->cfg = \array_replace_recursive($this->cfg, $cfg);
        if (isset($cfg['filesExclude'])) {
            // replace all
            $this->cfg['filesExclude'] = $cfg['filesExclude'];
        }
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        $debug = $event->getSubject();
        if (!$debug->getCfg('logEnvInfo.files', Debug::CONFIG_DEBUG)) {
            return;
        }

        $this->debug = $debug->getChannel('Files', $this->cfg['channelOpts']);

        $this->output();
    }

    /**
     * Output included files
     *
     * @return void
     */
    public function output()
    {
        $files = $this->files !== array()
            ? $this->files
            : $this->debug->php->getIncludedFiles();

        $countIncluded = \count($files);
        $files = $this->filter($files);
        $countLogged = \count($files);

        $this->debug->info($countIncluded . ' files required');
        if (empty($files)) {
            $this->debug->info('All files excluded from logging');
            $this->debug->log('See %clogFiles.filesExclude%c config', 'font-family: monospace;', '');
            return;
        }
        if ($countLogged !== $countIncluded) {
            $this->debug->info($countLogged . ' files logged');
        }

        return $this->cfg['asTree']
            ? $this->logFilesAsTree($files)
            : $this->logFilesAsArray($files);
    }

    /**
     * Set files to display
     *
     * @param array $files list of files
     *
     * @return void
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * increment `$this->excludedCounts`
     *
     * @param string $path filepath
     *
     * @return void
     */
    private function exclude($path)
    {
        if (!isset($this->excludedCounts[$path])) {
            $this->excludedCounts[$path] = 0;
        }
        $this->excludedCounts[$path]++;
    }

    /**
     * Filter files based on cfg['filesExclude']
     *
     * @param string[] $files list of filepaths
     *
     * @return string[]
     */
    protected function filter($files)
    {
        $this->excludedCounts = array();
        $files = \array_filter($files, function ($file) {
            foreach ($this->cfg['filesExclude'] as $searchStr) {
                $excludePath = $this->filterExcludePath($file, $searchStr);
                if ($excludePath === false) {
                    continue;
                }
                // excluded
                $this->exclude($excludePath);
                return false;
            }
            return true;
        });
        return \array_values($files);
    }

    /**
     * Test if file contains search string
     *
     * @param string $file      filepath
     * @param string $searchStr search string
     *
     * @return string portion of file preceding and including searchStr
     */
    private function filterExcludePath($file, $searchStr)
    {
        $strpos = \strpos($file, $searchStr);
        if ($strpos === false) {
            return false;
        }
        if ($searchStr === 'closure://function') {
            return $searchStr;
        }
        $dir = \dirname($file) . DIRECTORY_SEPARATOR;
        $strlen = $strpos + \strlen($searchStr);
        $path = \substr($dir, 0, $strlen);
        if (\substr($path, -1) !== DIRECTORY_SEPARATOR) {
            $strpos = \strpos($dir, DIRECTORY_SEPARATOR, $strlen) ?: 0;
            $path = \substr($dir, 0, $strpos);
        }
        $path = \rtrim($path, DIRECTORY_SEPARATOR);
        return $path;
    }

    /**
     * Log files files
     *
     * @param string[] $files array of filepaths
     *
     * @return void
     */
    private function logFilesAsArray($files)
    {
        $this->debug->log(
            $files,
            $this->debug->meta(array(
                'cfg' => array('maxDepth' => 0),
                'detectFiles' => true,
            ))
        );
        if ($this->excludedCounts) {
            $this->debug->log(
                \array_sum($this->excludedCounts) . ' excluded files',
                $this->excludedCounts
            );
        }
    }

    /**
     * Log files as a file tree
     *
     * @param string[] $files array of filepaths
     *
     * @return void
     */
    private function logFilesAsTree($files)
    {
        $fileTree = new \bdk\Debug\Utility\FileTree();
        $files = $fileTree->filesToTree($files, $this->excludedCounts, $this->cfg['condense']);
        $maxDepthBak = $this->debug->setCfg('maxDepth', 0, Debug::CONFIG_NO_PUBLISH);
        $this->debug->log(
            $this->debug->abstracter->crateWithVals(
                $files,
                array(
                    'options' => array(
                        'asFileTree' => true,
                        'expand' => true,
                    ),
                )
            ),
            $this->debug->meta(array(
                'detectFiles' => true,
            ))
        );
        $this->debug->setCfg('maxDepth', $maxDepthBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
    }
}
