<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use Curl\Curl;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Extend php-curl-class to log each request
 *
 * @see https://github.com/php-curl-class/php-curl-class
 */
class PhpCurlClass extends Curl
{
    private $debug;
    private $icon = 'fa fa-exchange';
    private $optionsDebug = array(
        'inclResponseBody' => false,
        'prettyResponseBody' => true,
        'inclInfo' => false,
        'verbose' => false,
    );

    public $rawRequestHeaders = '';

    /**
     * @var array constant value to array of names
     */
    protected static $optionConstants = array();

    /**
     * Constructor
     *
     * @param array $options options
     * @param Debug $debug   (optional) Specify PHPDebugConsole instance
     *                        if not passed, will create Curl channnel on singleton instance
     *                        if root channel is specified, will create a Curl channel
     */
    public function __construct($options = array(), Debug $debug = null)
    {
        $this->optionsDebug = \array_merge($this->optionsDebug, $options);
        if (!$debug) {
            $debug = Debug::_getChannel('Curl', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Curl', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $this->buildConstLookup();
        parent::__construct();
        if ($options['verbose']) {
            $this->verbose(true, \fopen('php://temp', 'rw'));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exec($ch = null)
    {
        $options = $this->buildDebugOptions();
        $this->debug->groupCollapsed(
            'Curl',
            $this->getHttpMethod($options),
            $options['CURLOPT_URL'],
            $this->debug->meta('icon', $this->icon)
        );
        $this->debug->log('options', $options);
        $return = parent::exec($ch);
        $verboseOutput = null;
        if (!empty($options['CURLOPT_VERBOSE'])) {
            /*
                CURLINFO_HEADER_OUT doesn't work with verbose...
                but we can get the request headers from the verbose output
            */
            $pointer = $options['CURLOPT_STDERR'];
            \rewind($pointer);
            $verboseOutput = \stream_get_contents($pointer);
            \preg_match_all('/> (.*?)\r\n\r\n/s', $verboseOutput, $matches);
            $this->rawRequestHeaders = \end($matches[1]);
            $parseReqHeadersRef = new ReflectionMethod($this, 'parseRequestHeaders');
            $parseReqHeadersRef->setAccessible(true);
            $this->requestHeaders = $parseReqHeadersRef->invoke($this, $this->rawRequestHeaders);
        } else {
            $this->rawRequestHeaders = $this->getInfo(CURLINFO_HEADER_OUT);
        }
        if ($this->error) {
            $this->debug->warn($this->errorCode, $this->errorMessage);
        }
        if ($this->effectiveUrl !== $options['CURLOPT_URL']) {
            \preg_match_all('/^(Location:|URI: )(.*?)\r\n/im', $this->rawResponseHeaders, $matches);
            $this->debug->log('Redirect(s)', $matches[2]);
        }
        $this->debug->log('request headers', $this->rawRequestHeaders, $this->debug->meta('redact'));
        // Curl provides no means to get the request body
        $this->debug->log('response headers', $this->rawResponseHeaders, $this->debug->meta('redact'));
        if ($this->optionsDebug['inclResponseBody']) {
            $body = $this->getResponseBody();
            $this->debug->log(
                'response body %c%s',
                'font-style: italic; opacity: 0.8;',
                $body instanceof Abstraction
                    ? '(prettified)'
                    : '',
                $body,
                $this->debug->meta('redact')
            );
        }
        if ($this->optionsDebug['inclInfo']) {
            $this->debug->log('info', $this->getInfo());
        }
        if ($verboseOutput) {
            $this->debug->log('verbose', $verboseOutput);
        }
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
        $consts = \get_defined_constants(true)['curl'];
        \ksort($consts);
        $valToNames = array();
        foreach ($consts as $name => $val) {
            if (\strpos($name, 'CURLOPT') !== 0 && $name !== 'CURLINFO_HEADER_OUT') {
                continue;
            }
            if (!isset($valToNames[$val])) {
                $valToNames[$val] = array();
            }
            $valToNames[$val][] = $name;
        }
        \ksort($valToNames);
        self::$optionConstants = $valToNames;
    }

    /**
     *  Build an array of human-readable options used
     *
     * @return array
     */
    private function buildDebugOptions()
    {
        $classRef = new ReflectionClass($this);
        $parent = $classRef->getParentClass()->getName();
        $optionsRef = new ReflectionProperty($parent, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($this);

        $opts = array();
        foreach ($options as $constVal => $val) {
            $name = \implode(' / ', self::$optionConstants[$constVal]);
            $opts[$name] = $val;
        }
        if (isset($opts['CURLOPT_POSTFIELDS']) && \is_string($opts['CURLOPT_POSTFIELDS'])) {
            \parse_str($opts['CURLOPT_POSTFIELDS'], $opts['CURLOPT_POSTFIELDS']);
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
     * @return Abstraction|string|null
     */
    private function getResponseBody()
    {
        $body = $this->rawResponse;
        if (\strlen($body) === 0) {
            return null;
        }
        if ($this->optionsDebug['prettyResponseBody']) {
            $event = $this->debug->rootInstance->eventManager->publish('debug.prettify', $this, array(
                'value' => $body,
                'contentType' => $this->responseHeaders['content-type'],
            ));
            return $event['value'];
        }
        return $body;
    }
}
