<?php

namespace bdk\Test\CurlHttpMessage;

use bdk\CurlHttpMessage\Factory;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase as TestCaseBase;

/**
 *
 */
class TestCase extends TestCaseBase
{
    use ExpectExceptionTrait;

    protected $baseUrl = 'http://127.0.0.1:8080';

    // ::class introduced in PHP 5.5...  we support 5.4
    protected $classes = array(
        // 'BadMethodCallException' => 'BadMethodCallException',
        'BadResponseException' => 'bdk\\CurlHttpMessage\\Exception\\BadResponseException',
        'Client' => 'bdk\\CurlHttpMessage\\Client',
        'Closure' => 'Closure',
        'HandlerStack' => 'bdk\\CurlHttpMessage\\HandlerStack',
        'InvalidArgumentException' => 'InvalidArgumentException',
        'NetworkException' => 'bdk\\CurlHttpMessage\\Exception\\NetworkException',
        'OutOfBoundsException' => 'OutOfBoundsException',
        'PromiseInterface' => 'bdk\\Promise\\PromiseInterface',
        'RequestException' => 'bdk\\CurlHttpMessage\\Exception\\RequestException',
        'Response' => 'bdk\\HttpMessage\\Response',
        'ResponseInterface' => 'Psr\\Http\\Message\\ResponseInterface',
        'RuntimeException' => 'RuntimeException',
    );

    protected static $factory;

    /**
     * {@inheritDoc}
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        self::$factory = new Factory();
        parent::__construct($name, $data, $dataName);
    }

    public function __get($name)
    {
        if ($name === 'factory') {
            return self::$factory;
        }
        throw new RuntimeException('Access to unavailable property ' . __CLASS__ . '::' . $name);
    }
}
