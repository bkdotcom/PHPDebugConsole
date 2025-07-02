<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Collector\OAuth;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Collector\OAuth
 * @covers \bdk\Debug\Plugin\Redaction
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class OAuthTest extends DebugTestFramework
{
    public static $consumerKey = 'key';
    public static $consumerSecret = 'secret';
    public static $accessTokenUrl = 'http://127.0.0.1:8080/oauth/access_token';
    public static $requestTokenUrl = 'http://127.0.0.1:8080/oauth/request_token';
    public static $oauthEndpoint = 'http://127.0.0.1:8080/echo';
    public static $token;
    public static $tokenSecret;
    private static $oauthDebug;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (\extension_loaded('OAuth')) {
            self::$oauthDebug = new OAuth(self::$consumerKey, self::$consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
        }
    }

    public function testGetAccessToken()
    {
        $this->assertOauth();
        $response = self::$oauthDebug->getAccessToken(self::$accessTokenUrl, '', '', OAUTH_HTTP_METHOD_POST);
        $this->assertSame(array(
            'oauth_token' => 'access_token',
            'oauth_token_secret' => 'access_token_secret',
        ), $response);
        $logEntries = $this->getLogEntries();
        $logEntries[3]['args'][1] = \array_intersect_key($logEntries[3]['args'][1], \array_flip(['size_download', 'sbs']));

        $this->assertLogEntries(array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'OAuth::getAccessToken',
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
                    'OAuth parameters',
                    array(
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '█████████',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'type' => Type::TYPE_STRING,
                            'typeMore' => Type::TYPE_TIMESTAMP,
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
                        // 'size_download' => 63.0,
                        'sbs' => 'POST&http%3A%2F%2F127.0.0.1%3A8080%2Foauth%2Faccess_token&oauth_consumer_key%3Dkey%26oauth_nonce%3D%s%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3D%s%26oauth_version%3D1.0',
                    ),
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    '%AAuthorization: OAuth oauth_consumer_key="key",oauth_signature_method="HMAC-SHA1",oauth_nonce="%s",oauth_timestamp="%d",oauth_version="1.0",oauth_signature="█████████"%A',
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
        ), $logEntries);
    }

    public function testGetAccessTokenException()
    {
        $this->assertOauth();
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
                // 'evalLine' => null,
                'file' => __FILE__,
                'line' => $line,
                'uncollapse' => true,
            ),
        ), \array_slice($logEntries, -2, 1)[0]);
        $this->assertInstanceOf('OAuthException', $e);
    }

    public function testGetRequestToken()
    {
        $this->assertOauth();
        $response = self::$oauthDebug->getRequestToken(self::$requestTokenUrl, 'http://www.bradkent.com/', OAUTH_HTTP_METHOD_GET);
        $this->assertSame(array(
            'oauth_token' => 'request_token',
            'oauth_token_secret' => 'request_token_secret',
        ), $response);

        $logEntries = $this->getLogEntries();
        $logEntries[4]['args'][1] = \array_intersect_key($logEntries[4]['args'][1], \array_flip(['size_download', 'sbs']));
        $this->assertLogEntries(array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'OAuth::getRequestToken',
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
                    'OAuth parameters',
                    array(
                        'oauth_callback' => 'http://www.bradkent.com/',
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '█████████',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            // 'strlen' => 10,
                            // 'strlenValue' => 10,
                            'type' => Type::TYPE_STRING,
                            'typeMore' => Type::TYPE_TIMESTAMP,
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
                        // 'size_download' => 65.0,
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
                    '%AAuthorization: OAuth oauth_callback="http%3A%2F%2Fwww.bradkent.com%2F",oauth_consumer_key="key",oauth_signature_method="HMAC-SHA1",oauth_nonce="%s",oauth_timestamp="%d",oauth_version="1.0",oauth_signature="█████████"%A',
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
        ), $logEntries);
    }

    public function testGetRequestTokenException()
    {
        $this->assertOauth();
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
                // 'evalLine' => null,
                'file' => __FILE__,
                'line' => $line,
                'uncollapse' => true,
            ),
        ), \array_slice($logEntries, -2, 1)[0]);
        $this->assertInstanceOf('OAuthException', $e);
    }

    public function testFetch()
    {
        $this->assertOauth();
        $return = self::$oauthDebug->fetch(self::$oauthEndpoint, array('foo' => 'bar'), OAUTH_HTTP_METHOD_POST);
        $this->assertIsBool($return);
        $logEntriesActual = $this->getLogEntries();
        $sizeDownload = null;
        foreach ($logEntriesActual as $i => $logEntry) {
            if (isset($logEntry['args'][0]) && $logEntry['args'][0] === 'additional info') {
                // $sizeDownload = $logEntry['args'][1]['size_download'];
                $logEntriesActual[$i]['args'][1] = \array_intersect_key($logEntry['args'][1], \array_flip(['size_download', 'size_upload', 'sbs']));
            } elseif (isset($logEntry['args'][0]) && $logEntry['args'][0] === 'response body') {
                $logEntriesActual[$i]['args'][1] = $logEntry['args'][1]['value'];
            }
        }

        $this->assertLogEntries(array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'OAuth::fetch',
                    'POST',
                    self::$oauthEndpoint,
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
                    'OAuth parameters',
                    array(
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '█████████',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'type' => Type::TYPE_STRING,
                            'typeMore' => Type::TYPE_TIMESTAMP,
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
                        // 'size_download' => $sizeDownload,
                        'size_upload' => 7.0,
                        'sbs' => 'POST&http%3A%2F%2F127.0.0.1%3A8080%2Fecho&foo%3Dbar%26oauth_consumer_key%3Dkey%26oauth_nonce%3D%s%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3%s%26oauth_version%3D1.0',
                    ),
                ),
                'meta' => array('channel' => 'general.OAuth'),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    '%A' . implode('%A' . "\n", array(
                        'Authorization: OAuth oauth_consumer_key="key",oauth_signature_method="HMAC-SHA1",oauth_nonce="%s",oauth_timestamp="%d",oauth_version="1.0",oauth_signature="█████████"',
                        'Content-Type: application/x-www-form-urlencoded',
                    )) . '%A',
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
                        'Content-Type: application/json; charset="utf-8"',
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
                    '%Afoo=bar%A',
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

        ), $logEntriesActual);
    }

    public function testFetchParamsViaSbs()
    {
        $this->assertOauth();
        $oauth = new OAuth(self::$consumerKey, self::$consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $return = $oauth->fetch(self::$oauthEndpoint, array('foo' => 'bar'), OAUTH_HTTP_METHOD_POST);
        $this->assertIsBool($return);
        $this->assertLogEntries(array(
            array(
                'method' => 'log',
                'args' => array(
                    'OAuth parameters',
                    array(
                        'oauth_consumer_key' => 'key',
                        'oauth_nonce' => '%s',
                        'oauth_signature' => '█████████',
                        'oauth_signature_method' => 'HMAC-SHA1',
                        'oauth_timestamp' => array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'type' => Type::TYPE_STRING,
                            'typeMore' => Type::TYPE_TIMESTAMP,
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
        $this->assertOauth();
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
                // 'evalLine' => null,
                'file' => __FILE__,
                'line' => $line,
                'uncollapse' => true,
            ),
        ), \array_slice($logEntries, -2, 1)[0]);
        $this->assertInstanceOf('OAuthException', $e);
    }

    protected function assertOauth()
    {
        if (\extension_loaded('oauth') === false) {
            $this->markTestSkipped('OAuth not avail');
        }
    }
}
