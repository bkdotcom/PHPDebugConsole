<?php

namespace bdk\Test\I18n;

use bdk\HttpMessage\ServerRequest;
use bdk\I18n;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\I18n
 */
class I18nTest extends TestCase
{
    public function testConstructor()
    {
        $_SESSION = array(
            'lang' => 'ses',
        );
        $serverRequest = (new ServerRequest())
            ->withQueryParams(array(
                'lang' => 'get',
            ))
            ->withCookieParams(array(
                'lang' => 'coo',
            ))
            ->withParsedBody(array(
                'lang' => 'pos',
                'locale' => 'pos-2',
            ))
            ->withHeader('Accept-Language', 'en-US, boogus, es-ES;q=0.9, es-MX;q=hi, en-UK;q=0.8, *;q=0.5');
        $i18n = new I18n($serverRequest, array(
            'filepath' => __DIR__ . '/trans/{domain}/{locale}.php',
            'localeFirstChoice' => 'fc',
            'priority' => array(
                'cfg:localeFirstChoice',
                'request:post.lang',
                'request:post.locale',
                'request:get.lang',
                'request:session.lang',
                'request:cookie.lang',
                'request:header.Accept-Language',
                'cfg:localeFallback',
            ),
            ));
        self::assertSame([
            "fc",
            "pos",
            "pos_2",
            "get",
            "ses",
            "coo",
            "en_US",
            "es_ES",
            "en_UK",
            "en",
            "es",
        ], $i18n->getUserLocales());
        self::assertSame('Gulf of America', $i18n->trans('gulf.of.mexico'));
        self::assertSame('howdy', $i18n->trans('hello'));
    }

    /**
     * Test Trans
     *
     * @param I18n   $i18n     I18n instance
     * @param string $str      String to translate
     * @param array  $args     optional Replacement values
     * @param string $domain   optional Domain
     * @param string $locale   optional Locale
     * @param string $expected Expected translation
     *
     * @return void
     *
     * @dataProvider providerTransCsv
     * @dataProvider providerTransIni
     * @dataProvider providerTransJson
     * @dataProvider providerTransPhp
     */
    public function testTrans($i18n, $str, $args, $domain, $locale, $expected)
    {
        $translated = $i18n->trans($str, $args, $domain, $locale);
        self::assertSame($expected, $translated);
    }

    public function testParseExtCsvNoHandle()
    {
        $method = new \ReflectionMethod('bdk\I18n', 'parseExtCsv');
        $method->setAccessible(true);
        $return = $method->invoke(null, __DIR__ . '/bogusFile.csv');
        self::assertSame(array(), $return);
    }

    public static function providerTrans($ext)
    {
        $serverRequest = (new ServerRequest())
            ->withHeader('Accept-Language', 'en-US, fr');
        $i18n = new I18n($serverRequest, array(
            'domainFilepath' => array(
                'test' => __DIR__ . '/trans/t/{locale}.' . $ext,
            ),
            'filepath' => __DIR__ . '/trans/{domain}/{locale}.' . $ext,
        ));

        $tests = [
            'base lang fallback' => ['hello', 'howdy'],
            'region' => ['gulf.of.mexico', 'Gulf of America'],
            'specify en' => ['gulf.of.mexico', array(), null, 'en', 'Gulf of Mexico'],
            'specify es_MX' => ['gulf.of.mexico', array(), null, 'es_MX', 'Golfo de México'],
            'vars' => ['user.likes', array(
                'name' => 'Brad',
                'what' => 'cheese',
            ), 'Brad likes cheese'],
            'no translation' => ['no translated', 'no translated'],
            'test domain' => ['Big Mac', [], 'test', null, 'Le Big Mac'],
            'domain no lang' => ['test', [], 'no.such.domain', null, 'test'],
        ];
        $tests = \array_map(static function ($params) use ($i18n) {
            $expect = \array_pop($params);
            return \array_merge(
                [$i18n],
                \array_replace(['', array(), '', '', $expect], $params)
            );
        }, $tests);
        $testsRenamed = array();
        foreach ($tests as $k => $params) {
            $testsRenamed[$ext . ' ' . $k] = $params;
        }
        return $testsRenamed;
    }

    public static function providerTransCsv()
    {
        return self::providerTrans('csv');
    }

    public static function providerTransIni()
    {
        return self::providerTrans('ini');
    }

    public static function providerTransJson()
    {
        return self::providerTrans('json');
    }

    public static function providerTransPhp()
    {
        return self::providerTrans('php');
    }
}
