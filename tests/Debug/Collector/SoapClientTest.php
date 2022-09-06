<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Collector\SoapClient;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Collector\SoapClient
 */
class SoapClientTest extends DebugTestFramework
{
    // protected $wsdl = 'http://www.SoapClient.com/xml/SQLDataSoap.wsdl';
    protected $wsdl = 'http://127.0.0.1:8080/soap/wsdl';
    protected static $client;

    protected function getClient()
    {
        if (self::$client) {
            return self::$client;
        }
        self::$client = new SoapClient($this->wsdl, array(
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 20,
        ));
        return self::$client;
    }

    public function testSoapCall()
    {
        try {
            $soapClient = $this->getClient();
            $soapClient->processSRL(
                '/xml/NEWS.SRI',
                'yahoo'
            );
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->markTestSkipped($message);
        }

        $logEntries = $this->debug->data->get('log');
        $logEntries = $this->helper->deObjectifyData($logEntries);

        $logEntriesExpect = array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'soap',
                    'http://127.0.0.1:8080/soap/SQLDataSRL',
                ),
                'meta' => array(
                    'channel' => 'general.Soap',
                    'icon' => 'fa fa-exchange',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    implode("%A\n", array(
                        'POST /soap/wsdl HTTP/1.1',
                        'Host: 127.0.0.1:8080',
                        'Connection: Keep-Alive',
                        'User-Agent: PHP-SOAP/' . PHP_VERSION,
                        'Content-Type: text/xml; charset=utf-8',
                        'SOAPAction: "http://127.0.0.1:8080/soap/SQLDataSRL"',
                        'Content-Length: %d',
                        '',
                    )),
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
                'method' => 'log',
                'args' => array(
                    'response headers',
                    \implode("%A\n", array(
                        'HTTP/1.1 200 OK',
                        // . 'Content-Length: %d' . "\r\n"
                        'Host: 127.0.0.1:8080',
                        // 'Date: %s',
                        'Connection: close',
                        'Content-Type: text/xml; charset="utf-8"',
                        '',
                        // . 'Set-Cookie: SessionId=%s;path=/;expires=%s GMT;Version=1; secure; HttpOnly' . "\r\n"
                        // . 'Server: SQLData-Server/%s Microsoft-HTTPAPI/2.0' . "\r\n"
                    )),
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
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
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

        $this->assertLogEntries($logEntriesExpect, $logEntries);
    }

    public function testDoRequest()
    {
        $request = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <ns1:ProcessSRL>
      <SRLFile xsi:type="xsd:string">/xml/NEWS.SRI</SRLFile>
      <RequestName xsi:type="xsd:string">yahoo</RequestName>
      <key xsi:nil="true"/>
    </ns1:ProcessSRL>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
';

        try {
            $soapClient = $this->getClient();
            $soapClient->__doRequest($request, $this->wsdl, '', SOAP_1_1);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->markTestSkipped($message);
        }

        $logEntries = $this->debug->data->get('log');
        $logEntries = $this->helper->deObjectifyData($logEntries);

        $logEntriesExpect = array(
            array(
                'method' => 'groupCollapsed',
                'args' => array('soap', 'ProcessSRL'),
                'meta' => array(
                    'channel' => 'general.Soap',
                    'icon' => 'fa fa-exchange',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    \implode("%A\n", array(
                        'POST /soap/wsdl HTTP/1.1',
                        'Host: 127.0.0.1:8080',
                        'Connection: Keep-Alive',
                        'User-Agent: PHP-SOAP/' . PHP_VERSION,
                        'Content-Type: text/xml; charset=utf-8',
                        'SOAPAction: ""',
                        'Content-Length: %d',
                        '',
                    )),
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
                'method' => 'log',
                'args' => array(
                    'response headers',
                    implode("%A\n", array(
                        'HTTP/1.1 200 OK',
                        'Host: 127.0.0.1:8080',
                        // . 'Content-Length: 664' . "\r\n"
                        // . 'Set-Cookie: SessionId=%s;path=/;expires=%s GMT;Version=1; secure; HttpOnly' . "\r\n"
                        // . 'Server: SQLData-Server/%s Microsoft-HTTPAPI/2.0' . "\r\n"
                        // 'Date: %s',
                        'Connection: close',
                        'Content-Type: text/xml; charset="utf-8"',
                        '',
                    )),
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
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
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

        $this->assertLogEntries($logEntriesExpect, $logEntries);
    }
}
