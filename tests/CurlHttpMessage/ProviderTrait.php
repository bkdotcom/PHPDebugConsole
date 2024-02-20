<?php

namespace bdk\Test\CurlHttpMessage;

trait ProviderTrait
{
    public static function methodProvider()
    {
        return [
            'DELETE' => [
                'method' => 'delete',
                'uri' => 'http://example.com/',
            ],
            'GET' => [
                'method' => 'get',
                'uri' => 'http://example.com/',
            ],
            'HEAD' => [
                'method' => 'head',
                'uri' => 'http://example.com/',
            ],
            'OPTIONS' => [
                'method' => 'options',
                'uri' => 'http://example.com/',
            ],
            'PATCH' => [
                'method' => 'patch',
                'uri' => 'http://example.com/',
            ],
            'POST' => [
                'method' => 'post',
                'uri' => 'http://example.com/',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => array('foo' => 'bar'),
            ],
            'PUT' => [
                'method' => 'put',
                'uri' => 'http://example.com/',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => array('foo' => 'bar'),
            ],
            'TRACE' => [
                'method' => 'trace',
                'uri' => 'http://example.com/',
            ],
        ];
    }
}
