<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\HttpFoundationBridge;
use bdk\HttpMessage\UploadedFile;
use bdk\Test\PolyFill\AssertionTrait;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
/**
 * @covers \bdk\HttpMessage\HttpFoundationBridge
 */
class HttpFoundationBridgeTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    public function testCreateRequest()
    {
        $serverParams = array(
            'REQUEST_METHOD' => 'POST',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_HOST' => 'www.test.com:8080',
            'REQUEST_URI' => '/path?ding=dong',
            'REQUEST_TIME_FLOAT' => $_SERVER['REQUEST_TIME_FLOAT'],
            'SCRIPT_NAME' => isset($_SERVER['SCRIPT_NAME'])
                ? $_SERVER['SCRIPT_NAME']
                : null,
            'PHP_AUTH_USER' => 'billybob',
            'PHP_AUTH_PW' => '1234',
        );
        $filesPhp = array(
            'files1' => [
                'name' => 'test1.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => TEST_DIR . '/assets/logo.png',
                'error' => UPLOAD_ERR_OK,
                'size' => 100000,
            ],
            // <input type="file" name="files2[a]">
            // <input type="file" name="files2[b]">
            'files2' => [
                'name' => [
                    'a' => 'test2.jpg',
                    'b' => 'test3.jpg',
                ],
                'type' => [
                    'a' => 'image/jpeg',
                    'b' => 'image/jpeg',
                ],
                'tmp_name' => [
                    'a' => TEST_DIR . '/assets/logo.png',
                    'b' => TEST_DIR . '/assets/logo.png',
                ],
                'error' => [
                    'a' => UPLOAD_ERR_OK,
                    'b' => UPLOAD_ERR_OK,
                ],
                'size' => [
                    'a' => 100001,
                    'b' => 100010,
                ],
            ],
            'noFile' => [
                'name' => null,
                'type' => null,
                'tmp_name' => null,
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        );
        $foundationRequest = new HttpFoundationRequest(
            array('foo' => 'bar'),              // query params
            array('how' => 'posted'),          // post params
            array('attrib' => 'test'),          // path attributes
            array('type' => 'chocolate chip'),  // cookies
            $filesPhp,
            $serverParams
        );
        $request = HttpFoundationBridge::createRequest($foundationRequest);
        $this->assertInstanceOf('bdk\\HttpMessage\\ServerRequest', $request);
        $this->assertSame(array(
            'foo' => 'bar',
        ), $request->getQueryParams());
        $this->assertSame(array(
            'type' => 'chocolate chip',
        ), $request->getCookieParams());
        $this->assertEquals($serverParams, $request->getServerParams());
        $this->assertSame(array(
            'attrib' => 'test',
        ), $request->getAttributes());
        $this->assertSame(array(
            'how' => 'posted',
        ), $request->getParsedBody());
        $this->assertEquals(array(
            'files1' => new UploadedFile(
                $filesPhp['files1']['tmp_name'],
                $filesPhp['files1']['size'],
                $filesPhp['files1']['error'],
                $filesPhp['files1']['name'],
                $filesPhp['files1']['type']
            ),
            'files2' => array(
                'a' => new UploadedFile(
                    $filesPhp['files2']['tmp_name']['a'],
                    $filesPhp['files2']['size']['a'],
                    $filesPhp['files2']['error']['a'],
                    $filesPhp['files2']['name']['a'],
                    $filesPhp['files2']['type']['a']
                ),
                'b' => new UploadedFile(
                    $filesPhp['files2']['tmp_name']['b'],
                    $filesPhp['files2']['size']['b'],
                    $filesPhp['files2']['error']['b'],
                    $filesPhp['files2']['name']['b'],
                    $filesPhp['files2']['type']['b']
                ),
            ),
            'noFile' => new UploadedFile(
                $filesPhp['noFile']['tmp_name'],
                $filesPhp['noFile']['size'],
                $filesPhp['noFile']['error'],
                $filesPhp['noFile']['name'],
                $filesPhp['noFile']['type']
            ),
        ), $request->getUploadedFiles());
    }

    public function testCreateResponse()
    {
        $html = '<!DOCTYPE html><html><head><title>WebCo WebPage</title></head><body>Clever Response</body></html>';
        $foundationResponse = new HttpFoundationResponse($html, 404);
        $foundationResponse->headers->setCookie(new Cookie('type', 'chocolate chip'));
        $response = HttpFoundationBridge::createResponse($foundationResponse);
        $this->assertInstanceOf('bdk\\HttpMessage\\Response', $response);
        $this->assertSame(404, $response->getStatusCode());
        $responseHeaders = $response->getHeaders();
        $this->assertSame(array('cache-control', 'date', 'set-cookie'), \array_keys($responseHeaders));
        $this->assertSame(array('no-cache, private'), $responseHeaders['cache-control']);
        $this->assertStringStartsWith('type=chocolate%20chip; path=/; httponly', $responseHeaders['set-cookie'][0]);
        $this->assertSame($html, (string) $response->getBody());
    }

    public function testCreateResponseBinaryFile()
    {
        $foundationResponse = new BinaryFileResponse(TEST_DIR . '/assets/logo.png');
        $response = HttpFoundationBridge::createResponse($foundationResponse);
        $this->assertInstanceOf('bdk\\HttpMessage\\Response', $response);
        $this->assertSame(\file_get_contents(TEST_DIR . '/assets/logo.png'), (string) $response->getBody());
    }

    public function testCreateResponseContentRange()
    {
        $foundationResponse = new BinaryFileResponse(
            TEST_DIR . '/assets/logo.png',
            200,
            array('Content-Range' => 'bytes 200-1000/67589')
        );
        $response = HttpFoundationBridge::createResponse($foundationResponse);
        $this->assertInstanceOf('bdk\\HttpMessage\\Response', $response);
        $this->assertSame(\file_get_contents(TEST_DIR . '/assets/logo.png'), (string) $response->getBody());
    }
}
