<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Php;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\Fixture\TestObj;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Utility class
 *
 * @covers \bdk\Debug\Utility
 * @covers \bdk\Debug\Utility\Php
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class PhpTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    public function testBuildDate()
    {
        $buildDate = Php::buildDate();
        $datetime = new \DateTime($buildDate);
        self::assertTrue($datetime > new \DateTime('2000-01-01'));
    }

    /**
     * @param string $input
     * @param string $expect
     *
     * @dataProvider providerFriendlyClassName
     */
    public function testFriendlyClassName($input, $expect)
    {
        self::assertSame($expect, Php::friendlyClassName($input));
    }

    public function testGetIncludedFiles()
    {
        $filesA = \get_included_files();
        $filesB = Php::getIncludedFiles();
        \sort($filesA);
        \sort($filesB);
        self::assertArraySubset($filesA, $filesB);
    }

    public function testGetIniFiles()
    {
        self::assertSame(
            \array_merge(
                array(\php_ini_loaded_file()),
                \preg_split('#\s*[,\r\n]+\s*#', \trim(\php_ini_scanned_files()))
            ),
            Php::getIniFiles()
        );
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test
     */
    public function testMemoryLimit()
    {
        self::assertNotNull(Php::memoryLimit());
    }

    public function testUnserializeSafe()
    {
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $serialized = \serialize(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'obj' => new \bdk\Test\Debug\Fixture\TestTraversable(array('foo' => 'bar')),
            'after' => 'bar',
        ));

        // allow everything
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'obj' => new \bdk\Test\Debug\Fixture\TestTraversable(array('foo' => 'bar')),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, true));

        // disable all (stdClass still allowed)
        $serialized = 'a:5:{s:6:"before";s:3:"foo";s:8:"stdClass";O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}s:12:"serializable";C:35:"bdk\Test\Debug\Fixture\Serializable":13:{Brad was here}s:3:"obj";O:38:"bdk\Test\Debug\Fixture\TestTraversable":1:{s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}s:5:"after";s:3:"bar";}';
        $unserialized = Php::unserializeSafe($serialized, false);
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'serializable' => \unserialize('O:22:"__PHP_Incomplete_Class":2:{s:27:"__PHP_Incomplete_Class_Name";s:35:"bdk\Test\Debug\Fixture\Serializable";s:17:"__serialized_data";s:13:"Brad was here";}'),
            'obj' => \unserialize('O:22:"__PHP_Incomplete_Class":2:{s:27:"__PHP_Incomplete_Class_Name";s:38:"bdk\Test\Debug\Fixture\TestTraversable";s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}'),
            'after' => 'bar',
        ), $unserialized);

        // no Serializable (vanila unserialize will be used
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $serialized = \serialize(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'after' => 'bar',
        ));
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, false));
    }

    public static function providerFriendlyClassName()
    {
        $fcnExpect = 'bdk\Test\Debug\Fixture\TestObj';
        $obj = new TestObj();
        $strClassname = 'bdk\Test\Debug\Fixture\TestObj';
        $tests = array(
            'obj' => array($obj, $fcnExpect),
            'reflectionClass' => array(new \ReflectionClass($strClassname), $fcnExpect),
            'reflectionObject' => array(new \ReflectionObject($obj), $fcnExpect),
            'strClassname' => array($strClassname, $fcnExpect),
            'strMethod' => array('\bdk\Test\Debug\Fixture\TestObj::methodPublic()', $fcnExpect),
            'strProperty' => array('\bdk\Test\Debug\Fixture\TestObj::$someArray', $fcnExpect),
        );
        if (PHP_VERSION_ID >= 70000) {
            $anonymous = require TEST_DIR . '/Debug/Fixture/Anonymous.php';
            $tests = \array_merge($tests, array(
                'anonymous' => array($anonymous['anonymous'], 'class@anonymous'),
                'anonymousExtends' => array($anonymous['stdClass'], 'stdClass@anonymous'),
                'anonymousImplements' => array($anonymous['implements'], 'IteratorAggregate@anonymous'),
            ));
        }
        if (PHP_VERSION_ID >= 70100) {
            $tests['strConstant'] = array('\bdk\Test\Debug\Fixture\TestObj::MY_CONSTANT', $fcnExpect);
        }
        if (PHP_VERSION_ID >= 80100) {
            $tests = \array_merge($tests, array(
                'enum' => array(\bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST, 'bdk\Test\Debug\Fixture\Enum\Meals'),
                'enum.backed' => array(\bdk\Test\Debug\Fixture\Enum\MealsBacked::BREAKFAST, 'bdk\Test\Debug\Fixture\Enum\MealsBacked'),
            ));
        }
        return $tests;
    }
}
