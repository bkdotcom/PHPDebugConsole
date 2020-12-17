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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Component;

/**
 * Log files that were included during request
 */
class LogFiles extends Component
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
     * Output included files
     *
     * @return void
     */
    public function output()
    {
        $files = $this->files !== null
            ? $this->files
            : $this->debug->utility->getIncludedFiles();

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

        if (!$this->cfg['asTree']) {
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
            return;
        }
        $files = $this->filesToTree($files, $this->excludedCounts, $this->cfg['condense']);
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

    /**
     * Convert list of filepaths to a tree structure
     *
     * @param string[] $files          list of files
     * @param array    $excludedCounts path => count array
     * @param bool     $condense       whether to "condense" filepaths
     *
     * @return array
     */
    public function filesToTree($files, $excludedCounts = array(), $condense = false)
    {
        $tree = array();
        foreach ($files as $filepath) {
            $cur = &$tree;
            $dirs = \explode('/', \trim($filepath, '/'));
            $file = \array_pop($dirs);
            if ($dirs) {
                $dirs[0] = '/' . $dirs[0];
            }
            foreach ($dirs as $dir) {
                if (!isset($cur[$dir])) {
                    // we're adding a dir..
                    $cur[$dir] = array();
                    $this->sortDir($cur);
                }
                $cur = &$cur[$dir];
            }
            $cur[] = new Abstraction(Abstracter::TYPE_STRING, array(
                'value' => $file,
                'attribs' => array(
                    'data-file' => $filepath,
                ),
            ));
            unset($cur);
        }
        $tree = $this->addExcludedToTree($tree, $excludedCounts);
        if ($condense) {
            $tree = $this->condenseTree($tree);
        }
        return $tree;
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
     * Insert 'xx omitted' info into file tree
     *
     * @param array $tree           file tree
     * @param array $excludedCounts path => count array
     *
     * @return array modifed tree
     */
    private function addExcludedToTree($tree, $excludedCounts)
    {
        foreach ($excludedCounts as $path => $count) {
            $cur = &$tree;
            $dirs = array($path);
            if ($path !== 'closure://function') {
                $dirs = \explode(DIRECTORY_SEPARATOR, $path);
                // $dir[0] is ''
                \array_shift($dirs);
                $dirs[0] = '/' . $dirs[0];
            }
            foreach ($dirs as $i => $dir) {
                if (!isset($cur[$dir])) {
                    $dir = \implode(DIRECTORY_SEPARATOR, \array_slice($dirs, $i));
                    $cur[$dir] = array();
                    $this->sortDir($cur);
                    $cur = &$cur[$dir];
                    break;
                }
                $cur = &$cur[$dir];
            }
            \array_unshift($cur, new Abstraction(Abstracter::TYPE_STRING, array(
                'value' => $count . ' omitted',
                'attribs' => array(
                    'class' => 'exclude-count',
                ),
            )));
        }
        return $tree;
    }

    /**
     * Reduce nesting
     * If directory only has one child:
     *    don't nest the child,
     *    append child to dirname
     *
     * @param array $tree Tree array structure to process
     *
     * @return array
     */
    private function condenseTree($tree)
    {
        $out = array();
        $stack = array(
            array(
                'src' => &$tree,
                'out' => &$out,
            ),
        );
        while ($stack) {
            $cur = \array_shift($stack);
            foreach ($cur['src'] as $k => &$val) {
                $keys = array($k);
                while (\is_array($val)) {
                    if (\count($val) > 1) {
                        break;
                    }
                    $vFirst = \current($val);
                    if (\is_array($vFirst) === false) {
                        $isOmittedCount = \preg_match('/^\d+ omitted/', $vFirst) === 1;
                        if ($isOmittedCount) {
                            break;
                        }
                        $val = $vFirst;
                        $vNew = \implode('/', $keys) . '/' . $val;
                        if ($val instanceof Abstraction) {
                            $val['value'] = $vNew;
                            $vNew = $val;
                        }
                        $val = $vNew;
                        break;
                    }
                    $k = \key($val);
                    $val = &$val[$k];
                    $keys[] = $k;
                }
                if (!\is_array($val)) {
                    $cur['out'][] = $val;
                    continue;
                }
                $kOut = \implode('/', $keys);
                // initialize output array
                $cur['out'][$kOut] = array();
                $stack[] = array(
                    'src' => &$val,
                    'out' => &$cur['out'][$kOut],
                );
            }
        }
        return $out;
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
            foreach ($this->cfg['filesExclude'] as $str) {
                $strpos = \strpos($file, $str);
                if ($strpos === false) {
                    continue;
                }
                // excluded
                $dir = \dirname($file) . DIRECTORY_SEPARATOR;
                $strlen = $strpos + \strlen($str);
                $path = \substr($dir, 0, $strlen);
                if (\substr($path, -1) !== DIRECTORY_SEPARATOR) {
                    $strpos = \strpos($dir, DIRECTORY_SEPARATOR, $strlen) ?: 0;
                    $path = \substr($dir, 0, $strpos);
                }
                $path = \rtrim($path, DIRECTORY_SEPARATOR);
                if ($str === 'closure://function') {
                    $path = $str;
                }
                if (!isset($this->excludedCounts[$path])) {
                    $this->excludedCounts[$path] = 0;
                }
                $this->excludedCounts[$path] ++;
                return false;
            }
            return true;
        });
        return \array_values($files);
    }

    /**
     * sort files with directories first
     *
     * @param array $dir file & directory list
     *
     * @return void
     */
    private function sortDir(&$dir)
    {
        \uksort($dir, function ($keyA, $keyB) {
            $aIsDir = \is_string($keyA);
            $bIsDir = \is_string($keyB);
            if ($aIsDir) {
                return $bIsDir
                    ? \strnatcasecmp($keyA, $keyB)
                    : -1;
            }
            if ($bIsDir) {
                return 1;
            }
            return \strnatcasecmp($keyA, $keyB);
        });
    }
}
