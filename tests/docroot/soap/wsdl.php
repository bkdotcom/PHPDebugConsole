<?php

header('Content-Type: text/xml; charset="utf-8"');

if ($serverRequest->getMethod() === 'GET') {
    \readfile(__DIR__ . '/SQLDataSoap.wsdl.xml');
    return;
}
$xml = (string) $serverRequest->getBody();
$dom = new \DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml);
$action = $dom->getElementsByTagName('Body')->item(0)->childNodes->item(0);

echo '<?' . 'xml version="1.0" encoding="UTF-8" standalone="no"?>';
?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.SoapClient.com/xml/SQLDataSoap.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
<?php
if (\strpos($action->textContent, 'faultMe') !== false) {
    ?>
    <SOAP-ENV:Body>
      <SOAP-ENV:Fault>
        <SOAP-ENV:faultcode>test</SOAP-ENV:faultcode>
        <SOAP-ENV:faultstring>This is a test</SOAP-ENV:faultstring>
      </SOAP-ENV:Fault>
    </SOAP-ENV:Body>
    <?php
} else {
    ?>
    <SOAP-ENV:Body>
      <mns:ProcessSRLResponse xmlns:mns="http://www.SoapClient.com/xml/SQLDataSoap.xsd" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
        <return xsi:type="xsd:string"/>
      </mns:ProcessSRLResponse>
    </SOAP-ENV:Body>
    <?php
}
?>
</SOAP-ENV:Envelope>
