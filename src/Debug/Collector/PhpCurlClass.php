<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use Curl\Curl;
use ReflectionObject;

/**
 * Extend php-curl-class to log each request
 *
 * Decorator (that extends Curl) would be prefered..  however unable to handle Curl's public properties
 *
 * @see https://github.com/php-curl-class/php-curl-class
 */
class PhpCurlClass extends Curl
{
    public $rawRequestHeaders = '';

    private $debug;
    private $icon = 'fa fa-exchange';
    private $debugOptions = array(
        'inclInfo' => false,
        'inclResponseBody' => false,
        'label' => 'Curl',
        'prettyResponseBody' => true,
        'verbose' => false,
    );
    private $reflection = array();

    /**
     * @var array constant value to array of names
     */
    protected static $optionConstants = array();

    /**
     * Constructor
     *
     * @param array $options options
     * @param Debug $debug   (optional) Specify PHPDebugConsole instance
     *                        if not passed, will create Curl channel on singleton instance
     *                        if root channel is specified, will create a Curl channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($options = array(), Debug $debug = null)
    {
        $this->debugOptions = \array_merge($this->debugOptions, $options);
        if (!$debug) {
            $debug = Debug::_getChannel($this->debugOptions['label'], array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($this->debugOptions['label'], array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $this->buildConstLookup();
        parent::__construct();
        if ($this->debugOptions['verbose']) {
            $this->verbose(true, \fopen('php://temp', 'rw'));
        }
        /*
            Do some reflection
        */
        $classRef = (new ReflectionObject($this))->getParentClass();
        $optionsRef = $classRef->getProperty('options');
        $optionsRef->setAccessible(true);
        $parseReqHeadersRef = $classRef->getMethod('parseRequestHeaders');
        $parseReqHeadersRef->setAccessible(true);
        $this->reflection = array(
            'options' => $optionsRef,
            'parseReqHeaders' => $parseReqHeadersRef,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param resource $ch Curl handle
     *
     * @return mixed Returns the value provided by parseResponse.
     */
    public function exec($ch = null)
    {
        $options = $this->buildOptionsDebug();
        $this->debug->groupCollapsed(
            $this->debugOptions['label'],
            $this->getHttpMethod($options),
            $options['CURLOPT_URL'],
            $this->debug->meta(array(
                'icon' => $this->icon,
                'redact' => true,
            ))
        );
        $this->debug->time($this->debugOptions['label']);
        $this->debug->log('options', $options);
        $return = parent::exec($ch);
        $verboseOutput = null;
        $this->rawRequestHeaders = $this->getInfo(CURLINFO_HEADER_OUT);
        if (!empty($options['CURLOPT_VERBOSE'])) {
            /*
                CURLINFO_HEADER_OUT doesn't work with verbose...
                but we can get the request headers from the verbose output
            */
            $pointer = $options['CURLOPT_STDERR'];
            \rewind($pointer);
            $verboseOutput = \stream_get_contents($pointer);
            $matches = array();
            \preg_match_all('/> (.*?)\r\n\r\n/s', $verboseOutput, $matches);
            $this->rawRequestHeaders = \end($matches[1]);
            $this->requestHeaders = $this->reflection['parseReqHeaders']->invoke($this, $this->rawRequestHeaders);
        }
        $this->logRequestResponse($verboseOutput, $options);
        $this->debug->groupEnd();
        return $return;
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
        $options = $this->reflection['options']->getValue($this);

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
        \ksort($opts);
        return $opts;
    }

    /**
     * Get the http method used (GET, POST, etc)
     *
     * @param array $options our human readable curl options
     *
     * @return string
     */
    private function getHttpMethod($options)
    {
        $method = 'GET';
        if (isset($options['CURLOPT_CUSTOMREQUEST'])) {
            $method = $options['CURLOPT_CUSTOMREQUEST'];
        } elseif (!empty($options['CURLOPT_POST'])) {
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
        $body = $this->rawResponse;
        if (\strlen($body) === 0) {
            return null;
        }
        return $this->debugOptions['prettyResponseBody']
            ? $this->debug->prettify($body, $this->responseHeaders['content-type'])
            : $body;
    }

    /**
     * Log errors, redirects, request headers, response headers, response body, etc
     *
     * @param string $verboseOutput verbose output
     * @param array  $options       Curl options used for request
     *
     * @return void
     */
    private function logRequestResponse($verboseOutput, $options)
    {
        $duration = $this->debug->timeEnd($this->debugOptions['label'], false);
        $this->debug->log('request headers', $this->rawRequestHeaders, $this->debug->meta('redact'));
        // Curl provides no means to get the request body
        if ($this->error) {
            $this->debug->backtrace->addInternalClass('Curl\\');
            $this->debug->warn($this->errorCode, $this->errorMessage);
        }
        if ($this->effectiveUrl !== $options['CURLOPT_URL']) {
            \preg_match_all('/^(Location:|URI: )(.*?)\r\n/im', $this->rawResponseHeaders, $matches);
            $this->debug->log('Redirect(s)', $matches[2]);
        }
        $this->debug->time($duration);
        $this->debug->log('response headers', $this->rawResponseHeaders, $this->debug->meta('redact'));
        if ($this->debugOptions['inclResponseBody']) {
            $this->debug->log(
                'response body',
                $this->getResponseBody(),
                $this->debug->meta('redact')
            );
        }
        if ($this->debugOptions['inclInfo']) {
            $this->debug->log('info', $this->getInfo());
        }
        if ($verboseOutput) {
            $this->debug->log('verbose', $verboseOutput);
        }
    }
}
