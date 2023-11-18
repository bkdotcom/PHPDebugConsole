<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Utility\Reflection;
use bdk\HttpMessage\Utility\ContentType;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Plugin\Prettify
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class PrettifyTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    /**
     * @doesNotPerformAssertions
     */
    public function testBootstrap()
    {
        $this->debug->removePlugin($this->debug->getPlugin('prettify'));
        $this->debug->addPlugin(new \bdk\Debug\Plugin\Prettify(), 'prettify');
    }

    public function testPrettify()
    {
        Reflection::propSet($this->debug->getPlugin('prettify'), 'highlightAdded', false);

        $foo = $this->debug->prettify('foo', 'unknown');
        self::assertSame('foo', $foo);

        $html = $this->debug->prettify('<html><title>test</title></html>', 'text/html');
        self::assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => null,
                'value' => '<html><title>test</title></html>',
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-markup',
                    ),
                ),
                'addQuotes' => false,
                'brief' => false,
                'contentType' => 'text/html',
                'prettified' => false,
                'prettifiedTag' => false,
                'visualWhiteSpace' => false,
            )),
            $html
        );

        $data = array('foo', 'bar');
        $json = $this->debug->prettify(\json_encode($data), 'application/json');
        self::assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => 'json',
                'value' => \json_encode($data, JSON_PRETTY_PRINT),
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-json',
                    ),
                ),
                'addQuotes' => false,
                'brief' => false,
                'contentType' => 'application/json',
                'prettified' => true,
                'prettifiedTag' => true,
                'visualWhiteSpace' => false,
                'valueDecoded' => $data,
            )),
            $json
        );

        $sql = $this->debug->prettify('SELECT * FROM table WHERE col = "val"', ContentType::SQL);
        self::assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => null,
                'value' => \str_replace('·', ' ', 'SELECT·
  *·
FROM·
  table·
WHERE·
  col = "val"'),
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-sql',
                    ),
                ),
                'addQuotes' => false,
                'brief' => false,
                'contentType' => 'application/sql',
                'prettified' => true,
                'prettifiedTag' => true,
                'visualWhiteSpace' => false,
            )),
            $sql
        );

        $xmlExpect = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://www.SoapClient.com/xml/SQLDataSoap.wsdl" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <mns:ProcessSRLResponse xmlns:mns="http://www.SoapClient.com/xml/SQLDataSoap.xsd" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
      <return xsi:type="xsd:string"/>
    </mns:ProcessSRLResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
';
        $xml = $this->debug->prettify(\str_replace("\n", '', $xmlExpect), 'application/xml');
        self::assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => null,
                'value' => $xmlExpect,
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-xml',
                    ),
                ),
                'addQuotes' => false,
                'brief' => false,
                'contentType' => 'application/xml',
                'prettified' => true,
                'prettifiedTag' => true,
                'visualWhiteSpace' => false,
            )),
            $xml
        );

        self::assertTrue(Reflection::propGet($this->debug->getPlugin('prettify'), 'highlightAdded'));
    }
}
