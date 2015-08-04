<?php
/*
 * This file is part of PHPUnit DOM Assertions.
 *
 * (c) Brad Kent <bkfake-github@yahoo.com>
 */

/**
 *
 */
abstract class PHPUnit_Framework_DOMTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Assert the presence, absence, or count of elements in a document matching
     * the CSS $selector, regardless of the contents of those elements.
     *
     * The first argument, $selector, is the CSS selector used to match
     * the elements in the $actual document.
     *
     * The second argument, $count, can be either boolean or numeric.
     * When boolean, it asserts for presence of elements matching the selector
     * (true) or absence of elements (false).
     * When numeric, it asserts the count of elements.
     *
     * assertSelectCount("#binder", true, $xml);  // any?
     * assertSelectCount(".binder", 3, $xml);     // exactly 3?
     *
     * @param array                 $selector CSS selector
     * @param integer|boolean|array $count    bool, count, or array('>'=5, <=10)
     * @param mixed                 $actual   HTML
     * @param string                $message  exception message
     * @param boolean               $isHtml   not used
     *
     * @return void
     */
    public static function assertSelectCount($selector, $count, $actual, $message = '', $isHtml = true)
    {
        self::assertSelectEquals($selector, true, $count, $actual, $message, $isHtml);
    }

    /**
     * assertSelectRegExp("#binder .name", "/Mike|Derek/", true, $xml); // any?
     * assertSelectRegExp("#binder .name", "/Mike|Derek/", 3, $xml);    // 3?
     *
     * @param array                 $selector CSS selector
     * @param string                $pattern  regex
     * @param integer|boolean|array $count    bool, count, or array('>'=5, <=10)
     * @param mixed                 $actual   HTML or domdocument
     * @param string                $message  exception message
     * @param boolean               $isHtml   not used
     *
     * @return void
     */
    public static function assertSelectRegExp($selector, $pattern, $count, $actual, $message = '', $isHtml = true)
    {
        self::assertSelectEquals($selector, "regexp:$pattern", $count, $actual, $message, $isHtml);
    }

    /**
     * assertSelectEquals("#binder .name", "Chuck", true,  $xml);  // any?
     * assertSelectEquals("#binder .name", "Chuck", false, $xml);  // none?
     *
     * @param string                $selector css selector
     * @param string                $content  content to match against.  may specify regex as regexp:/regexp/
     * @param integer|boolean|array $count    bool, count, or array('>'=5, <=10)
     * @param mixed                 $actual   html or domdocument
     * @param string                $message  exception message
     * @param boolean               $isHtml   not used
     *
     * @return void
     * @throws PHPUnit_Framework_Exception
     * @link https://phpunit.de/manual/3.7/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.assertions.assertSelectEquals
     */
    public static function assertSelectEquals($selector, $content, $count, $actual, $message = '', $isHtml = true)
    {
        $found =  \bdk\CssSelect::select($actual, $selector);
        if (is_string($content)) {
            /*
            $crawler = $crawler->reduce(function (Crawler $node, $i) use ($content) {
                if ($content === '') {
                    return $node->text() === '';
                }
                if (preg_match('/^regexp\s*:\s*(.*)/i', $content, $matches)) {
                    return (bool) preg_match($matches[1], $node->text());
                }
                return strstr($node->text(), $content) !== false;
            });
            */
            foreach ($found as $k => $node) {
                $keep = true;
                if ($content === '') {
                    $keep = $node['innerHTML'] === '';
                } elseif (preg_match('/^regexp\s*:\s*(.*)/i', $content, $matches)) {
                    $keep = (bool) preg_match($matches[1], $node['innerHTML']);
                } else {
                    $keep = strstr($node['innerHTML'], $content) !== false;
                }
                if (!$keep) {
                    unset($found[$k]);
                }
            }
        }
        $countFound = count($found);
        if (is_numeric($count)) {
            self::assertEquals($count, $countFound, $message);
        } elseif (is_bool($count)) {
            $found = $found > 0;
            if ($count) {
                self::assertTrue($countFound, $message);
            } else {
                self::assertFalse($countFound, $message);
            }
        } elseif (
            is_array($count) &&
            (isset($count['>']) || isset($count['<']) ||
            isset($count['>=']) || isset($count['<=']))) {
            if (isset($count['>'])) {
                self::assertTrue($countFound > $count['>'], $message);
            }
            if (isset($count['>='])) {
                self::assertTrue($countFound >= $count['>='], $message);
            }
            if (isset($count['<'])) {
                self::assertTrue($countFound < $count['<'], $message);
            }
            if (isset($count['<='])) {
                self::assertTrue($countFound <= $count['<='], $message);
            }
        } else {
            throw new PHPUnit_Framework_Exception('Invalid count format');
        }
    }
}
