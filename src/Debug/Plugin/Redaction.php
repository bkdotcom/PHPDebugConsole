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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Add additional public methods to debug instance
 */
class Redaction extends AbstractComponent implements SubscriberInterface
{
    use CustomMethodTrait;

    const REPLACEMENT = '█████████';

    /**
     * duplicate/store frequently used cfg vals
     *
     * @var array
     */
    protected $cfg = array(
        'redactKeys' => array(
            // key => regex of key
        ),
        'redactReplace' => null, // closure
        'redactStrings' => array(),
    );
    protected $methods = array(
        'redact',
        'redactHeaders',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cfg['redactReplace'] = static function ($str, $key = null) {
            array($str, $key); // phpmd suppress
            return self::REPLACEMENT;
        };
    }

    /**
     * Add new search string and optional replacement value
     *
     * @param string $search  Search string
     * @param string $replace Optional replacement
     *
     * @return void
     */
    public function addSearchReplace($search, $replace = null)
    {
        $this->cfg['redactStrings'][$search] = $replace;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $configs = $event->getValues();
        if (empty($configs['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfg = \array_intersect_key($configs['debug'], $this->cfg);
        $valActions = \array_intersect_key(array(
            'redactKeys' => array($this, 'onCfgRedactKeys'),
            'redactStrings' => array($this, 'onCfgRedactStrings'),
        ), $cfg);
        $this->cfg = \array_merge($this->cfg, $cfg);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfg[$key] = $callable($cfg[$key], $event);
        }
        $event['debug'] = \array_merge($event['debug'], $cfg);
    }

    /**
     * Redact
     *
     * @param mixed $val value to scrub
     * @param mixed $key array key, or property name
     *
     * @return mixed
     */
    public function redact($val, $key = null)
    {
        if (\is_string($val)) {
            return $this->redactString($val, $key);
        }
        if ($val instanceof Abstraction) {
            return $this->redactAbstraction($val);
        }
        if (\is_array($val)) {
            return $this->redactArray($val);
        }
        return $val;
    }

    /**
     * Redact headers
     *
     * @param array|string $headers Parsed headers or header block
     *
     * @return array|string
     */
    public function redactHeaders($headers)
    {
        $isString = \is_string($headers);
        if ($isString) {
            list($startLine, $headers) = $this->parseHeaders($headers);
        }
        foreach ($headers as $name => $values) {
            foreach ($values as $i => $value) {
                $headers[$name][$i] = $this->redactHeaderValue($name, $value);
            }
        }
        if ($isString) {
            $headers = $this->buildHeaderBlock($headers, $startLine);
        }
        return $headers;
    }

    /**
     * Redact a single header value
     *
     * @param string $name  Header name
     * @param string $value Header value
     *
     * @return string
     */
    public function redactHeaderValue($name, $value)
    {
        if (\in_array($name, array('Authorization', 'Proxy-Authorization'), true) === false) {
            return $this->redactString($value, $name);
        }
        if (\strpos($value, 'Basic') === 0) {
            $auth = \base64_decode(\str_replace('Basic ', '', $value), true);
            $userpass = \explode(':', $auth);
            $replacementShort = \mb_substr(self::REPLACEMENT, 0, 5, 'UTF-8');
            return 'Basic ' . self::REPLACEMENT . ' (base64\'d ' . $userpass[0] . ':' . $replacementShort . ')';
        }
        if (\strpos($value, 'Digest') === 0) {
            return \preg_replace('/(response="?)([^,"]*)("?)/', '$1' . self::REPLACEMENT . '$3', $value);
        }
        if (\strpos($value, 'OAuth') === 0) {
            return \preg_replace('/(oauth_signature="?)([^,"]*)("?)/', '$1' . self::REPLACEMENT . '$3', $value);
        }
        // Bearer or any unknown auth type
        return \preg_replace('/^(\S+ ).+$/', '$1' . self::REPLACEMENT, $value);
    }

    /**
     * Convert header name -> values to string
     *
     * @param array<string, string[]> $headers   Parsed headers
     * @param string|null             $startLine request / status line
     *
     * @return string
     */
    private function buildHeaderBlock($headers, $startLine = null)
    {
        $lines = array();
        if ($startLine) {
            $lines[] = $startLine;
        }
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $lines[] = $name . ': ' . $value;
            }
        }
        return \implode("\r\n", $lines);
    }

    /**
     * Build Regex that will search for key=val in string
     *
     * @param string $key key to redact
     *
     * @return string
     */
    private function buildRegex($key)
    {
        $strlen = \strlen($key);
        return '#(?:'
            // xml
            . '<(?:\w+:)?' . $key . '\b.*?>\s*([^<]*?)\s*</(?:\w+:)?' . $key . '>'
            . '|'
            // json
            . \json_encode($key) . '\s*:\s*"([^"]*?)"'
            . '|'
            // serialized
            . 's:' . $strlen . ':"' . $key . '";s:\d+:"(.*?)";'
            . '|'
            // url encoded
            . '\b' . $key . '=([^\s&]+\b)'
            . ')#i';
    }

    /**
     * Handle "redactKeys" config update
     *
     * @param mixed $val config value
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRedactKeys($val)
    {
        $keys = array();
        foreach ($val as $key) {
            $keys[$key] = $this->buildRegex($key);
        }
        $this->cfg['redactKeys'] = $keys;
        return $val;
    }

    /**
     * Handle "redactStrings" config update
     *
     * @param mixed $val config value
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRedactStrings($val)
    {
        $strings = array();
        foreach ($val as $k => $v) {
            \is_int($k)
                ? $strings[$v] = null
                : $strings[$k] = $v;
        }
        $this->cfg['redactStrings'] = $strings;
        return $val;
    }

    /**
     * Parse header block in to name -> values
     *
     * @param string $headers Header blocks
     *
     * @return array<string, string[]>
     */
    private function parseHeaders($headers)
    {
        $headerLines = \explode("\r\n", \trim($headers));
        $startLine = null;
        $headers = array();
        foreach ($headerLines as $i => $line) {
            if ($i === 0 && \strpos($line, ':') === false) {
                $startLine = $line;
                continue;
            }
            list($name, $value) = \array_replace(array(null, null), \explode(':', $line, 2));
            $name = \trim($name);
            if (isset($headers[$name]) === false) {
                $headers[$name] = array();
            }
            $headers[$name][] = $value !== null
                ? \trim($value)
                : null;
        }
        return array($startLine, $headers);
    }

    /**
     * Redact Abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return Abstraction
     */
    private function redactAbstraction(Abstraction $abs)
    {
        if ($abs['type'] === Abstracter::TYPE_OBJECT) {
            $abs['properties'] = $this->redact($abs['properties']);
            $abs['stringified'] = $this->redact($abs['stringified']);
            if (isset($abs['methods']['__toString']['returnValue'])) {
                $abs['methods']['__toString']['returnValue'] = $this->redact($abs['methods']['__toString']['returnValue']);
            }
            return $abs;
        }
        if ($abs['value']) {
            $abs['value'] = $this->redact($abs['value']);
        }
        if ($abs['valueDecoded']) {
            $abs['valueDecoded'] = $this->redact($abs['valueDecoded']);
        }
        return $abs;
    }

    /**
     * Redact array
     *
     * @param array $array array to redact
     *
     * @return Abstraction
     */
    private function redactArray($array)
    {
        foreach ($array as $k => $v) {
            $array[$k] = $this->redact($v, $k);
        }
        return $array;
    }

    /**
     * Redact string or portions within
     *
     * @param string $val string to redact
     * @param string $key if array value: the key. if object property: the prop name
     *
     * @return string
     */
    private function redactString($val, $key = null)
    {
        if (\is_string($key) && \array_key_exists($key, $this->cfg['redactKeys'])) {
            return \call_user_func($this->cfg['redactReplace'], $val, $key);
        }
        $val = \preg_replace_callback('#(https?://\w+:)(\w+)(@)#', function ($matches) {
            $replacement = \call_user_func($this->cfg['redactReplace'], $matches[2]);
            return $matches[1] . $replacement . $matches[3];
        }, $val);
        foreach ($this->cfg['redactKeys'] as $key => $regex) {
            $val = \preg_replace_callback($regex, function ($matches) use ($key) {
                $matches = \array_filter($matches, 'strlen');
                $substr = \end($matches);
                $replacement = \call_user_func($this->cfg['redactReplace'], $substr, $key);
                return \str_replace($substr, $replacement, $matches[0]);
            }, $val);
        }
        foreach ($this->cfg['redactStrings'] as $search => $replace) {
            $replace = $replace !== null
                ? $replace
                : self::REPLACEMENT;
            $val = \str_replace($search, $replace, $val);
        }
        return $val;
    }
}
