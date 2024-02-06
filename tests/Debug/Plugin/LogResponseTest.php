<?php

namespace bdk\Test\Debug\Plugin;

use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\Utility\ContentType;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Plugin\AbstractLogReqRes
 * @covers \bdk\Debug\Plugin\LogResponse
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class LogResponseTest extends DebugTestFramework
{
    /**
     * @doesNotPerformAssertions
     */
    public function testBootstrap()
    {
        $this->debug->removePlugin($this->debug->getPlugin('logResponse'));
        $this->debug->addPlugin(new \bdk\Debug\Plugin\LogResponse(), 'logResponse');
    }

    public function testDontLogResponse()
    {
        $logReqRes = $this->debug->getPlugin('logResponse');
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertCount(0, $logEntries);
    }

    public function testLogResponseContent()
    {
        $json = \json_encode(array('foo' => 'bar'));
        $response = (new Response())
            ->withHeader('Content-Type', ContentType::JSON)
            ->withBody(new Stream($json));
        $this->debug->setCfg(array(
            'logResponse' => 'auto',
            'serviceProvider' => array(
                'response' => $response,
            ),
        ));
        $this->debug->obEnd();

        $logReqRes = $this->debug->getPlugin('logResponse');
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));

        self::assertCount(4, $logEntries);

        self::assertSame('Response', $logEntries[0]['args'][0]);
        self::assertSame('Request / Response', $logEntries[0]['meta']['channel']);

        self::assertSame('table', $logEntries[1]['method']);
        self::assertSame(array(
            'Content-Type' => array('value' => ContentType::JSON),
        ), $logEntries[1]['args'][0]);
        self::assertSame('response headers', $logEntries[1]['meta']['caption']);

        self::assertSame('{' . "\n"
            . '    "foo": "bar"' . "\n"
            . '}', \end($logEntries)['args'][4]['value']);
        self::assertSame(array(
            'foo' => 'bar',
        ), \end($logEntries)['args'][4]['valueDecoded']);
    }

    public function testLogResponseContentUnknownType()
    {
        $defaultMimetype = \ini_get('default_mimetype');
        \ini_set('default_mimetype', '');
        $response = (new Response())
            ->withBody(new Stream('Brad Was Here'));
        $this->debug->setCfg(array(
            'logResponse' => 'auto',
            'serviceProvider' => array(
                'response' => $response,
            ),
        ));
        $this->debug->obEnd();

        $logReqRes = $this->debug->getPlugin('logResponse');
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));

        \ini_set('default_mimetype', $defaultMimetype);

        self::assertCount(5, $logEntries);
        self::assertSame('Response', $logEntries[0]['args'][0]);
        self::assertSame(array('response headers', array()), $logEntries[1]['args']);
        self::assertSame('It appears text/plain is being sent without a Content-Type header', $logEntries[2]['args'][0]);
        self::assertSame('Not logging response body for Content-Type "text/plain"', $logEntries[3]['args'][0]);
    }

    public function testResponseContentMaxLen()
    {
        $json = \json_encode(array('foo' => 'bar'));
        $response = (new Response())
            ->withHeader('Content-Type', ContentType::JSON)
            ->withBody(new Stream($json));
        $this->debug->setCfg(array(
            'logResponse' => 'auto',
            'logResponseMaxLen' => 3,
            'serviceProvider' => array(
                'response' => $response,
            ),
        ));
        $this->debug->obEnd();

        $logReqRes = $this->debug->getPlugin('logResponse');
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));

        self::assertCount(4, $logEntries);

        self::assertSame('Response', $logEntries[0]['args'][0]);
        self::assertSame('Request / Response', $logEntries[0]['meta']['channel']);

        self::assertSame('table', $logEntries[1]['method']);
        self::assertSame('response headers', $logEntries[1]['meta']['caption']);
        self::assertSame(array(
            'Content-Type' => array('value' => ContentType::JSON),
        ), $logEntries[1]['args'][0]);

        self::assertSame('response too large (13 B) to output', $logEntries[2]['args'][0]);
    }

    public function testDontLogResponseContent()
    {
        $html = '<!DOCTYPE html><html><head><title>WebCo WebPage</title></head><body>Clever Response</body></html>';
        $response = (new Response())
            ->withHeader('Content-Type', ContentType::HTML)
            ->withBody(new Stream($html));
        $this->debug->setCfg(array(
            // 'logEnvInfo' => true,
            'logResponse' => 'auto',
            'serviceProvider' => array(
                'response' => $response,
            ),
        ));
        $this->debug->obEnd();

        $logReqRes = $this->debug->getPlugin('logResponse');
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        $count = \count($logEntries);

        self::assertCount(4, $logEntries);
        self::assertSame('Not logging response body for Content-Type "text/html"', $logEntries[$count - 2]['args'][0]);
    }
}
