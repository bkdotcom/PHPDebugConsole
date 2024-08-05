<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.3
 */

namespace bdk\Debug\Utility\PhpDoc;

/**
 * PhpDoc parsing helper methods
 *
 * @psalm-import-type TagInfo from \bdk\Debug\Utility\PhpDoc
 */
class Helper
{
    /**
     * Split description and summary
     *
     * @param string $comment Beginning of doc comment
     *
     * @return array{desc?:string,summary?:string}
     */
    public static function parseDescSummary($comment)
    {
        /*
            Do some string replacement
        */
        $comment = \preg_replace('/^\\\@/m', '@', $comment);
        $comment = \str_replace('{@*}', '*/', $comment);
        /*
            split into summary & description
            summary ends with empty whitespace line or "." followed by \n
        */
        $split = \preg_split('/(\.[\r\n]+|[\r\n]{2})/', $comment, 2, PREG_SPLIT_DELIM_CAPTURE);
        $split = \array_replace(array('', '', ''), $split);
        // assume that summary and desc won't be "0"..  remove empty value and merge
        return \array_filter(array(
            'desc' => self::trimDesc($split[2]),
            'summary' => \trim($split[0] . $split[1]),    // split[1] is the ".\n"
        ));
    }

    /**
     * Trim leading spaces from each description line
     *
     * @param string $desc string to trim
     *
     * @return string
     */
    public static function trimDesc($desc)
    {
        $desc = \rtrim((string) $desc);
        $lines = \explode("\n", $desc);
        $leadingSpaces = array();
        $trimLineStart = 0;
        foreach (\array_filter($lines) as $line) {
            $leadingSpaces[] = \strspn($line, ' ');
        }
        if (\reset($leadingSpaces) === 0) {
            $trimLineStart = 1;
            \array_shift($leadingSpaces);
        }
        $trimLen = $leadingSpaces
            ? \min($leadingSpaces)
            : 0;
        for ($i = $trimLineStart, $count = \count($lines); $i < $count; $i++) {
            $lines[$i] = \substr($lines[$i], $trimLen);
        }
        return \implode("\n", $lines);
    }
}
