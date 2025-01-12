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

namespace bdk\Debug\Utility;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use OutOfBoundsException;

/**
 * Convert a list of files a tree
 */
class FileTree
{
    /**
     * Convert list of filepaths to a tree structure
     *
     * @param string[]          $files          list of files
     * @param array<string,int> $excludedCounts path => count array
     * @param bool              $condense       whether to "condense" filepaths
     *
     * @return array
     */
    public function filesToTree($files, $excludedCounts = array(), $condense = false)
    {
        $tree = array();
        foreach ($files as $filepath) {
            $dirs = \explode('/', \trim($filepath, '/'));
            $file = \array_pop($dirs);
            if ($dirs) {
                $dirs[0] = '/' . $dirs[0];
            }
            $node = &$this->getTreeNode($tree, $dirs);
            $node[] = new Abstraction(Type::TYPE_STRING, array(
                'attribs' => array(
                    'data-file' => $filepath,
                ),
                'value' => $file,
            ));
            unset($node);
        }
        $tree = $this->addExcludedToTree($tree, $excludedCounts);
        if ($condense) {
            $tree = $this->condenseTree($tree);
        }
        return $tree;
    }

    /**
     * Insert 'xx omitted' info into file tree
     *
     * @param array             $tree           file tree
     * @param array<string,int> $excludedCounts path => count array
     *
     * @return array modified tree
     */
    private function addExcludedToTree($tree, $excludedCounts)
    {
        foreach ($excludedCounts as $path => $count) {
            $dirs = \explode(DIRECTORY_SEPARATOR, $path);
            // $dir[0] is ''
            \array_shift($dirs);
            $dirs[0] = '/' . $dirs[0];
            if ($path === 'closure://function') {
                $dirs = [$path];
            }
            $node = &$this->getTreeNode($tree, $dirs);
            \array_unshift($node, new Abstraction(Type::TYPE_STRING, array(
                'attribs' => array(
                    'class' => 'exclude-count',
                ),
                'value' => $count . ' omitted',
            )));
        }
        return $tree;
    }

    /**
     * Get tree node for given path
     *
     * @param array    $tree file tree
     * @param string[] $path path to traverse
     *
     * @return array reference to tree node
     *
     * @throws OutOfBoundsException
     */
    private function &getTreeNode(array &$tree, array $path)
    {
        $cur = &$tree;
        foreach ($path as $subdir) {
            if (!isset($cur[$subdir])) {
                // we're adding a dir..
                $cur[$subdir] = array();
                $this->sortDir($cur);
            }
            $cur = &$cur[$subdir];
            if (\is_array($cur) === false) {
                throw new OutOfBoundsException('Invalid path: ' . \implode('/', $path));
            }
        }
        return $cur;
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
        /** @var array{out:array,src:array}[]  */
        $stack = array(
            array(
                'out' => &$out,
                'src' => &$tree,
            ),
        );
        while ($stack) {
            $cur = \array_shift($stack);
            $this->condenseTreeFrame($cur, $stack);
        }
        return $out;
    }

    /**
     * Condense a tree frame
     *
     * @param array                        $cur   current stack frame
     * @param array{out:array,src:array}[] $stack remaining stack
     *
     * @return void
     */
    private function condenseTreeFrame($cur, &$stack)
    {
        foreach ($cur['src'] as $k => &$val) {
            list($keys, $val) = $this->walkBranch([$k], $val);
            if (\is_array($val) === false) {
                // leaf (file)
                $cur['out'][] = $val;
                continue;
            }
            $kOut = \implode('/', $keys);
            // initialize output array
            $cur['out'][$kOut] = array();
            $stack[] = array(
                'out' => &$cur['out'][$kOut],
                'src' => &$val,
            );
        }
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
        \uksort(
            $dir,
            /**
             * @param string|int $keyA
             * @param string|int $keyB
             */
            static function ($keyA, $keyB) {
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
                return \strnatcasecmp((string) $keyA, (string) $keyB);
            }
        );
    }

    /**
     * Walk branch to test for multiple branches
     *
     * @param string[] $keys path/directories
     * @param array    $val  tree node
     *
     * @return array{0:list<string>,1:string|Abstraction}
     */
    private function walkBranch($keys, $val)
    {
        while (\is_array($val)) {
            if (\count($val) > 1) {
                break;
            }
            $valFirst = \current($val);
            if (\is_array($valFirst) === false) {
                // test if first entry is leaf
                $val = $this->walkBranchTestLeaf($keys, $val);
                break;
            }
            $key = \key($val);
            $val = &$val[$key];
            $keys[] = $key;
        }
        return [$keys, $val];
    }

    /**
     * Update current value if abstraction
     *
     * @param string[]                 $keys path
     * @param list<Abstraction|string> $val  directory entries
     *
     * @return list<Abstraction|string>|Abstraction|string
     */
    private function walkBranchTestLeaf($keys, $val)
    {
        $valFirst = \current($val);
        $isOmittedCount = \preg_match('/^\d+ omitted/', (string) $valFirst) === 1;
        if ($isOmittedCount) {
            return $val;
        }
        $val = \implode('/', $keys) . '/' . $valFirst;
        if ($valFirst instanceof Abstraction) {
            $valFirst['value'] = $val;
            return $valFirst;
        }
        return $val;
    }
}
