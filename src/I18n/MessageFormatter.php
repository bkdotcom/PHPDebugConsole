<?php

/**
 * @package   bdk/i18n
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2025-2025 Brad Kent
 * @since     1.0
 */

namespace bdk\I18n;

use bdk\I18n\NumberFormatter;
use DomainException;

/*
 * Copyright © 2008 by Yii Software LLC (http://www.yiisoft.com)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *  * Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *  * Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *  * Neither the name of Yii Software LLC nor the names of its
 *    contributors may be used to endorse or promote products derived
 *    from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * Originally forked from
 * https://github.com/symfony/polyfill-intl-messageformatter/blob/1.x/MessageFormatter.php
 *
 * Originally forked from
 * https://github.com/yiisoft/yii2/blob/2.0.15/framework/i18n/MessageFormatter.php
 */

/**
 * A polyfill implementation of the MessageFormatter class provided by the intl extension.
 *
 * It only supports the following message formats:
 *  * number formatting
 *  * plural formatting for english ('one' and 'other' selectors)
 *  * select format
 *  * simple parameters
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @author Carsten Brandt <mail@cebe.cc>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Brad Kent <bkfake.github@yahoo.com
 *
 * @internal
 */
class MessageFormatter
{
    /** @var int */
    private $errorCode = 0;

    /** @var string */
    private $errorMessage = '';

    /** @var string */
    private $locale;

    /** @var string */
    private $pattern;

    /** @var array */
    private $tokens;

    /** @var array info for current tokenization */
    private $tokenizeInfo = array();

    /** @var string */
    private $stringInvalidPattern = 'Message pattern is invalid.';

    /**
     * Constructor
     *
     * @param string $locale  The locale to use when formatting arguments
     * @param string $pattern The pattern string to stick arguments into
     *
     * @throws DomainException
     */
    public function __construct($locale, $pattern)
    {
        $this->locale = $locale;
        if (!$this->setPattern($pattern)) {
            throw new DomainException($this->stringInvalidPattern);
        }
    }

    /**
     * Constructs a new Message Formatter
     *
     * @param string $locale  The locale to use when formatting arguments
     * @param string $pattern The pattern string to stick arguments into
     *
     * @return static|null
     */
    public static function create($locale, $pattern)
    {
        $formatter = new static($locale, '-');
        return $formatter->setPattern($pattern) ? $formatter : null;
    }

    /**
     * Format the message
     *
     * @param array $values Arguments to insert into the format string
     *
     * @return string|false
     */
    public function format(array $values)
    {
        $this->errorCode = 0;
        $this->errorMessage = '';

        try {
            return $this->parseTokens($this->tokens, $values);
        } catch (DomainException $e) {
            $this->errorCode = -1;
            $this->errorMessage = $e->getMessage();
            return false;
        }
    }

    /**
     * Quick format message
     *
     * @param string $locale  The locale to use for formatting locale-dependent parts
     * @param string $pattern The pattern string to insert things into
     * @param array  $values  The array of values to insert into the format string
     *
     * @return string|false
     */
    public static function formatMessage($locale, $pattern, $values)
    {
        $formatter = self::create($locale, $pattern);
        return $formatter !== null
            ? $formatter->format($values)
            : false;
    }

    /**
     * Get the error code from last operation
     *
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Get the error text from the last operation
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Get the locale for which the formatter was created
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Get the pattern used by the formatter
     *
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Parse input string according to pattern
     *
     * @param string $string The string to parse
     *
     * @return false Polyfill does not support this method
     */
    public function parse($string) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        $this->errorCode = -1;
        $this->errorMessage = \sprintf('The PHP intl extension is required to use "MessageFormatter::%s()".', __FUNCTION__);
        return false;
    }

    /**
     * Quick parse input string
     *
     * @param string $locale  The locale to use for parsing locale-dependent parts
     * @param string $pattern The pattern with which to parse the message.
     * @param string $message The string to parse, conforming to the pattern.
     *
     * @return false Polyfill does not support this method
     */
    public static function parseMessage($locale, $pattern, $message) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        return false;
    }

    /**
     * Set the pattern used by the formatter
     *
     * @param string $pattern The pattern string to use in this message formatter
     *
     * @return bool
     */
    public function setPattern($pattern)
    {
        try {
            $this->tokens = $this->tokenizePattern($pattern);
            $this->pattern = $pattern;
        } catch (DomainException $e) {
            return false;
        }
        return true;
    }

    /**
     * Parses pattern based on ICU grammar.
     *
     * @param array $token  parsed token
     * @param array $values The substitution values to insert into the format string
     *
     * @return string
     *
     * @throws DomainException
     *
     * @see http://icu-project.org/apiref/icu4c/classMessageFormat.html#details
     */
    private function parseToken(array $token, array $values)
    {
        $param = $token[0];
        if (!isset($values[$param])) {
            return '{' . $param . '}';
        }
        $return = $this->parseTokenType($token, $values[$param]);
        if ($return === false) {
            throw new DomainException($this->stringInvalidPattern);
        }
        return $this->parseTokens($this->tokenizePattern($return), $values);
    }

    /**
     * Parse the givent token type
     *
     * @param array $token parsed token
     * @param mixed $value the substitution value passed in the args array
     *
     * @return string
     *
     * @throws DomainException
     */
    private function parseTokenType(array $token, $value)
    {
        $type = isset($token[1]) ? $token[1] : 'none';
        switch ($type) {
            case 'none':
                return $value;

            case 'number':
                $style = isset($token[2]) ? $token[2] : null;
                $formatter = new NumberFormatter($this->locale);
                return $formatter->format($value, $style);

            case 'select':
                return $this->parseTokenSelect($value, $token);

            case 'plural':
                return $this->parseTokenPlural($value, $token);

            default:
                // 'date':  DateFormatter
                // 'time':
                // 'spellout':
                // 'ordinal':
                // 'duration':
                // 'choice':
                // 'selectordinal':
        }
        throw new DomainException(\sprintf('Unsupported %s format and/or the PHP intl extension is required.', $type));
    }

    /**
     * Parse message ("pattern") tokens and return finished ("formatted") message
     *
     * @param array $tokens tokens to parse
     * @param array $values substitution values
     *
     * @return string
     */
    private function parseTokens(array $tokens, array $values)
    {
        return \implode('', \array_map(function ($token) use ($values) {
            return \is_array($token)
                ? $this->parseToken($token, $values)
                : $token;
        }, $tokens));
    }

    /**
     * Parse plural token
     *
     * http://icu-project.org/apiref/icu4c/classicu_1_1PluralFormat.html
     *   pluralStyle = [offsetValue] (selector '{' message '}')+
     *   offsetValue = "offset:" number
     *   selector = explicitValue | keyword
     *   explicitValue = '=' number  // adjacent, no white space in between
     *   keyword = [^[[:Pattern_Syntax:][:Pattern_White_Space:]]]+
     *   message: see MessageFormat
     *
     * @param string $value Argument value
     * @param array  $token The plural token
     *
     * @return string|false
     *
     * @throws DomainException
     */
    private function parseTokenPlural($value, array $token)
    {
        if (!isset($token[2])) {
            throw new DomainException($this->stringInvalidPattern);
        }
        $plural = $this->tokenizePattern($token[2]);
        $offset = 0;
        if (\strncmp(\trim($plural[0]), 'offset:', 7) === 0) {
            $selector = \trim($plural[0]);
            $pos = \strpos(\str_replace(["\n", "\r", "\t"], ' ', $selector), ' ', 7);
            $offset = (int) \trim(\substr($selector, 7, $pos - 7));
            $plural[0] = \trim(\substr($selector, 1 + $pos, \strlen($selector)));
        }
        $count = \count($plural);
        for ($i = 0; $i + 1 < $count; $i += 2) {
            $this->assertPluralSelect($plural[$i], $plural[$i + 1]);
            if ($this->testPluralSelector(\trim($plural[$i]), $value, $offset)) {
                // @todo handle escaped #'s
                return \implode(',', \str_replace('#', $value - $offset, $plural[$i + 1]));
            }
        }
        return false;
    }

    /**
     * Assert plural/select selector / message is valid
     *
     * @param string $selector selector
     * @param array  $message  replacement message
     *
     * @return void
     *
     * @throws DomainException
     */
    private function assertPluralSelect($selector, $message)
    {
        if (empty($selector) || \is_array($selector) || !\is_array($message)) {
            throw new DomainException($this->stringInvalidPattern);
        }
    }

    /**
     * Test if plural selector matches value
     *
     * @param string $selector plural selector
     * @param int    $value    Argument value
     * @param int    $offset   plural offset
     *
     * @return bool
     */
    private function testPluralSelector($selector, $value, $offset)
    {
        if ($selector[0] === '=') {
            return (int) \substr($selector, 1, \strlen($selector)) === $value;
        }
        switch ($selector) {
            case 'zero':
                return $value - $offset === 0;
            case 'one':
                return $value - $offset === 1;
            // two
            // few
            // many
            case 'other':
                return true;
        }
    }

    /**
     * Parse select token
     *
     * http://icu-project.org/apiref/icu4c/classicu_1_1SelectFormat.html
     * selectStyle = (selector '{' message '}')+
     *
     * @param string $value Argument value
     * @param array  $token The select token
     *
     * @return string|false
     *
     * @throws DomainException
     */
    private function parseTokenSelect($value, array $token)
    {
        if (!isset($token[2])) {
            throw new DomainException($this->stringInvalidPattern);
        }
        $select = $this->tokenizePattern($token[2]);
        $count = \count($select);
        for ($i = 0; $i + 1 < $count; $i += 2) {
            $this->assertPluralSelect($select[$i], $select[$i + 1]);
            $selector = \trim($select[$i]);
            if ($selector === $value || $selector === 'other') {
                return \implode(',', $select[$i + 1]);
            }
        }
        return false;
    }

    /**
     * Split pattern into tokens
     *
     * @param string $pattern The pattern/message string
     *
     * @return array
     *
     * @throws DomainException
     */
    private function tokenizePattern($pattern)
    {
        $regex = '/(\')?([{}\'])/';
        $this->tokenizeInfo = array(
            'depth' => 0,
            'inQuotedString' => false,
            'offset' => 0,
            'openPos' => 0, // keep track of zero-depth open bracket position
            'pattern' => $pattern,
            'token' => '',
            'tokens' => [],
        );
        while (\preg_match($regex, $pattern, $matches, PREG_OFFSET_CAPTURE, $this->tokenizeInfo['offset']) && $this->tokenizeInfo['depth'] > -1) {
            $this->tokenizePatternRegexMatch($matches);
        }
        $tokens = $this->tokenizeInfo['tokens'];
        if ($this->tokenizeInfo['offset'] < \strlen($pattern)) {
            $tokens[] = $this->tokenizeInfo['token'] . \substr($pattern, $this->tokenizeInfo['offset']);
        }
        if ($this->tokenizeInfo['depth'] !== 0) {
            throw new DomainException($this->stringInvalidPattern);
        }
        return $tokens;
    }

    /**
     * Process tokenization regex matches
     *
     * @param array $matches regex matches
     *
     * @return void|null
     */
    private function tokenizePatternRegexMatch(array $matches)
    {
        $substrStart = $this->tokenizeInfo['offset'];
        $this->tokenizeInfo['offset'] = $matches[2][1] + 1; // new offset for next preg_match
        $char = $matches[2][0];
        $isApostrophe = $char === '\'';
        $isEscaped = $matches[1][0] !== '';
        $len = $this->tokenizeInfo['offset'] - $substrStart - 1;
        switch (true) {
            case $isEscaped:
                $this->tokenizeInfo['inQuotedString'] = $this->tokenizeInfo['inQuotedString'] || $isApostrophe === false;
                $this->tokenizeInfo['token'] .= \substr($this->tokenizeInfo['pattern'], $substrStart, $len - 1) . $char;
                break;
            case $this->tokenizeInfo['inQuotedString']:
                // if we're in quoted string, don't treat { or } as special
                if ($isApostrophe) {
                    // unquoted apostrophe.. end of quoted string
                    $this->tokenizeInfo['inQuotedString'] = false;
                    $char = '';
                }
                // fall through
            case $isApostrophe:
                $this->tokenizeInfo['token'] .= \substr($this->tokenizeInfo['pattern'], $substrStart, $len) . $char;
                break;
            case $char === '{':
                return $this->tokenizeBracketOpen($substrStart, $len);
            case $char === '}':
                return $this->tokenizeBracketClose();
        }
    }

    /**
     * Handle open bracket ("{") found
     *
     * @param int $substrStart where to start substr
     * @param int $len         length of substr
     *
     * @return void
     */
    private function tokenizeBracketOpen($substrStart, $len)
    {
        if ($this->tokenizeInfo['depth'] === 0) {
            $this->tokenizeInfo['openPos'] = $this->tokenizeInfo['offset'];
            $this->tokenizeInfo['token'] .= \substr($this->tokenizeInfo['pattern'], $substrStart, $len);
            $this->tokenizeInfo['tokens'][] = $this->tokenizeInfo['token'];
            $this->tokenizeInfo['token'] = '';
        }
        $this->tokenizeInfo['depth']++;
    }

    /**
     * Handle close bracket ("}") found
     *
     * @return void
     */
    private function tokenizeBracketClose()
    {
        $this->tokenizeInfo['depth']--;
        if ($this->tokenizeInfo['depth'] === 0) {
            $len = $this->tokenizeInfo['offset'] - 1 - $this->tokenizeInfo['openPos'];
            $token = \substr($this->tokenizeInfo['pattern'], $this->tokenizeInfo['openPos'], $len);
            $this->tokenizeInfo['tokens'][] = \array_map('trim', \explode(',', $token, 3));
        }
    }
}
