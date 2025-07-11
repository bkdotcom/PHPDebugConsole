<?php

namespace bdk\Debug\Dev;

use DateTimeImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * update @copyright tags, etc
 */
class UpdatePhpDocFileHeader
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
            if (\preg_match('/node_modules/', $filepath)) {
                // skip files that are not in the src directory
                continue;
            }
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
     * @param string $relPath  relative path
     *
     * @return bool whether updated
     */
    protected static function updateFile($filepath, $year, $relPath)
    {
        $contents = \file_get_contents($filepath);
        $fileInfo = array(
            'hasCopyright' => true,
        );
        $md5Orig = \md5($contents);

        $contents = \preg_replace_callback('/^(\s*\*\s*@copyright\s+)(.*)$/m', static function ($matches) use ($year) {
            $comment = \preg_replace('/\b(\d{2,4}\-)?(\d{2,4})\b/', '${1}' . $year, $matches[2]);
            return $matches[1] . $comment;
        }, $contents, -1, $count);
        if ($count === 0) {
            $fileInfo['hasCopyright'] = false;
        }

        $fileInfoFalse = \array_filter($fileInfo, static function ($value) {
            return $value === false;
        });
        if ($fileInfoFalse) {
            \bdk\Debug::varDump($relPath, $fileInfoFalse);
        }

        if (\md5($contents) !== $md5Orig) {
            \file_put_contents($filepath, $contents);
            return true;
        }
        return false;
    }
}
