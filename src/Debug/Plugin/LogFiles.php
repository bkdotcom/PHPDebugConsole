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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\PubSub\SubscriberInterface;

/**
 * Log files that were included during request
 */
class LogFiles extends AbstractComponent implements SubscriberInterface
{
    private $excludedCounts = array();
    private $debug;
    private $files;

    /**
     * Constructor
     *
     * @param array      $config configuration
     * @param Debug|null $debug  Debug instance
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($config = array(), Debug $debug = null)
    {
        $config = \array_merge(array(
            'asTree' => true,
            'condense' => true,
            'filesExclude' => array(
                'closure://function',
                '/vendor/',
            ),
        ), $config);
        $this->setCfg($config);
        $channelOpts = array(
            'channelIcon' => 'fa fa-files-o',
            'channelSort' => -10,
            'nested' => false,
        );
        if (!$debug) {
            $debug = Debug::_getChannel('Files', $channelOpts);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Files', $channelOpts);
        }
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => array('onOutput', PHP_INT_MAX),
        );
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @return void
     */
    public function onOutput()
    {
        if (!$this->debug->getCfg('logEnvInfo.files', Debug::CONFIG_DEBUG)) {
            return;
        }
        $this->output();
    }

    /**
     * Output included files
     *
     * @return void
     */
    public function output()
    {
        $files = $this->files !== null
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

        if ($this->cfg['asTree']) {
            $this->logFilesAsTree($files);
            return;
        }
        $this->logFiles($files);
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
        $this->excludedCounts[$path] ++;
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
     * @param string $searchStr searchstring
     *
     * @return string portion of file preceeding and including searchStr
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
    private function logFiles($files)
    {
        $this->debug->log(
            $files,
            $this->debug->meta(array(
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
    }
}
