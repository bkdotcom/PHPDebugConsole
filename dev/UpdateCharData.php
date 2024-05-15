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

    /** @var array<string, charInfo> */
	protected $charData = array();

	/**
	 * Update confusableData.php
	 *
	 * @return void
	 */
	public static function update()
	{
		$filepathOut = __DIR__ . '/../src/Debug/Dump/charData.php';
        $comment = '/**
            * This file is generated automatically from confusables.txt
            * https://www.unicode.org/Public/security/latest/confusables.txt
            *
            * `composer run update-char-data`
            *
            * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            */';
		$php = '<?php // phpcs:ignore SlevomatCodingStandard.Files.FileLength' . "\n\n"
            . \preg_replace('/^[ ]{12}/m', ' ', $comment) . "\n\n"
			. 'return ' . self::varExportPretty(self::build()) . ";\n";
        $php = \preg_replace_callback('/[\'"](.)[\'"] => /u', static function ($matches) {
            $char = $matches[1];
            $codePoint = \mb_ord($char);
            return $codePoint < 0x80
                ? '"\\x' . \dechex($codePoint) . '" => '
                : '\'' . $char . '\' => ';
        }, $php);
		\file_put_contents($filepathOut, $php);
	}

	/**
	 * Build char data
	 *
	 * @return array<string, array<string, string|bool>>
	 */
	public static function build()
	{
		$rows = self::getParsedRows();

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
	 * Return parsed data for all confusable data
	 *
	 * @return array<string, string|bool>[]
	 */
	private static function getParsedRows()
	{
		$rows = \file(self::$filepathSrc);
		$rows = \array_filter($rows, static function ($row) {
			$isEmptyOrComment = \strlen(\trim($row)) === 0 || $row[0] === '#';
			return $isEmptyOrComment === false;
		});

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
		$parts = \array_combine(array('charACodePoint', 'charBCodePoint', 'comment'), $parts);

		$parts['charACodePoint'] = \implode(' ', \array_map(static function ($codePoint) {
			// remove leading 00 pairs
			return \preg_replace('/^(00)+/', '', $codePoint);
		}, \explode(' ', $parts['charACodePoint'])));

		$parts['charBCodePoint'] = \implode(' ', \array_map(static function ($codePoint) {
			// remove leading 00 pairs
			return \preg_replace('/^(00)+/', '', $codePoint);
		}, \explode(' ', $parts['charBCodePoint'])));

		\preg_match('/^(?P<category>\w+)\t#(?P<notXid>\*?)\s*(?P<example>\(.*?\))\s*(?P<charADesc>.*?) → (?P<charBDesc>.*?)(\s+#.*)?$/u', $parts['comment'], $matches);
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
}
