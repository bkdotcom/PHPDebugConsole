<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\WpHttp
 */
class WpHttpTest extends DebugTestFramework
{
    protected static $plugin;

    public static function setUpBeforeClass(): void
    {
        if (!\function_exists('get_option')) {
            require_once __DIR__ . '/mock_wordpress.php';
        }
        wp_reset_mock();
        self::resetDebug();
        self::$plugin = new \bdk\Debug\Framework\WordPress\WpHttp();
        // self::$plugin->onBootstrap(new Event(self::$debug));
    }

    public function testGetSubscriptions()
    {
        self::assertInstanceOf('bdk\PubSub\SubscriberInterface', self::$plugin);
        self::assertSame([
            // Debug::EVENT_BOOTSTRAP => 'onBootstrap',
        ], self::$plugin->getSubscriptions());
    }

    /*
    public function testOnBootstrap()
    {
        wp_reset_mock();
        self::$plugin->onBootstrap(new Event(self::$debug));
        $objectHash = \spl_object_hash(self::$plugin);
        self::assertSame(array(
            'http_request_args' => [[$objectHash, 'onRequest']],
            'http_api_debug' => [[$objectHash, 'onResponse']],
        ), $GLOBALS['wp_actions_filters']['filters']);
    }
    */

    public function testOnRequestResponse()
    {
        $args = array(
            'blocking' => true,
            'body' => array('foo' => 'bar'),
            // 'body' => json_encode(array('foo' => 'bar')),
            'headers' => array(
                // 'Content-Type' => 'application/json',
                'x-brad' => 'was here',
            ),
            'httpversion' => '1.1',
            'method' => 'POST',
            'user-agent' => 'wordpress',
        );
        $url = 'http://example.com/path/?foo=bar';
        $argsNew = self::$plugin->onRequest($args, $url);
        self::assertIsFloat($argsNew['time_start']);
        $args['time_start'] = $argsNew['time_start'];
        self::assertSame($args, $argsNew);

        $requestId = 'wphttp_' . \md5($args['time_start']);

        $responseStuff = array(
            'response' => array(
                'code' => 200,
                'message' => 'OK',
            ),
            'body' => 'response body',
            'headers' => array(
                'content-type' => 'text/html',
                'x-foo' => 'bar',
            ),
            'http_response' => 'some object',
        );
        self::$plugin->onResponse($responseStuff, 'response', 'TransportClass', $args, $url);

        self::assertSame([
            $requestId => array(
                'method' => 'groupCollapsed',
                'args' => [
                    'http',
                    'POST',
                    'http://example.com/path/?foo=bar',
                ],
                'meta' => array(
                    'attribs' => array(
                        'id' => $requestId,
                        'class' => [],
                    ),
                    'channel' => 'general.http',
                    'icon' => 'fa fa-exchange',
                    'redact' => true,
                ),
            ),
            0 => array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    'POST /path/?foo=bar HTTP/1.1' . "\r\n"
                        . 'Host: example.com' . "\r\n"
                        . 'User-Agent: wordpress' . "\r\n"
                        . 'x-brad: was here' . "\r\n"
                        . 'Content-Type: application/x-www-form-urlencoded',
                ),
                'meta' => array(
                    'channel' => 'general.http',
                ),
            ),
            1 => array(
                'method' => 'log',
                'args' => array(
                    0 => 'request body',
                    1 => array(
                        'brief' => false,
                        'debug' => Abstracter::ABSTRACTION,
                        'type' => 'string',
                        'typeMore' => 'form',
                        'value' => 'foo=bar',
                        'valueDecoded' => array(
                            'foo' => 'bar',
                        ),
                    ),
                ),
                'meta' => array(
                    'channel' => 'general.http',
                    'redact' => true,
                ),
            ),
            2 => array(
                'method' => 'log',
                'args' => array(
                    'response headers',
                    'HTTP/1.1 200 OK' . "\r\n"
                        . 'content-type: text/html' . "\r\n"
                        . 'x-foo: bar',
                ),
                'meta' => array(
                    'channel' => 'general.http',
                ),
            ),
            3 => array(
                'method' => 'log',
                'args' => array(
                    'response body',
                    array(
                        'attribs' => array(
                            'class' => [
                                'highlight',
                                'language-markup',
                                'no-quotes',
                            ],
                        ),
                        'brief' => false,
                        'contentType' => 'text/html',
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => false,
                        'prettifiedTag' => false,
                        'type' => 'string',
                        'typeMore' => null,
                        'value' => 'response body',
                    ),
                ),
                'meta' => array(
                    'channel' => 'general.http',
                    'redact' => true,
                ),
            ),
            4 => array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'general.http',
                ),
            ),

        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    public function testOnRequestResponseAsync()
    {
        $args = array(
            'blocking' => false,
            'headers' => array(
                'x-brad' => 'was here',
            ),
            'httpversion' => '1.1',
            'method' => 'GET',
            'user-agent' => 'wordpress',
        );
        $url = 'http://example.com/path/?foo=bar';
        $argsNew = self::$plugin->onRequest($args, $url);
        self::assertIsFloat($argsNew['time_start']);
        $args['time_start'] = $argsNew['time_start'];
        self::assertSame($args, $argsNew);

        $requestId = 'wphttp_' . \md5($args['time_start']);

        $responseStuff = array(
            'response' => array(
                'code' => 200,
                'message' => 'OK',
            ),
            'body' => json_encode(array('message' => 'json for reasons')),
            'headers' => array(
                'content-type' => 'application/json',
                'x-foo' => 'bar',
            ),
            'http_response' => 'some object',
        );
        self::$plugin->onResponse($responseStuff, 'response', 'TransportClass', $args, $url);

        self::assertSame([

            $requestId => array(
                'method' => 'groupCollapsed',
                'args' => [
                    'http',
                    'GET',
                    'http://example.com/path/?foo=bar',
                ],
                'meta' => array(
                    'attribs' => array(
                        'id' => $requestId,
                        'class' => [],
                    ),
                    'channel' => 'general.http',
                    'icon' => 'fa fa-exchange',
                    'redact' => true,
                ),
            ),
            0 => array(
                'method' => 'info',
                'args' => [
                    'asynchronous',
                ],
                'meta' => array(
                    'channel' => 'general.http',
                    'icon' => 'fa fa-random',
                ),
            ),
            1 => array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    'GET /path/?foo=bar HTTP/1.1' . "\r\n"
                        . 'Host: example.com' . "\r\n"
                        . 'User-Agent: wordpress' . "\r\n"
                        . 'x-brad: was here',
                ),
                'meta' => array(
                    'channel' => 'general.http',
                ),
            ),
            2 => array(
                'method' => 'info',
                'args' => array(
                    'WordPress does not provide asynchronous response',
                ),
                'meta' => array(
                    'appendGroup' => $requestId,
                    'channel' => 'general.http',
                ),
            ),
            3 => array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'general.http',
                ),
            ),
        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }


    public function testOnRequestResponseAsyncError()
    {
        $args = array(
            'blocking' => false,
            // 'body' => array('foo' => 'bar'),
            // 'body' => json_encode(array('foo' => 'bar')),
            'headers' => array(
                // 'Content-Type' => 'application/json',
                'x-brad' => 'was here',
            ),
            'httpversion' => '1.1',
            'method' => 'GET',
            'user-agent' => 'wordpress',
        );
        $url = 'http://example.com/path/?foo=bar';
        $argsNew = self::$plugin->onRequest($args, $url);
        self::assertIsFloat($argsNew['time_start']);
        $args['time_start'] = $argsNew['time_start'];
        self::assertSame($args, $argsNew);

        $requestId = 'wphttp_' . \md5($args['time_start']);

        $responseStuff = array(
            'response' => array(
                'code' => 500,
                'message' => 'busted',
            ),
            'body' => json_encode(array('message' => 'json for reasons')),
            'headers' => array(
                'content-type' => 'application/json',
                'x-foo' => 'bar',
            ),
            'http_response' => 'some object',
        );
        $line = __LINE__ + 1;
        self::$plugin->onResponse($responseStuff, 'response', 'TransportClass', $args, $url);

        self::assertSame([

            $requestId => array(
                'method' => 'groupCollapsed',
                'args' => [
                    'http',
                    'GET',
                    'http://example.com/path/?foo=bar',
                ],
                'meta' => array(
                    'attribs' => array(
                        'id' => $requestId,
                        'class' => [],
                    ),
                    'channel' => 'general.http',
                    'icon' => 'fa fa-exchange',
                    'redact' => true,
                ),
            ),
            0 => array(
                'method' => 'info',
                'args' => [
                    'asynchronous',
                ],
                'meta' => array(
                    'channel' => 'general.http',
                    'icon' => 'fa fa-random',
                ),
            ),
            1 => array(
                'method' => 'log',
                'args' => array(
                    'request headers',
                    'GET /path/?foo=bar HTTP/1.1' . "\r\n"
                        . 'Host: example.com' . "\r\n"
                        . 'User-Agent: wordpress' . "\r\n"
                        . 'x-brad: was here',
                ),
                'meta' => array(
                    'channel' => 'general.http',
                ),
            ),
            2 => array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'general.http',
                ),
            ),
            3 => array(
                'method' => 'error',
                'args' => array(
                    'error',
                    'some object',
                ),
                'meta' => array(
                    'channel' => 'general.http',
                    'detectFiles' => true,
                    'file' => __FILE__,
                    'line' => $line,
                    'uncollapse' => true,
                ),
            ),

        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    public function testSetCfg()
    {
        // disable
        self::$plugin->setCfg(array(
            'enabled' => false,
        ));
        self::assertSame(array(
            'actions' => array(),
            'filters' => array(
                'http_request_args' => [],
                'http_api_debug' => [],
            ),
        ), $GLOBALS['wp_actions_filters']);

        // enable
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(),
            'filters' => array(
                'http_request_args' => [
                    [self::$plugin, 'onRequest'],
                ],
                'http_api_debug' => [
                    [self::$plugin, 'onResponse'],
                ],
            ),
        ), $GLOBALS['wp_actions_filters']);

        // no change
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(),
            'filters' => array(
                'http_request_args' => [
                    [self::$plugin, 'onRequest'],
                ],
                'http_api_debug' => [
                    [self::$plugin, 'onResponse'],
                ],
            ),
        ), $GLOBALS['wp_actions_filters']);
    }
}
