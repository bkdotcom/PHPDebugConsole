<?php

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\SubscriberInterface;

/**
 * Handle traces and errors for proxy classes
 */
class ProxyHooks implements SubscriberInterface
{
    /** @var Debug */
    private $debug;

    private $proxyClasses = [
        'Curl_CurlProxy',
        'mysqliProxy',
        'OAuthProxy',
        'PDOProxy',
        'Psr_SimpleCache_CacheInterfaceProxy',
        'SoapClientProxy',
    ];

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        $this->debug->backtrace->addProcessor([$this, 'backtraceProcessor']);
        return array(
            ErrorHandler::EVENT_ERROR => ['onError', 1000],
        );
    }

    /**
     * Skip over "internal" proxy classes in traces
     *
     * @param array $trace Trace frames
     *
     * @return array
     */
    public function backtraceProcessor(array $trace)
    {
        $function = isset($trace[1]['function'])
            ? $trace[1]['function']
            : null;
        if ($function === 'ReflectionClass->newInstanceArgs' && isset($trace[2]['function'])) {
            $function = $trace[2]['function'];
        }
        $function = $this->debug->backtrace->parseFunction($function);
        if (\in_array($function['class'], $this->proxyClasses, true) === false) {
            return $trace;
        }
        $class = $function['class'];
        $count = \count($trace);
        for ($i = 2; $i < $count; $i++) {
            $function = isset($trace[$i]['function'])
                ? $trace[$i]['function']
                : null;
            $function = $this->debug->backtrace->parseFunction($function);
            if ($function['class'] !== $class && \strpos((string) $function['class'], 'bdk\\Debug') === false) {
                break;
            }
        }
        return \array_slice($trace, $i - 1);
    }

    /**
     * Skip over "internal" proxy classes when reporting error file/line
     *
     * @param \bdk\ErrorHandler\Error $error Error instance
     *
     * @return void
     */
    public function onError(Error $error)
    {
        $filePathProxyTrait = \realpath(__DIR__ . '/../../Proxy/ProxyTrait.php');
        if ($error['file'] !== $filePathProxyTrait) {
            return;
        }
        $trace = $error['exception']
            ? $error['exception']->getTrace()
            : \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $found = false;
        foreach ($trace as $frame) {
            if (isset($frame['file']) === false) {
                continue;
            }
            if ($found === false) {
                $found = $frame['file'] === $filePathProxyTrait;
                continue;
            }
            if (\dirname($frame['file']) !== \dirname($filePathProxyTrait)) {
                break;
            }
        }
        $error['file'] = $frame['file'];
        $error['line'] = $frame['line'];
    }
}
