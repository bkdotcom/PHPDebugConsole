<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\StringUtil;
use bdk\Test\Debug\Fixture\Test2Base;
use bdk\Test\Debug\Fixture\TestObj;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Utility class
 *
 * @covers \bdk\Debug\Utility\StringUtil
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class StringUtilTest extends TestCase
{
    use ExpectExceptionTrait;

    /**
     * Test compare method
     *
     * @param mixed       $valA     Value A
     * @param mixed       $valB     Value B
     * @param string|null $operator comparison operator or function
     * @param bool|int    $expect   expected return val
     *
     * @return void
     *
     * @dataProvider providerCompare
     */
    public function testCompare($valA, $valB, $operator, $expect)
    {
        $ret = $operator !== null
            ? StringUtil::compare($valA, $valB, $operator)
            : StringUtil::compare($valA, $valB);
        self::assertSame($expect, $ret);
    }

    public function testCompareException()
    {
        self::expectException('InvalidArgumentException');
        self::expectExceptionMessage('bdk\Debug\Utility\StringUtil::compare - Invalid operator passed');
        StringUtil::compare('1', '2', '3');
    }

    public function testInterpolate()
    {
        $message = '{user.name} was {where}. {obj} {null} {notReplaced}';
        $values = array(
            'null' => null,
            'obj' => new TestObj('toStringVal'),
            'user' => array(
                'name' => 'Brad',
            ),
            'where' => 'here',
        );
        $placeholders = array();
        $return = StringUtil::interpolate($message, $values, $placeholders);
        self::assertSame('Brad was here. toStringVal  {notReplaced}', $return);
        self::assertSame(array('user.name','where','obj','null','notReplaced'), $placeholders);

        $message = new TestObj($message);
        $return = StringUtil::interpolate($message, (object) $values, $placeholders);
        self::assertSame('Brad was here. toStringVal {null} {notReplaced}', $return);
        self::assertSame(array('user.name','where','obj','null','notReplaced'), $placeholders);
    }

    public function testInterpolateInvalidMessage()
    {
        self::expectException('InvalidArgumentException');
        self::expectExceptionMessage('bdk\Debug\Utility::interpolate()\'s $message expects string or Stringable object. boolean provided.');
        StringUtil::interpolate(false, 'string');
    }

    public function testInterpolateInvalidContext()
    {
        self::expectException('InvalidArgumentException');
        self::expectExceptionMessage('bdk\Debug\Utility::interpolate()\'s $context expects array or object for $context. string provided.');
        StringUtil::interpolate('message', 'string');
    }

    /**
     * Test
     *
     * @param string $val    value to test
     * @param bool   $expect expected return val
     *
     * @return void
     *
     * @dataProvider providerIsBase64Encoded
     */
    public function testIsBase64Encoded($val, $expect)
    {
        self::assertSame($expect, StringUtil::isBase64Encoded($val));
    }

    public function testIsJson()
    {
        self::assertFalse(StringUtil::isJson(null));
        // tis json, but it's not an obj or list
        self::assertFalse(StringUtil::isJson('"string"'));
        self::assertTrue(StringUtil::isJson('[42]'));
        self::assertTrue(StringUtil::isJson('{"foo":"bar"}'));
    }

    /**
     * @param string $val    value to test
     * @param bool   $expect expected return val
     *
     * @return void
     *
     * @dataProvider providerIsSerializedSafe
     */
    public function testIsSerializedSafe($val, $expect)
    {
        self::assertSame($expect, StringUtil::isSerializedSafe($val));
    }

    public function getXml()
    {
        return <<<'EOD'
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <ns1:ProcessSRL>
      <SRLFile xsi:type="xsd:string">/xml/NEWS.SRI</SRLFile><RequestName xsi:type="xsd:string">yahoo</RequestName><key xsi:nil="true"/>
    </ns1:ProcessSRL>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
EOD;
    }

    public function testIsXml()
    {
        $xml = self::getXml();
        self::assertFalse(StringUtil::isXml(null));
        self::assertTrue(StringUtil::isXml($xml));
    }

    public function testPrettyJson()
    {
        $data = array('foo','bar');
        self::assertSame(
            \json_encode($data, JSON_PRETTY_PRINT),
            StringUtil::prettyJson(\json_encode($data))
        );
    }

    public function testPrettySql()
    {
        $sql = 'SELECT * FROM table WHERE col = "val"';
        self::assertEquals(
            \str_replace('·', ' ', 'SELECT·
  *·
FROM·
  table·
WHERE·
  col = "val"'),
            StringUtil::prettySql($sql)
        );
    }

    public function testPrettyXml()
    {
        \bdk\Test\Debug\Helper::setProp('bdk\Debug\Utility\StringUtil', 'domDocument', null);

        self::assertSame('', StringUtil::prettyXml(''));

        $xml = $this->getXml();
        $expect = <<<'EOD'
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <ns1:ProcessSRL>
      <SRLFile xsi:type="xsd:string">/xml/NEWS.SRI</SRLFile>
      <RequestName xsi:type="xsd:string">yahoo</RequestName>
      <key xsi:nil="true"/>
    </ns1:ProcessSRL>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>

EOD;
        self::assertSame($expect, StringUtil::prettyXml($xml));
    }

    public static function providerCompare()
    {
        // =, ==, ===, <>, != !== >= <= > <
        return array(
            'default1' => array('foo', 'bar', null,  1),
            'default2' => array('foo', 'foo', null,  0),
            'default3' => array('foo', 0,     null,  1),
            'default4' => array('foo', 'Foo', null,  1),
            'default5' => array(0,     'foo', null,  -1),
            'default6' => array(42,    '42',  null,  0),
            'default7' => array('img2', 'img10', null, -1),
            'default8' => array('img10', 'img2', null, 1),

            'strcmp1' => array(42, 42, 'strcmp', 0),
            'strcmp2' => array('foo', 'Foo', 'strcmp', 1),
            'strcmp3' => array('img2', 'img10', 'strcmp', 1),
            'strcmp4' => array('img10', 'img2', 'strcmp', -1),

            'strcasecmp1' => array('foo', 'Foo', 'strcasecmp', 0),

            'eq1' => array('foo', 'bar', '==',  false),
            'eq2' => array('foo', 'foo', '==',  true),
            'eq3' => array('foo', 0,     '==',  false),
            'eq4' => array(0,     'foo', '==',  false),
            'eq5' => array(42,    '42',  '==',  true),

            'eqs1' => array(42,    '42',  '===', false),
            'eqs2' => array(42,    42,    '===', true),

            'ne1' => array('foo', 'bar', '<>',  true),
            'ne2' => array(42,    '42',  '<>',  false),
            'ne3' => array('foo', 'bar', '!=',  true),
            'ne4' => array(42,     42,   '!=',  false),

            'nes1' => array('foo', 'foo', '!==', false),
            'nes2' => array(42,    '42',  '!==', true),
            'nes3' => array('42',  '42',  '!==', false),

            'gte1' => array('foo', 'bar', '>=',  true),
            'gte2' => array('bar', 'foo', '>=',  false),
            'gte3' => array('9',   '10',  '>=',  false),
            'gte4' => array('10',  '9',   '>=',  true),
            'gte5' => array('42',  '42',  '>=',  true),

            'lte1' => array('foo', 'bar', '<=',  false),
            'lte2' => array('bar', 'foo', '<=',  true),
            'lte3' => array('9',   '10',  '<=',  true),
            'lte4' => array('10',  '9',   '<=',  false),
            'lte5' => array('42',  '42',  '<=',  true),

            'gt1' => array('foo', 'bar', '>',   true),
            'gt2' => array('bar', 'foo', '>',   false),
            'gt3' => array('9',   '10',  '>',   false),
            'gt4' => array('10',  '9',   '>',   true),
            'gt5' => array('42',  '42',  '>',   false),

            'lt1' => array('foo', 'bar', '<',   false),
            'lt2' => array('bar', 'foo', '<',   true),
            'lt3' => array('9',   '10',  '<',   true),
            'lt4' => array('10',  '9',   '<',   false),
            'lt5' => array('42',  '42',  '<',   false),
        );
    }

    public static function providerIsBase64Encoded()
    {
        $base64Str = \base64_encode(\chunk_split(\str_repeat('zippity do dah', 50)));

        return array(
            'notAString' => array(123, false),
            'chunkSplit' => array($base64Str, true),
            'hex' => array('deadbeef0ba5eba110b01dface', false),
            'mod4fail' => array('onRenderComplet', false),
            'endsWith=' => array('onRenderComplet=', true),
            'zeroLen' => array('==', false),
            'stats' => array('I\'m just a bill.', false),
            'stats2' => array('onRenderComplete', false),
            'stats3' => array('/Users/jblow/not/base64/', false),
        );
    }

    public static function providerIsSerializedSafe()
    {
        return array(
            // 0
            array(
                42,
                false,
            ),
            // 1
            array(
                \serialize('foo'),
                true,
            ),
            // 2
            array(
                \serialize(array('foo' => 'bar')),
                true,
            ),
            // 3
            array(
                \serialize((object) array('foo' => 'bar')),
                true,
            ),
            // 4
            array(
                \serialize(new Test2Base()),
                false,
            ),
            // 5
            array(
                \serialize(array('notSafe' => new Test2Base())),
                false,
            ),
        );
    }
}
