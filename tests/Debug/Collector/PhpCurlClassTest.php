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
            'inclResponseBody' => true,
            'verbose' => true,
        ));
        $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $response = $curl->post($this->baseUrl . '/echo', array(
            'username' => 'myusername',
            'password' => 'mypassword',
            // 'file' => '@'.__FILE__,
        ));
        // $this->helper->stderr('response', $response);
        // $this->assertTrue(true);
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
        $this->assertSame($responseData, (array) $response);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurl(%sPOST%s' . $this->baseUrl . '/echo%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">CURLINFO_HEADER_OUT</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="false">false</span></li>
                                <li><span class="t_key">CURLOPT_HEADERFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_NOPROGRESS</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="false">false</span></li>
                                <li><span class="t_key">CURLOPT_POST</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_POSTFIELDS</span><span class="t_operator">=&gt;</span><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                                    <ul class="array-inner list-unstyled">
                                    <li><span class="t_key">username</span><span class="t_operator">=&gt;</span><span class="t_string">myusername</span></li>
                                    <li><span class="t_key">password</span><span class="t_operator">=&gt;</span><span class="t_string">mypassword</span></li>
                                    </ul><span class="t_punct">)</span></span></li>
                                <li><span class="t_key">CURLOPT_PROGRESSFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_RETURNTRANSFER</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_STDERR</span><span class="t_operator">=&gt;</span><span class="t_resource">Resource id #%d: stream</span></li>
                                <li><span class="t_key">CURLOPT_TIMEOUT</span><span class="t_operator">=&gt;</span><span class="t_int">30</span></li>
                                <li><span class="t_key">CURLOPT_URL</span><span class="t_operator">=&gt;</span><span class="t_string">http://127.0.0.1:8080/echo</span></li>
                                <li><span class="t_key">CURLOPT_USERAGENT</span><span class="t_operator">=&gt;</span><span class="t_string">' . $userAgent . '</span></li>
                                <li><span class="t_key">CURLOPT_VERBOSE</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_log" data-channel="general.Curl">%srequest headers</span> = <span class="t_string">POST /echo HTTP/1.1%A</li>
                        <li class="m_time" data-channel="general.Curl"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 200 OK%a</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response body</span> = <span class="string-encoded tabs-container" data-type-more="json">
                            <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">json</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">decoded</a></nav>
                            <div class="tab-1 tab-pane" role="tabpanel"><span class="highlight language-json no-quotes t_string">{<span class="ws_n"></span>
                            &quot;queryParams&quot;: [],<span class="ws_n"></span>
                            &quot;headers&quot;: &quot;POST \/echo HTTP\/1.1\r\nHost: 127.0.0.1:8080\r\nUser-Agent: %s\r\nAccept: *\/*\r\nContent-Length: 39\r\nContent-Type: application\/x-www-form-urlencoded&quot;,<span class="ws_n"></span>
                            &quot;cookieParams&quot;: [],<span class="ws_n"></span>
                            &quot;body&quot;: &quot;username=myusername&amp;password=█████████&quot;<span class="ws_n"></span>
                            }</span></div>
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
                            <ul class="array-inner list-unstyled">%A</ul><span class="t_punct">)</span></span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">verbose</span> = <span class="t_string">%A</span></li>
                    </ul>
                </li>',
        ));
    }

    public function testHead()
    {
        $curl = new PhpCurlClass(array(
            // 'inclInfo' => true,
            'inclResponseBody' => true,
            // 'verbose' => true,
        ), $this->debug);
        $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $response = $curl->head($this->baseUrl . '/echo');
        $this->assertSame('', $response);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurl(%sHEAD%s' . $this->baseUrl . '/echo%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">CURLINFO_HEADER_OUT</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_CUSTOMREQUEST</span><span class="t_operator">=&gt;</span><span class="t_string">HEAD</span></li>
                                <li><span class="t_key">CURLOPT_HEADERFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_NOBODY</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_NOPROGRESS</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="false">false</span></li>
                                <li><span class="t_key">CURLOPT_PROGRESSFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_RETURNTRANSFER</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_TIMEOUT</span><span class="t_operator">=&gt;</span><span class="t_int">30</span></li>
                                <li><span class="t_key">CURLOPT_URL</span><span class="t_operator">=&gt;</span><span class="t_string">http://127.0.0.1:8080/echo</span></li>
                                <li><span class="t_key">CURLOPT_USERAGENT</span><span class="t_operator">=&gt;</span><span class="t_string">' . $userAgent . '</span></li>
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
            'inclResponseBody' => true,
            // 'verbose' => true,
        ), $this->debug);
        $curl->setFollowLocation();
        $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $uri = '/echo?redirect=1';
        $response = $curl->get($this->baseUrl . $uri);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurl(%sGET%s' . $this->baseUrl . '/echo%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">CURLINFO_HEADER_OUT</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_CUSTOMREQUEST</span><span class="t_operator">=&gt;</span><span class="t_string">GET</span></li>
                                <li><span class="t_key">CURLOPT_FOLLOWLOCATION</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_HEADERFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_HTTPGET</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_NOPROGRESS</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="false">false</span></li>
                                <li><span class="t_key">CURLOPT_PROGRESSFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_RETURNTRANSFER</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_TIMEOUT</span><span class="t_operator">=&gt;</span><span class="t_int">30</span></li>
                                <li><span class="t_key">CURLOPT_URL</span><span class="t_operator">=&gt;</span><span class="t_string">http://127.0.0.1:8080' . $uri . '</span></li>
                                <li><span class="t_key">CURLOPT_USERAGENT</span><span class="t_operator">=&gt;</span><span class="t_string">' . $userAgent . '</span></li>
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_log" data-channel="general.Curl">%srequest headers</span> = <span class="t_string">GET /echo HTTP/1.1%A</li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">Redirect(s)</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                            <li><span class="t_int t_key">0</span><span class="t_operator">=&gt;</span><span class="t_string"> http://127.0.0.1:8080/echo</span></li>
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_time" data-channel="general.Curl"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 302 Found%a</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response body</span> = <span class="string-encoded tabs-container" data-type-more="json">
                            <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">json</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">decoded</a></nav>
                            <div class="tab-1 tab-pane" role="tabpanel"><span class="highlight language-json no-quotes t_string">{<span class="ws_n"></span>
                            &quot;queryParams&quot;: [],<span class="ws_n"></span>
                            &quot;headers&quot;: &quot;GET \/echo HTTP\/1.1\r\nHost: 127.0.0.1:8080\r\nUser-Agent: %s\r\nAccept: *\/*&quot;,<span class="ws_n"></span>
                            &quot;cookieParams&quot;: [],<span class="ws_n"></span>
                            &quot;body&quot;: &quot;&quot;<span class="ws_n"></span>
                            }</span></div>
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
            'inclResponseBody' => true,
            // 'verbose' => true,
        ), $this->debug);
        $userAgent = $curl->getOpt(CURLOPT_USERAGENT);
        $response = $curl->get($this->baseUrl . '/echo?headers[]=HTTP/1.1');
        $this->outputTest(array(
            'html' => '<li class="expanded m_group" data-channel="general.Curl" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurl(%sGET%s' . $this->baseUrl . '/echo%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">options</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">CURLINFO_HEADER_OUT</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_CUSTOMREQUEST</span><span class="t_operator">=&gt;</span><span class="t_string">GET</span></li>
                                <li><span class="t_key">CURLOPT_HEADERFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_HTTPGET</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_NOPROGRESS</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="false">false</span></li>
                                <li><span class="t_key">CURLOPT_PROGRESSFUNCTION</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                                    %A</li>
                                <li><span class="t_key">CURLOPT_RETURNTRANSFER</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
                                <li><span class="t_key">CURLOPT_TIMEOUT</span><span class="t_operator">=&gt;</span><span class="t_int">30</span></li>
                                <li><span class="t_key">CURLOPT_URL</span><span class="t_operator">=&gt;</span><span class="t_string">http://127.0.0.1:8080/echo?headers[]=HTTP/1.1</span></li>
                                <li><span class="t_key">CURLOPT_USERAGENT</span><span class="t_operator">=&gt;</span><span class="t_string">' . $userAgent . '</span></li>
                            </ul><span class="t_punct">)</span></span></li>
                        <li class="m_log" data-channel="general.Curl">%srequest headers</span> = <span class="t_string">GET /echo%s HTTP/1.1%A</li>
                        <li class="m_warn" data-channel="general.Curl" data-detect-files="true" data-file="' . __FILE__ . '" data-line="%d"><span class="t_int">%d</span>, <span class="t_string">Unsupported protocol (CURLE_UNSUPPORTED_PROTOCOL): Unsupported HTTP version in response</span></li>
                        <li class="m_time" data-channel="general.Curl"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response headers</span> = <span class="t_string">%A</span></li>
                        <li class="m_log" data-channel="general.Curl"><span class="no-quotes t_string">response body</span> = <span class="t_null">null</span></li>
                    </ul>
                </li>',
        ));
    }
}
