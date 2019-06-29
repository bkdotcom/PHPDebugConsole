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
use Curl\Curl;

/**
 * Extend php-curl-class to log each request
 *
 * @see https://github.com/php-curl-class/php-curl-class
 */
class PhpCurlClass extends Curl
{
    private $debug;
    private $icon = 'fa fa-exchange';
    private $optionsDebug = array();
    private $optionsConstDebug = array();

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
        $this->optionsDebug = \array_merge(array(
            'inclInfo' => false,
            'verbose' => false,
        ), $options);
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
    public function close()
    {
        parent::close();
        $this->optionsConstDebug = array();
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
        if ($this->error) {
            $this->debug->warn($this->errorCode, $this->errorMessage);
        }
        $this->debug->log('request headers', $this->getInfo(CURLINFO_HEADER_OUT));
        $this->debug->log('response headers', $this->rawResponseHeaders);
        if ($this->optionsDebug['inclInfo']) {
            $this->debug->log('info', $this->getInfo());
        }
        if ($this->optionsDebug['verbose']) {
            $pointer = $options['CURLOPT_STDERR'];
            \rewind($pointer);
            $this->debug->log('verbose', \stream_get_contents($pointer));
        }
        $this->debug->groupEnd();
        return $return;
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
     * {@inheritDoc}
     */
    public function setOpt($option, $value)
    {
        $return = parent::setOpt($option, $value);
        if ($return) {
            $this->optionsConstDebug[$option] = $value;
        }
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
        $opts = array();
        foreach ($this->optionsConstDebug as $constVal => $val) {
            $name = \implode(' / ', self::$optionConstants[$constVal]);
            $opts[$name] = $val;
        }
        if (isset($opts['CURLOPT_POSTFIELDS']) && \is_string($opts['CURLOPT_POSTFIELDS'])) {
            \parse_str($opts['CURLOPT_POSTFIELDS'], $opts['CURLOPT_POSTFIELDS']);
        }
        \ksort($opts);
        return $opts;
    }
}
