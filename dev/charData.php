<?php

/**
 * Define characters that will be highlighted / replaced
 *
 * Don't use \u{xxxx} here - not supported in PHP < 7.0
 */

// @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
return array(
    "\x00" => array(
        'class' => 'char-control',
        'desc' => 'NUL',
        'replaceWith' => "\xE2\x90\x80",
    ),
    "\x01" => array(
        'class' => 'char-control',
        'desc' => 'SOH (start of heading)',
        'replaceWith' => "\xE2\x90\x81",
    ),
    "\x02" => array(
        'class' => 'char-control',
        'desc' => 'STX (start of text)',
        'replaceWith' => "\xE2\x90\x82",
    ),
    "\x03" => array(
        'class' => 'char-control',
        'desc' => 'ETX (end of text)',
        'replaceWith' => "\xE2\x90\x83",
    ),
    "\x04" => array(
        'class' => 'char-control',
        'desc' => 'EOT (end of transmission)',
        'replaceWith' => "\xE2\x90\x84",
    ),
    "\x05" => array(
        'class' => 'char-control',
        'desc' => 'ENQ (enquiry)',
        'replaceWith' => "\xE2\x90\x85",
    ),
    "\x06" => array(
        'class' => 'char-control',
        'desc' => 'ACK (acknowledge)',
        'replaceWith' => "\xE2\x90\x86",
    ),
    "\x07" => array(
        'class' => 'char-control',
        'desc' => 'BEL (bell)',
        'replaceWith' => "\xE2\x90\x87",
    ),
    "\x08" => array(
        'class' => 'char-control',
        'desc' => 'BS (backspace)',
        'replaceWith' => "\xE2\x90\x88",
    ),
    /*
    "\x09" => array(
        'class' => 'char-control',
        'desc' => 'HT (horizontal tab)',           // \t not treated special by default
        'replaceWith' => "\u{2409}",
    ),
    "\x0A" => array(
        'class' => 'char-control',
        'desc' => 'LF (NL line feed / new line)',  // \n not treated special by default
        'replaceWith' => "\u{240A}",
    ),
    */
    "\x0B" => array(
        'class' => 'char-control',
        'desc' => 'VT (vertical tab)',
        'replaceWith' => "\xE2\x90\x8B",
    ),
    "\x0C" => array(
        'class' => 'char-control',
        'desc' => 'FF (NP form feed / new page)',
        'replaceWith' => "\xE2\x90\x8C",
    ),
    /*
    "\x0D" => array(
        'class' => 'char-control',
        'desc' => 'CR (carriage return)',          // \r not treated special by default
        'replaceWith' => "\u{240D}",
    ),
    */
    "\x0E" => array(
        'class' => 'char-control',
        'desc' => 'SO (shift out)',
        'replaceWith' => "\xE2\x90\x8E",
    ),
    "\x0F" => array(
        'class' => 'char-control',
        'desc' => 'SI (shift in)',
        'replaceWith' => "\xE2\x90\x8F",
    ),
    "\x10" => array(
        'class' => 'char-control',
        'desc' => 'DLE (data link escape)',
        'replaceWith' => "\xE2\x90\x90",
    ),
    "\x11" => array(
        'class' => 'char-control',
        'desc' => 'DC1 (device control 1)',
        'replaceWith' => "\xE2\x90\x91",
    ),
    "\x12" => array(
        'class' => 'char-control',
        'desc' => 'DC2 (device control 2)',
        'replaceWith' => "\xE2\x90\x92",
    ),
    "\x13" => array(
        'class' => 'char-control',
        'desc' => 'DC3 (device control 3)',
        'replaceWith' => "\xE2\x90\x93",
    ),
    "\x14" => array(
        'class' => 'char-control',
        'desc' => 'DC4 (device control 4)',
        'replaceWith' => "\xE2\x90\x94",
    ),
    "\x15" => array(
        'class' => 'char-control',
        'desc' => 'NAK (negative acknowledge)',
        'replaceWith' => "\xE2\x90\x95",
    ),
    "\x16" => array(
        'class' => 'char-control',
        'desc' => 'SYN (synchronous idle)',
        'replaceWith' => "\xE2\x90\x96",
    ),
    "\x17" => array(
        'class' => 'char-control',
        'desc' => 'ETB (end of trans. block)',
        'replaceWith' => "\xE2\x90\x97",
    ),
    "\x18" => array(
        'class' => 'char-control',
        'desc' => 'CAN (cancel)',
        'replaceWith' => "\xE2\x90\x98",
    ),
    "\x19" => array(
        'class' => 'char-control',
        'desc' => 'EM (end of medium)',
        'replaceWith' => "\xE2\x90\x99",
    ),
    "\x1A" => array(
        'class' => 'char-control',
        'desc' => 'SUB (substitute)',
        'replaceWith' => "\xE2\x90\x9A",
    ),
    "\x1B" => array(
        'class' => 'char-control',
        'desc' => 'ESC (escape)',
        'replaceWith' => "\xE2\x90\x9B",
    ),
    "\x1C" => array(
        'class' => 'char-control',
        'desc' => 'FS (file separator)',
        'replaceWith' => "\xE2\x90\x9C",
    ),
    "\x1D" => array(
        'class' => 'char-control',
        'desc' => 'GS (group separator)',
        'replaceWith' => "\xE2\x90\x9D",
    ),
    "\x1E" => array(
        'class' => 'char-control',
        'desc' => 'RS (record separator)',
        'replaceWith' => "\xE2\x90\x9E",
    ),
    "\x1F" => array(
        'class' => 'char-control',
        'desc' => 'US (unit separator)',
        'replaceWith' => "\xE2\x90\x9F",
    ),
    "\x7F" => array(
        'class' => 'char-control',
        'desc' => 'DEL',
        'replaceWith' => "\xE2\x90\xA1",
    ),

    "\xC2\xA0" => array(
        'codePoint' => '00A0',
        'desc' => 'NBSP',
        'replaceWith' => '\u{00a0}',
    ),
    "\xE1\x9A\x80" => array(
        'codePoint' => '1680',
        'desc' => 'Ogham Space Mark',
        'replaceWith' => '\u{1680}',
    ),
    "\xE1\xA0\x8E" => array(
        'codePoint' => '180E',
        'desc' => 'Mongolian Vowel Separator',
        'replaceWith' => '\u{180e}',
    ),
    "\xE2\x80\x80" => array(
        'codePoint' => '2000',
        'desc' => 'En Quad',
        'replaceWith' => '\u{2000}',
    ),
    "\xE2\x80\x81" => array(
        'codePoint' => '2001',
        'desc' => 'Em Quad',
        'replaceWith' => '\u{2001}',
    ),
    "\xE2\x80\x82" => array(
        'codePoint' => '2002',
        'desc' => 'En Space',
        'replaceWith' => '\u{2002}',
    ),
    "\xE2\x80\x83" => array(
        'codePoint' => '2003',
        'desc' => 'Em Space',
        'replaceWith' => '\u{2003}',
    ),
    "\xE2\x80\x84" => array(
        'codePoint' => '2004',
        'desc' => 'Three-Per-Em Space',
        'replaceWith' => '\u{2004}',
    ),
    "\xE2\x80\x85" => array(
        'codePoint' => '2005',
        'desc' => 'Four-Per-Em Space',
        'replaceWith' => '\u{2005}',
    ),
    "\xE2\x80\x86" => array(
        'codePoint' => '2006',
        'desc' => 'Six-Per-Em Space',
        'replaceWith' => '\u{2006}',
    ),
    "\xE2\x80\x87" => array(
        'codePoint' => '2007',
        'desc' => 'Figure Space',
        'replaceWith' => '\u{2007}',
    ),
    "\xE2\x80\x88" => array(
        'codePoint' => '2008',
        'desc' => 'Punctuation Space',
        'replaceWith' => '\u{2008}',
    ),
    "\xE2\x80\x89" => array(
        'codePoint' => '2009',
        'desc' => 'Thin Space',
        'replaceWith' => '\u{2009}',
    ),
    "\xE2\x80\x8A" => array(
        'codePoint' => '200A',
        'desc' => 'Hair Space',
        'replaceWith' => '\u{200a}',
    ),
    "\xE2\x80\x8B" => array(
        'codePoint' => '200B',
        'desc' => 'Zero Width Space', // not included in Separator Category
        'replaceWith' => '\u{200b}',
    ),
    "\xE2\x80\xA8" => array(
        'codePoint' => '2028',
        'desc' => 'Line Separator',
        'replaceWith' => '\u{2028}',
    ),
    "\xE2\x80\xA9" => array(
        'codePoint' => '2029',
        'desc' => 'Paragraph Separator',
        'replaceWith' => '\u{2029}',
    ),
    "\xE2\x80\xAF" => array(
        'codePoint' => '202F',
        'desc' => 'Narrow No-Break Space',
        'replaceWith' => '\u{202f}',
    ),
    "\xE2\x81\x9F" => array(
        'codePoint' => '202F',
        'desc' => 'Medium Mathematical Space',
        'replaceWith' => '\u{205f}',
    ),
    "\xE3\x80\x80" => array(
        'codePoint' => '3000',
        'desc' => 'Ideographic Space',
        'replaceWith' => '\u{3000}',
    ),
    "\xEF\xBB\xBF" => array(
        'codePoint' => 'FEFF',
        'desc' => 'BOM / Zero Width No-Break Space', // not included in Separator Category
        'replaceWith' => '\u{feff}',
    ),
    "\xEF\xBF\xBD" => array(
        'codePoint' => 'FFFD',
        'desc' => 'Replacement Character',
    ),
);