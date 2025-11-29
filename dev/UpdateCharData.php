<?php

namespace bdk\Debug\Dev;

/**
 * Pull latest confusables.txt from unicode and save to php file
 *
 * @psalm-import-type charInfo from \bdk\Debug\Plugin\CharHighlight
 */
class UpdateCharData
{
    /** @var string */
    public static $filepathSrc = 'https://www.unicode.org/Public/security/latest/confusables.txt';

    /** @var string */
    public static $filepathOut = '../src/Debug/Dump/charData.php';

    /** @var array<string,string> populated via loadData() */
    protected static $sourceMeta = array(
        'date' => '',
        'version' => '',
    );

    /**
     * Update confusableData.php
     *
     * @return void
     */
    public static function update()
    {
        $parsedData = self::loadData();
        $charData = self::build($parsedData);
        $filepathOut = \realpath(__DIR__ . '/' . self::$filepathOut);
        $php = '<?php' . "\n\n"
            . self::buildComment() . "\n\n"
            . 'return ' . self::varExportPretty($charData) . ";\n";
        $php = \preg_replace_callback('/[\'"](.)[\'"] => /u', static function ($matches) {
            $char = $matches[1];
            $codePoint = \mb_ord($char);
            return $codePoint < 0x80
                ? '"\\x' . \dechex($codePoint) . '" => '
                : '\'' . $char . '\' => ';
        }, $php);
        $result = \file_put_contents($filepathOut, $php);
        self::output(
            $result !== false
                ? self::ansiColor('Wrote ', 'info') . $filepathOut . ' successfully'
                : self::ansiColor('Error writing ', 'error') . $filepathOut
        );
    }

    /**
     * Build char data array
     *
     * @param array $rows parsed rows
     *
     * @return array<string,array<string,string|bool>>
     */
    public static function build(array $rows)
    {
        // only interested in chars that are confusable with an ascii char
        // not interested in ascii chars that are confusable with other ascii chars
        $rows = \array_filter($rows, static function ($row) {
            $isCharAAscii = \strlen($row['charA']) === 1 && \ord($row['charA']) < 0x80;
            $isCharBAscii = \strlen($row['charB']) === 1 && \ord($row['charB']) < 0x80;
            return $isCharAAscii === false && $isCharBAscii;
        });

        \usort($rows, static function ($rowA, $rowB) {
            return \strcmp($rowA['charA'], $rowB['charA']);
        });

        // rekey
        $rowsNew = require __DIR__ . '/charData.php';
        foreach ($rows as $row) {
            $key = $row['charA'];
            if (isset($rowsNew[$key])) {
                continue;
            }
            unset($row['charA']);
            $rowsNew[$key] = array(
                'codePoint' => $row['charACodePoint'],
                'desc' => $row['charADesc'],
                'similarTo' => $row['charB'],
            );
        }

        \ksort($rowsNew);

        return $rowsNew;
    }

    /**
     * Wrap text in ansi color codes
     *
     * @param string $text  text to color
     * @param string $color semantic color name
     *
     * @return string
     */
    protected static function ansiColor($text, $color)
    {
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        $colors = array(
            'emergency' => "\e[38;5;11;1;4m",
            'alert' => "\e[38;5;226m",
            'critical' => "\e[38;5;220;1m",
            'error' => "\e[38;5;220m",
            'warning' => "\e[38;5;214;40m",
            'notice' => "\e[38;5;208m",
            'info' => "\e[38;5;51m",
            'muted' => "\e[38;5;247m",
        );
        $colorReset = "\e[0m";
        return isset($colors[$color])
            ? $colors[$color] . $text . $colorReset
            : $text;
    }

    /**
     * Build output file's comment
     *
     * @return string
     */
    private static function buildComment()
    {
        $comment = '/**
            * This file is generated automatically
            *
            * `composer run update-char-data`
            *
            * Built / Checked:  ' . \date(\DateTime::RFC3339) . '
            *
            * Source:
            *  url: https://www.unicode.org/Public/security/latest/confusables.txt
            *  date: ' . self::$sourceMeta['date'] . '
            *  version: ' . self::$sourceMeta['version'] . '
            */';
        return \preg_replace('/^[ ]{12}/m', ' ', $comment);
    }

    /**
     * Load parsed data from source file
     *
     * @return array
     */
    private static function loadData()
    {
        $rows = \file(self::$filepathSrc);
        $rows = \array_filter($rows, static function ($row) {
            if ($row[0] === '#') {
                if (\preg_match('/^# Date:\s+(?P<date>.+)$/', $row, $matches)) {
                    self::$sourceMeta['date'] = $matches['date'];
                } elseif (\preg_match('/^# Version:\s+(?P<version>.+)$/', $row, $matches)) {
                    self::$sourceMeta['version'] = $matches['version'];
                }
                return false;
            }
            return \strlen(\trim($row)) > 0;
        });

        self::output(self::ansiColor('Loaded ', 'info') . self::$filepathSrc . ' successfully');
        self::output(\sprintf(
            self::ansiColor('Version: ', 'info') . '%s' . self::ansiColor(' (%s)', 'muted'),
            self::$sourceMeta['version'],
            self::$sourceMeta['date']
        ));

        return \array_map(static function ($row) {
            return self::parseRow($row);
        }, $rows);
    }

    /**
     * Parse confusable.txt row
     *
     * @param string $row non-comment row from data file
     *
     * @return array<string,mixed>
     */
    protected static function parseRow($row)
    {
        $parts = \explode(';	', $row, 3);
        $parts = \array_map('trim', $parts);
        $parts = \array_combine(['charACodePoint', 'charBCodePoint', 'comment'], $parts);

        $parts['charACodePoint'] = \implode(' ', \array_map(static function ($codePoint) {
            // remove leading 00 pairs
            return \preg_replace('/^(00)+/', '', $codePoint);
        }, \explode(' ', $parts['charACodePoint'])));

        $parts['charBCodePoint'] = \implode(' ', \array_map(static function ($codePoint) {
            // remove leading 00 pairs
            return \preg_replace('/^(00)+/', '', $codePoint);
        }, \explode(' ', $parts['charBCodePoint'])));
        \preg_match('/^(?P<category>\w+)\t#(?P<notXid>\*?)\s*(?P<example>\([^\)]*+\))\s*(?P<charADesc>.*?) → (?P<charBDesc>.*?)(\s+#.*)?$/u', $parts['comment'], $matches);
        $parts = \array_merge($parts, $matches);

        return array(
            'charA' => \implode('', \array_map(static function ($hex) {
                $codePoint = \hexdec($hex);
                return \mb_chr($codePoint, 'UTF-8');
            }, \explode(' ', $parts['charACodePoint']))),
            'charACodePoint' => $parts['charACodePoint'],
            'charADesc' => $parts['charADesc'],

            'charB' => \implode('', \array_map(static function ($hex) {
                $codePoint = \hexdec($hex);
                return \mb_chr($codePoint, 'UTF-8');
            }, \explode(' ', $parts['charBCodePoint']))),
            'isXid' => empty($parts['notXid']),
        );
    }

    /**
     * export value as valid php
     *
     * @param mixed $val Value to export
     *
     * @return string
     */
    protected static function varExportPretty($val)
    {
        $php = \var_export($val, true);
        $php = \str_replace('array (', 'array(', $php);
        $php = \preg_replace('/=> \n\s+array/', '=> array', $php);
        $php = \preg_replace_callback('/^(\s*)/m', static function ($matches) {
            return \str_repeat($matches[1], 2);
        }, $php);
        $php = \str_replace('\'\' . "\0" . \'\'', '"\x00"', $php);
        return $php;
    }

    /**
     * Output string
     *
     * @param string $str String to output
     *
     * @return void
     */
    protected static function output($str)
    {
        echo $str . "\n";
    }
}
