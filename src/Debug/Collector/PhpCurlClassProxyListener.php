<?php

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Proxy\ListenerInterface;
use Curl\Curl;
use ReflectionObject;

/**
 * Listener for PhpCurlClass proxy
 */
class PhpCurlClassProxyListener implements ListenerInterface
{
    /** @var array curl options */
    private $curlOptions = array();

    /** @var Debug */
    private $debug;

    /** @var array<string,mixed> */
    private $debugOptions = array(
        'channelIcon' => ':send-receive:',
        'channelKey' => 'Curl',
        'channelName' => 'Curl',
        'inclInfo' => false,
        'inclOptions' => false,
        'inclRequestBody' => false,
        'inclResponseBody' => false,
        'prettyResponseBody' => true,
        'verbose' => false,
    );

    /** @var Exception|null */
    private $exception = null;

    /** @var array */
    private $initValues = array();

    /** @var array<int,list<string>> constant value to array of names */
    private static $optionConstants = array();

    /** @var Curl */
    private $proxy;

    /** @var string */
    private $rawRequestHeaders = '';

    /** @var array<string,\Reflector> */
    private $reflection = array();

    /** @var Curl */
    private $subject;

    /** @var string|null */
    private $verboseOutput = null;

    /**
     * Constructor
     *
     * @param array      $debugOptions (optional) debug options
     * @param Debug|null $debug        (optional) $debug instance
     */
    public function __construct(array $debugOptions = array(), $debug = null)
    {
        $this->debugOptions = \array_merge($this->debugOptions, $debugOptions);
        $channelOptions = array(
            'channelIcon' => $this->debugOptions['channelIcon'],
            'channelName' => $this->debugOptions['channelName'],
        );
        if (!$debug) {
            $debug = Debug::getChannel($this->debugOptions['channelKey'], $channelOptions);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($this->debugOptions['channelKey'], $channelOptions);
        }
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception = null)
    {
        $this->initValues = $initValues;
        $this->exception = $exception;
        $logMethods = ['delete', 'exec', 'get', 'head', 'options', 'patch', 'post', 'put', 'search'];
        if (\in_array($methodName, $logMethods, true)) {
            $this->log();
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function init($subject, $proxy)
    {
        $this->subject = $subject;
        $this->proxy = $proxy;

        $this->buildConstLookup();
        if ($this->debugOptions['verbose']) {
            $this->subject->verbose(true, \fopen('php://temp', 'rw'));
        }

        $this->setReflection();
    }

    /**
     * Log phpCurlClass method call
     *
     * @return void
     */
    private function log()
    {
        $this->setValues();
        $this->debug->groupCollapsed(
            $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG),
            $this->getHttpMethod(),
            $this->curlOptions['CURLOPT_URL'],
            $this->debug->meta(array(
                'icon' => $this->debugOptions['channelIcon'],
                'redact' => true,
            ))
        );
        if ($this->debugOptions['inclOptions']) {
            $this->debug->log('options', $this->curlOptions, $this->debug->meta('redact'));
        }
        $this->logRequestResponse();
        $this->debug->groupEnd();
    }

    /**
     * Set self::optionConstants  CURLOPT_* value => names array
     *
     * @return void
     */
    private function buildConstLookup()
    {
        if (self::$optionConstants) {
            return;
        }
        $consts = \get_defined_constants(true);
        $consts = (array) $consts['curl'];
        $valToNames = \array_fill_keys(\array_unique($consts), array());
        foreach ($consts as $name => $val) {
            if (\strpos($name, 'CURLOPT') !== 0 && $name !== 'CURLINFO_HEADER_OUT') {
                continue;
            }
            $valToNames[$val][] = $name;
        }
        self::$optionConstants = $valToNames;
    }

    /**
     * Build an array of human-readable options used
     *
     * @return array
     */
    private function buildOptionsDebug()
    {
        $options = $this->reflection['options']->getValue($this->subject);
        $opts = array();
        foreach ($options as $constVal => $val) {
            $name = \implode(' / ', self::$optionConstants[$constVal]);
            $opts[$name] = $val;
        }
        if (isset($opts['CURLOPT_POSTFIELDS']) && \is_string($opts['CURLOPT_POSTFIELDS'])) {
            $parsed = array();
            \parse_str($opts['CURLOPT_POSTFIELDS'], $parsed);
            $opts['CURLOPT_POSTFIELDS'] = $parsed;
        }
        if (isset($opts['CURLOPT_CUSTOMREQUEST']) && $opts['CURLOPT_CUSTOMREQUEST'] === 'HEAD') {
            // PhpCurlClass reset CURLOPT_NOBODY in exec()
            $opts['CURLOPT_NOBODY'] = true;
        }
        \ksort($opts);
        return $opts;
    }

    /**
     * Get the http method used (GET, POST, etc)
     *
     * @return string
     */
    private function getHttpMethod()
    {
        $method = 'GET';
        if (isset($this->curlOptions['CURLOPT_CUSTOMREQUEST'])) {
            $method = $this->curlOptions['CURLOPT_CUSTOMREQUEST'];
        } elseif (!empty($this->curlOptions['CURLOPT_POST'])) {
            $method = 'POST';
        }
        return $method;
    }

    /**
     * Get the response body
     *
     * Will return formatted Abstraction if html/json/xml
     *
     * @return \bdk\Debug\Abstraction\Abstraction|string|null
     */
    private function getResponseBody()
    {
        $body = $this->subject->rawResponse;
        if (\strlen($body) === 0) {
            return null;
        }
        $contentType = $this->subject->responseHeaders['content-type'];
        return $this->debugOptions['prettyResponseBody']
            ? $this->debug->prettify($body, $contentType)
            : $body;
    }

    /**
     * Set curlOptions, verboseOutput, rawRequestHeaders, and requestHeaders (parsed) properties on self::subject for use in logging
     *
     * @return void
     */
    private function setValues()
    {
        $this->curlOptions = $this->buildOptionsDebug();
        $this->verboseOutput = null;
        $this->rawRequestHeaders = $this->subject->getInfo(CURLINFO_HEADER_OUT) ?: '';
        if (empty($this->curlOptions['CURLOPT_VERBOSE'])) {
            return;
        }
        /*
            CURLINFO_HEADER_OUT doesn't work with verbose...
            but we can get the request headers from the verbose output
        */
        $pointer = $this->curlOptions['CURLOPT_STDERR'];
        \rewind($pointer);
        $this->verboseOutput = \stream_get_contents($pointer);
        $matches = [];
        \preg_match_all('/> (.*?)\r\n\r\n/s', $this->verboseOutput, $matches);
        $this->rawRequestHeaders = \end($matches[1]);
        $this->subject->requestHeaders = $this->reflection['parseReqHeaders']->invoke($this->subject, $this->rawRequestHeaders);
    }

    /**
     * Log errors, redirects, request headers, response headers, response body, etc
     *
     * @return void
     */
    private function logRequestResponse()
    {
        $this->logRequest();
        $this->debug->time(\microtime(true) - $this->initValues['timeStart']);
        // Curl provides no means to get the request body
        if ($this->subject->error) {
            $this->debug->warn($this->subject->errorCode, $this->subject->errorMessage);
        }
        if ($this->subject->effectiveUrl !== $this->curlOptions['CURLOPT_URL']) {
            \preg_match_all('/^(Location:|URI: )(.*?)\r\n/im', $this->subject->rawResponseHeaders, $matches);
            $this->debug->log('Redirect(s)', $matches[2]);
        }
        $this->logResponse();
        if ($this->debugOptions['inclInfo']) {
            $this->debug->log($this->debug->i18n->trans('info'), $this->subject->getInfo());
        }
        if ($this->verboseOutput) {
            $this->debug->log($this->debug->i18n->trans('word.verbose'), $this->verboseOutput);
        }
    }

    /**
     * Log request headers and body
     *
     * @return void
     */
    private function logRequest()
    {
        if ($this->rawRequestHeaders) {
            $this->debug->log($this->debug->i18n->trans('request.headers'), $this->debug->redactHeaders($this->rawRequestHeaders));
        }
        if ($this->debugOptions['inclRequestBody'] && isset($this->curlOptions['CURLOPT_POSTFIELDS'])) {
            $requestBody = \is_array($this->curlOptions['CURLOPT_POSTFIELDS'])
                ? $this->debug->abstracter->getAbstraction(\http_build_query($this->curlOptions['CURLOPT_POSTFIELDS']), null, [Type::TYPE_STRING, Type::TYPE_STRING_FORM])
                : $this->curlOptions['CURLOPT_POSTFIELDS'];
            $this->debug->log($this->debug->i18n->trans('request.body'), $requestBody, $this->debug->meta('redact'));
        }
    }

    /**
     * Log response headers and body
     *
     * @return void
     */
    private function logResponse()
    {
        $responseHeaders = \trim($this->subject->rawResponseHeaders);
        if ($responseHeaders) {
            $this->debug->log($this->debug->i18n->trans('response.headers'), $this->debug->redactHeaders($responseHeaders));
        }
        $responseBody = $this->getResponseBody();
        if ($responseBody === null && $this->subject->error) {
            return;
        }
        if (!empty($this->curlOptions['CURLOPT_NOBODY'])) {
            $responseBody = 'CURLOPT_NOBODY';
        }
        $this->debug->log($this->debug->i18n->trans('response.body'), $responseBody, $this->debug->meta('redact'));
    }

    /**
     * We need access to some subject privates
     *
     * @return void
     */
    private function setReflection()
    {
        $classRef = new ReflectionObject($this->subject);
        // phpCurlClass adds a public getOptions method but this is necessary for prior versions
        $optionsRef = $classRef->getProperty('options');
        $parseReqHeadersRef = $classRef->getMethod('parseRequestHeaders');
        if (PHP_VERSION_ID < 80100) {
            $optionsRef->setAccessible(true);
            $parseReqHeadersRef->setAccessible(true);
        }
        $this->reflection = array(
            'options' => $optionsRef,
            'parseReqHeaders' => $parseReqHeadersRef,
        );
    }
}
