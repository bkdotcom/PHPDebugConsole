<?php

namespace bdk\DebugTests\Collector;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Collector\SoapClient;
use bdk\Debug\LogEntry;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class SoapClientTest extends DebugTestFramework
{
    public function testSoapCall()
    {
        $wsdl = 'http://www.SoapClient.com/xml/SQLDataSoap.wsdl';
        $soapClient = new SoapClient($wsdl);

        $soapClient->processSRL(
            '/xml/NEWS.SRI',
            'yahoo'
        );

        $logEntries = $this->debug->data->get('log');
        $logEntries = \array_slice($logEntries, -8);
        $logEntries = \array_map(function (LogEntry $logEntry) {
            return $this->logEntryToArray($logEntry);
        }, $logEntries);

        $logEntriesExpect = array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'soap',
                    'http://soapclient.com/SQLDataSRL',
                ),
                'meta' => array(
                    'channel' => 'general.Soap',
                    'icon' => 'fa fa-exchange',
                ),
            ),
            array(
                'asString' => true,
                'method' => 'log',
                'args' => array(
                    'request headers',
                    'POST /xml/SQLDataSoap.wsdl HTTP/1.1' . "\r\n"
                        . 'Host: www.soapclient.com' . "\r\n"
                        . 'Connection: Keep-Alive' . "\r\n"
                        . 'User-Agent: PHP-SOAP/' . PHP_VERSION . "\r\n"
                        . 'Content-Type: text/xml; charset=utf-8' . "\r\n"
                        . 'SOAPAction: "http://soapclient.com/SQLDataSRL"' . "\r\n"
                        . 'Content-Length: %d' . "\r\n"
                        . '' . "\r\n",
                ),
                'meta' => array(
                    'channel' => 'general.Soap',
                    'redact' => true,
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request body',
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-xml'),
                        ),
                        'debug' => Abstracter::ABSTRACTION,
                        'type' => Abstracter::TYPE_STRING,
                        'value' => '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <ns1:ProcessSRL>
      <SRLFile xsi:type="xsd:string">/xml/NEWS.SRI</SRLFile>
      <RequestName xsi:type="xsd:string">yahoo</RequestName>
      <key xsi:nil="true"/>
    </ns1:ProcessSRL>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
',
                        'visualWhiteSpace' => false,
                    ),
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => array('no-indent'),
                    ),
                    'channel' => 'general.Soap',
                    'redact' => true,
                ),
            ),
            array(
                'asString' => true,
                'method' => 'log',
                'args' => array(
                    'response headers',
                    'HTTP/1.1 200 OK' . "\r\n"
                        . 'Content-Length: %d' . "\r\n"
                        . 'Content-Type: text/xml; charset="utf-8"' . "\r\n"
                        . 'Set-Cookie: SessionId=%s;path=/;expires=%s GMT;Version=1; secure; HttpOnly' . "\r\n"
                        . 'Server: SQLData-Server/%s Microsoft-HTTPAPI/2.0' . "\r\n"
                        . 'Date: %s GMT' . "\r\n"
                        . '',
                ),
                'meta' => array(
                    'channel' => 'general.Soap',
                    'redact' => true,
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'response body',
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-xml'),
                        ),
                        'debug' => Abstracter::ABSTRACTION,
                        'type' => Abstracter::TYPE_STRING,
                        'value' => '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://www.SoapClient.com/xml/SQLDataSoap.wsdl" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <mns:ProcessSRLResponse xmlns:mns="http://www.SoapClient.com/xml/SQLDataSoap.xsd" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
      <return xsi:type="xsd:string"/>
    </mns:ProcessSRLResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
',
                        'visualWhiteSpace' => false,
                    ),
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => array('no-indent'),
                    ),
                    'channel' => 'general.Soap',
                    'redact' => true,
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'general.Soap',
                ),
            ),
        );

        foreach ($logEntriesExpect as $i => $valsExpect) {
            $asString = !empty($valsExpect['asString']);
            unset($valsExpect['asString']);
            if ($asString) {
                $this->assertStringMatchesFormat(\json_encode($valsExpect), \json_encode($logEntries[$i]));
                continue;
            }
            $this->assertSame($valsExpect, $logEntries[$i]);
        }
    }
}
