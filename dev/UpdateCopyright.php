<?php

namespace bdk\Debug\Dev;

use DateTimeImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * update @copyright tags
 */
class UpdateCopyright
{
    /**
     * Update copyright year in all PHP files
     *
     * @return void
     */
    public static function update()
    {
        $directory = __DIR__ . '/../src';
        $directoryIterator = new RecursiveDirectoryIterator($directory);
        $iteratorIterator = new RecursiveIteratorIterator($directoryIterator);
        $files = new RegexIterator($iteratorIterator, '/^.*\.php$/');
        $datetime = new DateTimeImmutable();
        $doy = $datetime->format('z') + 1;
        $year = $doy >= 365 - 7
            ? $datetime->format('Y') + 1
            : $datetime->format('Y');
        $updateCount = 0;
        foreach ($files as $file) {
            $filepath = $file->getPathname();
            $relPath = \str_replace($directory . '/', '', $filepath);
            $updated = self::updateFile($filepath, $year, $relPath);
            if ($updated) {
                $updateCount++;
            }
        }
        \bdk\Debug::varDump('updateCount', $updateCount);
    }

    /**
     * Update file's copyright year
     *
     * @param string $filepath filepath
     * @param string $year     copyright year
     * @param string $relpath  relative path
     *
     * @return bool whether updated
     */
    protected static function updateFile($filepath, $year, $relPath)
    {
        $contents = \file_get_contents($filepath);
        $replacementCount = 0;
        $contents = \preg_replace_callback('/^(\s*\*\s*@copyright\s+)(.*)$/m', static function ($matches) use (&$replacementCount, $year) {
            $comment = \preg_replace('/\b(\d{2,4}\-)?(\d{2,4})\b/', '${1}' . $year, $matches[2]);
            $replacement = $matches[1] . $comment;
            if ($replacement !== $matches[0]) {
                $replacementCount++;
            }
            return $replacement;
        }, $contents, -1, $count);
        if ($count === 0) {
            \bdk\Debug::varDump('no copyright: ', $relPath);
        }
        if ($replacementCount > 0) {
            \file_put_contents($filepath, $contents);
            return true;
        }
        return false;
    }
}
