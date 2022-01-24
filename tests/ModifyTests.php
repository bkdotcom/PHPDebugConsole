<?php

namespace bdk\Test;

/**
 * PHPDebugConsole supports php 5.4 - php 8.0
 * Modify our unit tests depending on what version of PHP we're testing
 * and what version of PHPUnit is being used.
 *
 * ie PHPUnit 9.x is required for PHP 8.0... but only supports PHP 7.3 +
 */
class ModifyTests
{
    private $modifiedFiles = array();
    protected $dir;

    /**
     * Modify our tests if necessary
     * Register shutdown function to revert changes
     *
     * @param string $dir test directory
     *
     * @return void
     */
    public function modify($dir)
    {
        $this->dir = $dir;
        $this->removeReturnType();
        \register_shutdown_function(array($this, 'revert'));
    }

    /**
     * PHPUnit 8.0 requires specifying return types for some methods.
     * This is a PHP 7.0 feature that we must remove to run tests for php 5.x
     * void return type was added in php 7.1
     *
     * @return void
     */
    public function removeReturnType()
    {
        if (PHP_VERSION_ID >= 70100) {
            return;
        }
        // remove void return type from php < 7.1
        $this->findFiles($this->dir, function ($filepath) {
            if (\preg_match('/\.php$/', $filepath) === 0) {
                return false;
            }
            if (\preg_match('/\b(Mock|Fixture)\b/', $filepath) === 1) {
                return false;
            }
            $content = \preg_replace_callback(
                '/(function \S+\s*\([^)]*\))\s*:\s*void/',
                function ($matches) use ($filepath) {
                    if (!isset($this->modifiedFiles[$filepath])) {
                        $this->modifiedFiles[$filepath] = array();
                    }
                    $this->modifiedFiles[$filepath][] = array(
                        'original' => $matches[0],
                        'new' => $matches[1],
                    );
                    return $matches[1];
                },
                \file_get_contents($filepath),
                -1, // no limit
                $count
            );
            if ($count > 0) {
                \file_put_contents($filepath, $content);
                return true;
            }
            return false;
        });
    }

    /**
     * Revert changes to files
     *
     * @return void
     */
    public function revert()
    {
        foreach ($this->modifiedFiles as $filepath => $changes) {
            $content = \file_get_contents($filepath);
            foreach ($changes as $change) {
                $content = \str_replace($change['new'], $change['original'], $content);
            }
            \file_put_contents($filepath, $content);
        }
    }

    /**
     * Find all files in given directory optionally filtered by filter callback
     *
     * @param string   $dir    directory
     * @param callable $filter filter callable receives full filepath
     *
     * @return string[] filepaths
     */
    private function findFiles($dir, $filter = null)
    {
        $files = \glob($dir . '/*');
        foreach (\glob($dir . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = \array_merge($files, self::findFiles($dir));
        }
        if (\is_callable($filter)) {
            $files = \array_filter($files, $filter);
        }
        return $files;
    }
}
