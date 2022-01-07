<?php

namespace bdk\DebugTests\Utility;

use bdk\Debug\Utility\StringUtil;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Utility class
 */
class StringUtilTest extends TestCase
{
    /**
     * Test
     *
     * @return void
     */
    public function testIsBase64Encoded()
    {
        $base64Str = \base64_encode(\chunk_split(\str_repeat('zippity do dah', 50)));
        $this->assertTrue(StringUtil::isBase64Encoded($base64Str));

        $this->assertFalse(StringUtil::isBase64Encoded('I\'m just a bill.'));
        $this->assertFalse(StringUtil::isBase64Encoded('onRenderComplete'));
        $this->assertFalse(StringUtil::isBase64Encoded('/Users/jblow/not/base64/'));
    }

    public function testIsJson()
    {
        $this->assertFalse(StringUtil::isJson(null));
        $this->assertTrue(StringUtil::isJson('[42]'));
        $this->assertTrue(StringUtil::isJson('{"foo":"bar"}'));
    }

    /**
     * [testIsSerializedSafe description]
     *
     * @dataProvider providerIsSerializedSafe
     */
    public function testIsSerializedSafe($val, $expect)
    {
        $this->assertSame($expect, StringUtil::isSerializedSafe($val));
    }

    public function getXml()
    {
        $xml = <<<'EOD'
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <ns1:ProcessSRL>
      <SRLFile xsi:type="xsd:string">/xml/NEWS.SRI</SRLFile><RequestName xsi:type="xsd:string">yahoo</RequestName><key xsi:nil="true"/>
    </ns1:ProcessSRL>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
EOD;
        return $xml;
    }

    public function testIsXml()
    {
        $xml = $this->getXml();
        $this->assertFalse(StringUtil::isXml(null));
        $this->assertTrue(StringUtil::isXml($xml));
    }

    public function testPrettyXml()
    {
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
        $this->assertSame($expect, StringUtil::prettyXml($xml));
    }

    public function providerIsSerializedSafe()
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
                \serialize($this),
                false,
            ),
            // 5
            array(
                \serialize(array('notSafe' => $this)),
                false,
            ),
        );
    }
}
