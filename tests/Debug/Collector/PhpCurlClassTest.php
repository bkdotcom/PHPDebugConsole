<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Collector\PhpCurlClass;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for PhpCurlClass
 *
 * @covers \bdk\Debug\Collector\PhpCurlClass
 */
class PhpCurlClassTest extends DebugTestFramework
{
    protected $baseUrl = 'http://127.0.0.1:8080';

    public function testPost()
    {
        $curl = new PhpCurlClass(array(
            'inclInfo' => true,
            'inclOptions' => true,
            'inclRequestBody' => true,
            'inclResponseBody' => true,
            'verbose' => true,
        ));
        $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $response = $curl->post($this->baseUrl . '/echo', array(
            'username' => 'myusername',
            'password' => 'mypassword',
        ));
        $responseData = array(
            'queryParams' => array(),
            'headers' => \implode("\r\n", array(
                'POST /echo HTTP/1.1',
                'Host: 127.0.0.1:8080',
                'User-Agent: ' . $userAgent,
                'Accept: */*',
                'Content-Length: 39',
                'Content-Type: application/x-www-form-urlencoded',
            )),
            'cookieParams' => array(),
            'body' => 'username=myusername&password=mypassword',
        );
        self::assertSame($responseData, (array) $response);
        $htmlExpect = '<li class="m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                <div class="group-header">%sCurl(%sPOST%s' . $this->baseUrl . '/echo%s)</span></div>
                <ul class="group-body">
                    <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                        <ul class="array-inner list-unstyled">
                            %A
                            <li><span class="t_key">CURLOPT_POST</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                            <li><span class="t_key">CURLOPT_POSTFIELDS</span><span class="t_operator">=&gt;</span><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                                <ul class="array-inner list-unstyled">
                                <li><span class="t_key">username</span><span class="t_operator">=&gt;</span><span class="t_string">myusername</span></li>
                                <li><span class="t_key">password</span><span class="t_operator">=&gt;</span><span class="t_string">█████████</span></li>
                                </ul><span class="t_punct">)</span></span></li>
                            %A
                            <li><span class="t_key">CURLOPT_VERBOSE</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                        </ul><span class="t_punct">)</span></span></li>
                    <li class="m_log" data-channel="general.Curl">%srequest headers</span> = <span class="t_string">POST /echo HTTP/1.1%A</li>
                    <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">request body</span> = <span class="string-encoded tabs-container" data-type-more="form">
                        <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">form</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">parsed</a></nav>
                        <div class="tab-1 tab-pane" role="tabpanel"><span class="no-quotes t_string">username=myusername&amp;password=█████████</span></div>
                        <div class="active tab-2 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                        <ul class="array-inner list-unstyled">
                            <li><span class="t_key">username</span><span class="t_operator">=&gt;</span><span class="t_string">myusername</span></li>
                            <li><span class="t_key">password</span><span class="t_operator">=&gt;</span><span class="t_string">█████████</span></li>
                        </ul><span class="t_punct">)</span></span></div>
                        </span></li>
                    <li class="m_time" data-channel="general.Curl"><span class="no-quotes t_string">time: %f %s</span></li>
                    <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 200 OK%a</span></li>
                    <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response body</span> = <span class="string-encoded tabs-container" data-type-more="json">
                        <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">json</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">parsed</a></nav>
                        <div class="tab-1 tab-pane" role="tabpanel"><span class="value-container" data-type="string"><span class="prettified">(prettified)</span> <span class="highlight language-json no-quotes t_string">{
                            &quot;queryParams&quot;: [],
                            &quot;headers&quot;: &quot;POST \/echo HTTP\/1.1\r\nHost: 127.0.0.1:8080\r\nUser-Agent: %s\r\nAccept: *\/*\r\nContent-Length: 39\r\nContent-Type: application\/x-www-form-urlencoded&quot;,
                            &quot;cookieParams&quot;: [],
                            &quot;body&quot;: &quot;username=myusername&amp;password=█████████&quot;
                            }</span></span></div>
                        <div class="active tab-2 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                        <ul class="array-inner list-unstyled">
                        <li><span class="t_key">queryParams</span><span class="t_operator">=&gt;</span><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></li>
                        <li><span class="t_key">headers</span><span class="t_operator">=&gt;</span><span class="t_string">POST /echo HTTP/1.1<span class="ws_r"></span><span class="ws_n"></span>
                        Host: 127.0.0.1:8080<span class="ws_r"></span><span class="ws_n"></span>
                        User-Agent: ' . $userAgent . '<span class="ws_r"></span><span class="ws_n"></span>
                        Accept: */*<span class="ws_r"></span><span class="ws_n"></span>
                        Content-Length: 39<span class="ws_r"></span><span class="ws_n"></span>
                        Content-Type: application/x-www-form-urlencoded</span></li>
                        <li><span class="t_key">cookieParams</span><span class="t_operator">=&gt;</span><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></li>
                        <li><span class="t_key">body</span><span class="t_operator">=&gt;</span><span class="t_string">username=myusername&amp;password=█████████</span></li>
                        </ul><span class="t_punct">)</span></span></div>
                        </span></li>
                    <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">info</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                        <ul class="array-inner list-unstyled">
                        %A
                        </ul><span class="t_punct">)</span></span></li>
                    <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">verbose</span> = <span class="t_string">%A</span></li>
                </ul>
            </li>';
        $this->outputTest(array(
            'html' => static function ($htmlActual) use ($htmlExpect) {
                self::assertSame(3, \preg_match_all('/password=█████████/', $htmlActual), 'Did not find redacted password three times');
                $htmlActual = \preg_replace('#^\s+#m', '', $htmlActual);
                $htmlExpect = \preg_replace('#^\s+#m', '', $htmlExpect);
                // \bdk\Debug::varDump('expect', $htmlExpect);
                // \bdk\Debug::varDump('actual', $htmlActual);
                self::assertStringMatchesFormat('%A' . $htmlExpect . '%A', $htmlActual);
            },
        ));
    }

    public function testHead()
    {
        $curl = new PhpCurlClass(array(
            // 'inclInfo' => true,
            'inclOptions' => true,
            'inclRequestBody' => true,
            'inclResponseBody' => true,
            // 'verbose' => true,
        ), $this->debug);
        // $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $response = $curl->head($this->baseUrl . '/echo');
        self::assertSame('', $response);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurl(%sHEAD%s' . $this->baseUrl . '/echo%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                %A
                                <li><span class="t_key">CURLOPT_NOBODY</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                %A
                                <li><span class="t_key">CURLOPT_URL</span><span class="t_operator">=&gt;</span><span class="t_string">http://127.0.0.1:8080/echo</span></li>
                                %A
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_log" data-channel="general.Curl">%srequest headers</span> = <span class="t_string">HEAD /echo HTTP/1.1%A</li>
                        <li class="m_time" data-channel="general.Curl"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 200 OK%a</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response body</span> = <span class="t_null">null</span></li>
                    </ul>
                </li>',
        ));
    }

    public function testRedirect()
    {
        $curl = new PhpCurlClass(array(
            // 'inclInfo' => true,
            'inclOptions' => true,
            'inclRequestBody' => true,
            'inclResponseBody' => true,
            // 'verbose' => true,
        ), $this->debug);
        $curl->setFollowLocation();
        $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $uri = '/echo?redirect=1';
        $curl->get($this->baseUrl . $uri);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurl(%sGET%s' . $this->baseUrl . '/echo%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                %A
                                <li><span class="t_key">CURLOPT_FOLLOWLOCATION</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                %A
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_log" data-channel="general.Curl">%srequest headers</span> = <span class="t_string">GET /echo HTTP/1.1%A</li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">Redirect(s)</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                            <li><span class="t_int t_key">0</span><span class="t_operator">=&gt;</span><span class="t_string"> http://127.0.0.1:8080/echo</span></li>
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_time" data-channel="general.Curl"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 302 Found%a</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response body</span> = <span class="string-encoded tabs-container" data-type-more="json">
                            <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">json</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">parsed</a></nav>
                            <div class="tab-1 tab-pane" role="tabpanel"><span class="value-container" data-type="string"><span class="prettified">(prettified)</span> <span class="highlight language-json no-quotes t_string">{
                                &quot;queryParams&quot;: [],
                                &quot;headers&quot;: &quot;GET \/echo HTTP\/1.1\r\nHost: 127.0.0.1:8080\r\nUser-Agent: %s\r\nAccept: *\/*&quot;,
                                &quot;cookieParams&quot;: [],
                                &quot;body&quot;: &quot;&quot;
                                }</span></span></div>
                            <div class="active tab-2 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                            <li><span class="t_key">queryParams</span><span class="t_operator">=&gt;</span><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></li>
                            <li><span class="t_key">headers</span><span class="t_operator">=&gt;</span><span class="t_string">GET /echo HTTP/1.1<span class="ws_r"></span><span class="ws_n"></span>
                            Host: 127.0.0.1:8080<span class="ws_r"></span><span class="ws_n"></span>
                            User-Agent: ' . $userAgent . '<span class="ws_r"></span><span class="ws_n"></span>
                            Accept: */*</span></li>
                            <li><span class="t_key">cookieParams</span><span class="t_operator">=&gt;</span><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></li>
                            <li><span class="t_key">body</span><span class="t_operator">=&gt;</span><span class="t_string"></span></li>
                            </ul><span class="t_punct">)</span></span></div>
                            </span></li>
                    </ul>
                </li>',
        ));
    }

    public function testError()
    {
        $curl = new PhpCurlClass(array(
            // 'inclInfo' => true,
            'inclOptions' => true,
            'inclRequestBody' => true,
            'inclResponseBody' => true,
            // 'verbose' => true,
        ), $this->debug);
        // $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $curl->get($this->baseUrl . '/echo?headers[]=HTTP/1.1');
        $this->outputTest(array(
            'html' => '<li class="expanded m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurl(%sGET%s' . $this->baseUrl . '/echo%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                %A
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_log" data-channel="general.Curl">%srequest headers</span> = <span class="t_string">GET /echo%s HTTP/1.1%A</li>
                        <li class="m_warn" data-channel="general.Curl" data-detect-files="true" data-file="' . __FILE__ . '" data-line="%d"><span class="t_int">%d</span>, <span class="t_string">%SUnsupported %S in response</span></li>
                        <li class="m_time" data-channel="general.Curl"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response headers</span> = <span class="t_string">%A</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response body</span> = <span class="t_null">null</span></li>
                    </ul>
                </li>',
        ));
    }
}
