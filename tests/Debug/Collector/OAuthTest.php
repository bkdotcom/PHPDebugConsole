<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Collector\OAuth;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Collector\OAuth
 */
class OAuthTest extends DebugTestFramework
{
    public static $consumerKey = 'key';
    public static $consumerSecret = 'secret';
    public static $accessTokenUrl = 'http://127.0.0.1:8080/oauth/access_token';
    public static $requestTokenUrl = 'http://127.0.0.1:8080/oauth/request_token';
    public static $oauthEndpoint = 'http://127.0.0.1:8080/oauth/echo';
    public static $token;
    public static $tokenSecret;
    private static $oauthDebug;

    public static function setUpBeforeClass(): void
    {
        self::$oauthDebug = new OAuth(self::$consumerKey, self::$consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
    }

    public function testGetAccessToken()
    {
        $response = self::$oauthDebug->getAccessToken(self::$accessTokenUrl, '', '', OAUTH_HTTP_METHOD_POST);
        $this->assertSame(array(
            'oauth_token' => 'access_token',
            'oauth_token_secret' => 'access_token_secret',
        ), $response);
        $this->assertLogEntries(array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'getAccessToken',
                    'POST',
                    self::$accessTokenUrl,
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'time',
                'args' => array(
                    'time: %f ms',
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'OAuth Parameters',
                    array(
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '%s',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'strlen' => null,
                            'type' => Abstracter::TYPE_STRING,
                            'typeMore' => Abstracter::TYPE_TIMESTAMP,
                            'value' => '%d'
                        ),
                        'oauth_version' => '1.0',
                    ),
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'additional info',
                    array(
                        'size_download' => 63.0,
                        'sbs' => 'POST&http%3A%2F%2F127.0.0.1%3A8080%2Foauth%2Faccess_token&oauth_consumer_key%3Dkey%26oauth_nonce%3D%s%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3D%s%26oauth_version%3D1.0',
                    ),
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    'Authorization: OAuth oauth_consumer_key="key",oauth_signature_method="HMAC-SHA1",oauth_nonce="%s",oauth_timestamp="%d",oauth_version="1.0",oauth_signature="%s"',
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-right',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'response headers',
                    \implode('%A', array(
                        'HTTP/1.%d 200 OK',
                        'Content-Type: application/x-www-form-urlencoded',
                        '',
                    )),
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-left',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'response body',
                    'oauth_token=access_token&oauth_token_secret=access_token_secret',
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-left',
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array('channel' => 'general.OAuth'),
            ),
        ), $this->getLogEntries());
    }

    public function testGetAccessTokenException()
    {
        $e = null;
        $line = __LINE__ + 2;
        try {
            self::$oauthDebug->getAccessToken(self::$accessTokenUrl . '/400');
        } catch (\OAuthException $e) {
        }
        $logEntries = $this->getLogEntries();
        $this->assertSame(array(
            'method' => 'warn',
            'args' => array(
                'OAuthException',
                'Invalid auth/bad request (got a 404, expected HTTP/1.1 20X or a redirect)',
            ),
            'meta' => array(
                'channel' => 'general.OAuth',
                'detectFiles' => true,
                'file' => __FILE__,
                'line' => $line,
                'uncollapse' => true,
            ),
        ), \array_slice($logEntries, -2, 1)[0]);
        $this->assertInstanceOf('OAuthException', $e);
    }

    public function testGetRequestToken()
    {
        $response = self::$oauthDebug->getRequestToken(self::$requestTokenUrl, 'http://www.bradkent.com/', OAUTH_HTTP_METHOD_GET);
        $this->assertSame(array(
            'oauth_token' => 'request_token',
            'oauth_token_secret' => 'request_token_secret',
        ), $response);
        $this->assertLogEntries(array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'getRequestToken',
                    'GET',
                    self::$requestTokenUrl,
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'callback url',
                    'http://www.bradkent.com/',
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'time',
                'args' => array(
                    'time: %f ms',
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'OAuth Parameters',
                    array(
                        'oauth_callback' => 'http://www.bradkent.com/',
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '%s',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'strlen' => null,
                            'type' => Abstracter::TYPE_STRING,
                            'typeMore' => Abstracter::TYPE_TIMESTAMP,
                            'value' => '%d',
                        ),
                        'oauth_version' => '1.0',
                    ),
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'additional info',
                    array(
                        'size_download' => 65.0,
                        'sbs' => 'GET&http%3A%2F%2F127.0.0.1%3A8080%2Foauth%2Frequest_token&oauth_callback%3Dhttp%253A%252F%252Fwww.bradkent.com%252F%26oauth_consumer_key%3Dkey%26oauth_nonce%3D%s%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3D%s%26oauth_version%3D1.0',
                    ),
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    'Authorization: OAuth oauth_callback="http%3A%2F%2Fwww.bradkent.com%2F",oauth_consumer_key="key",oauth_signature_method="HMAC-SHA1",oauth_nonce="%s",oauth_timestamp="%d",oauth_version="1.0",oauth_signature="%s"',
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-right',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'response headers',
                    \implode('%A', array(
                        'HTTP/1.%d 200 OK',
                        'Content-Type: application/x-www-form-urlencoded',
                        '',
                    )),
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-left',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'response body',
                    'oauth_token=request_token&oauth_token_secret=request_token_secret',
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-left',
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array('channel' => 'general.OAuth'),
            ),
        ), $this->getLogEntries());
    }

    public function testGetRequestTokenException()
    {
        $e = null;
        $line = __LINE__ + 2;
        try {
            self::$oauthDebug->getRequestToken(self::$requestTokenUrl . '/404', 'http://www.bradkent.com/', OAUTH_HTTP_METHOD_GET);
        } catch (\OAuthException $e) {
        }
        $logEntries = $this->getLogEntries();
        $this->assertSame(array(
            'method' => 'warn',
            'args' => array(
                'OAuthException',
                'Invalid auth/bad request (got a 404, expected HTTP/1.1 20X or a redirect)',
            ),
            'meta' => array(
                'channel' => 'general.OAuth',
                'detectFiles' => true,
                'file' => __FILE__,
                'line' => $line,
                'uncollapse' => true,
            ),
        ), \array_slice($logEntries, -2, 1)[0]);
        $this->assertInstanceOf('OAuthException', $e);
    }

    public function testFetch()
    {
        $return = self::$oauthDebug->fetch(self::$oauthEndpoint, array('foo' => 'bar'), OAUTH_HTTP_METHOD_POST);
        $this->assertIsBool($return);
        $this->assertLogEntries(array(

            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'fetch',
                    'POST',
                    'http://127.0.0.1:8080/oauth/echo',
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'time',
                'args' => array(
                    'time: %f ms',
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'OAuth Parameters',
                    array(
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '%s',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'strlen' => null,
                            'type' => Abstracter::TYPE_STRING,
                            'typeMore' => Abstracter::TYPE_TIMESTAMP,
                            'value' => '%d',
                        ),
                        'oauth_version' => '1.0',
                    ),
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'additional info',
                    array(
                        'size_download' => 7.0,
                        'size_upload' => 7.0,
                        'sbs' => 'POST&http%3A%2F%2F127.0.0.1%3A8080%2Foauth%2Fecho&foo%3Dbar%26oauth_consumer_key%3Dkey%26oauth_nonce%3D%s%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3%s%26oauth_version%3D1.0',
                    ),
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    implode('%A' . "\n", array(
                        'Authorization: OAuth oauth_consumer_key="key",oauth_signature_method="HMAC-SHA1",oauth_nonce="%s",oauth_timestamp="%d",oauth_version="1.0",oauth_signature="%s"',
                        'Content-Type: application/x-www-form-urlencoded',
                    )),
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-right',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request body',
                    'foo=bar',
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-right',
                    'redact' => true,
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'response headers',
                    \implode('%A', array(
                        'HTTP/1.%d 200 OK',
                        'Content-Type: application/x-www-form-urlencoded',
                        '',
                    )),
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-left',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'response body',
                    'foo=bar',
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                    'icon' => 'fa fa-arrow-left',
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array('channel' => 'general.OAuth'),
            ),

        ), $this->getLogEntries());
    }

    public function testFetchParamsViaSbs()
    {
        $oauth = new OAuth(self::$consumerKey, self::$consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $return = $oauth->fetch(self::$oauthEndpoint, array('foo' => 'bar'), OAUTH_HTTP_METHOD_POST);
        $this->assertIsBool($return);
        $this->assertLogEntries(array(
            array(
                'method' => 'log',
                'args' => array(
                    'OAuth Parameters',
                    array(
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '%s',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'strlen' => null,
                            'type' => Abstracter::TYPE_STRING,
                            'typeMore' => Abstracter::TYPE_TIMESTAMP,
                            'value' => '%s',
                        ),
                        'oauth_version' => '1.0',
                    ),
                ),
                'meta' => array(
                    'channel' => 'general.OAuth',
                ),
            ),
        ), \array_slice($this->getLogEntries(), 2, 1));
    }

    public function testFetchException()
    {
        $e = null;
        $line = __LINE__ + 2;
        try {
            self::$oauthDebug->fetch(self::$oauthEndpoint . '/404', array('foo' => 'bar'), OAUTH_HTTP_METHOD_POST);
        } catch (\OAuthException $e) {
        }
        $logEntries = $this->getLogEntries();
        $this->assertSame(array(
            'method' => 'warn',
            'args' => array(
                'OAuthException',
                'Invalid auth/bad request (got a 404, expected HTTP/1.1 20X or a redirect)',
            ),
            'meta' => array(
                'channel' => 'general.OAuth',
                'detectFiles' => true,
                'file' => __FILE__,
                'line' => $line,
                'uncollapse' => true,
            ),
        ), \array_slice($logEntries, -2, 1)[0]);
        $this->assertInstanceOf('OAuthException', $e);
    }
}
