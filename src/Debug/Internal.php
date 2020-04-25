<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Route\RouteInterface;
use bdk\Debug\Utility\FileStreamWrapper;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Methods that are internal to the debug class
 *
 * a) Don't want to clutter the debug class
 * b) avoiding a base class as it would necessitate we first load the base or have
 *       an autoloader in place to bootstrap the debug class
 * c) a trait for code not meant to be "reusable" seems like an anti-pattern
 *       doesn't solve the bootstrap/autoload issue
 */
class Internal implements SubscriberInterface
{

    private $debug;

    /**
     * duplicate/store frequently used cfg vals
     *
     * @var array
     */
    private $cfg = array(
        'redactKeys' => array(),
        'redactReplace' => null,
    );

    private $isBootstraped = false;
    private static $profilingEnabled = false;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->debug->eventManager->addSubscriberInterface($this);
        if ($debug->parentInstance) {
            return;
        }
        $this->onConfig(new Event(
            $this->debug,
            array(
                'debug' => $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
            )
        ));
    }

    /**
     * Send an email
     *
     * @param string $toAddr  to
     * @param string $subject subject
     * @param string $body    body
     *
     * @return void
     */
    public function email($toAddr, $subject, $body)
    {
        $addHeadersStr = '';
        $fromAddr = $this->debug->getCfg('emailFrom', Debug::CONFIG_DEBUG);
        if ($fromAddr) {
            $addHeadersStr .= 'From: ' . $fromAddr;
        }
        \call_user_func($this->debug->getCfg('emailFunc', Debug::CONFIG_DEBUG), $toAddr, $subject, $body, $addHeadersStr);
    }

    /**
     * get error statistics from errorHandler
     * how many errors were captured in/out of console
     * breakdown per error category
     *
     * @return array
     */
    public function errorStats()
    {
        $errors = $this->debug->errorHandler->get('errors');
        $stats = array(
            'inConsole' => 0,
            'inConsoleCategories' => 0,
            'notInConsole' => 0,
            'counts' => array(),
        );
        foreach ($errors as $error) {
            if ($error['isSuppressed']) {
                continue;
            }
            $category = $error['category'];
            if (!isset($stats['counts'][$category])) {
                $stats['counts'][$category] = array(
                    'inConsole' => 0,
                    'notInConsole' => 0,
                );
            }
            $k = $error['inConsole'] ? 'inConsole' : 'notInConsole';
            $stats['counts'][$category][$k]++;
        }
        foreach ($stats['counts'] as $a) {
            $stats['inConsole'] += $a['inConsole'];
            $stats['notInConsole'] += $a['notInConsole'];
            if ($a['inConsole']) {
                $stats['inConsoleCategories']++;
            }
        }
        $order = array(
            'fatal',
            'error',
            'warning',
            'deprecated',
            'notice',
            'strict',
        );
        $stats['counts'] = \array_intersect_key(\array_merge(\array_flip($order), $stats['counts']), $stats['counts']);
        return $stats;
    }

    /**
     * Return the group & groupCollapsed ("ancestors")
     *
     * @param array $logEntries log entries
     * @param int   $curDepth   current group depth
     *
     * @return LogEntry[] kwys are maintained
     */
    public static function getCurrentGroups(&$logEntries, $curDepth)
    {
        /*
            curDepth will fluctuate as we go back through log
            minDepth will decrease as we work our way down/up the groups
        */
        $minDepth = $curDepth;
        $entries = array();
        for ($i = \count($logEntries) - 1; $i >= 0; $i--) {
            if ($curDepth < 1) {
                break;
            }
            $method = $logEntries[$i]['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $curDepth--;
                if ($curDepth < $minDepth) {
                    $minDepth--;
                    $entries[$i] = $logEntries[$i];
                }
            } elseif ($method === 'groupEnd') {
                $curDepth++;
            }
        }
        return $entries;
    }

    /**
     * Determine default route
     *
     * @return string
     */
    public function getDefaultRoute()
    {
        $interface = $this->debug->utility->getInterface();
        if (\strpos($interface, 'ajax') !== false) {
            return $this->values['routeNonHtml'];
        }
        if ($interface === 'http') {
            $contentType = $this->getResponseHeader('Content-Type', ',');
            if ($contentType && \strpos($contentType, 'text/html') === false) {
                return $this->debug->getCfg('routeNonHtml', Debug::CONFIG_DEBUG);
            }
            return 'html';
        }
        return 'stream';
    }

    /**
     * Remove config values that should not be propagated to children channels
     *
     * @param array $cfg config array
     *
     * @return array
     */
    public function getPropagateValues($cfg)
    {
        $cfg = \array_diff_key($cfg, \array_flip(array(
            'errorEmailer',
            'errorHandler',
        )));
        unset($cfg['debug']['onBootstrap']);
        unset($cfg['debug']['route']);
        if (isset($cfg['debug']['services'])) {
            $cfg['debug']['services'] = \array_intersect_key($cfg['debug']['services'], \array_flip(array(
                // these services aren't tied to a debug instance... allow inheritance
                'backtrace',
                'html',
                'methodClear',
                'methodTable',
                // 'request', // not tied to instance, but "singleton" returned
                'response',
                'utf8',
                'utility',
            )));
        }
        return $cfg;
    }

    /**
     * Get HTTP response code
     *
     * Status code pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, code pulled via `http_response_code()`
     *
     * @return int Status code
     */
    public function getResponseCode()
    {
        return $this->debug->response
            ? $this->debug->response->getStatusCode()
            : \http_response_code();
    }

    /**
     * Return the response header value(s) for specified header
     *
     * Header value is pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, value is pulled from emitted headers via `headers_list()`
     *
     * @param string $header    ('Content-Type') header to return
     * @param string $delimiter Optional.  If specified, join values.  Otherwise, array is returned
     *
     * @return array|string
     */
    public function getResponseHeader($header = 'Content-Type', $delimiter = false)
    {
        $headers = $this->getResponseHeaders();
        $header = isset($headers[$header])
            ? $headers[$header]
            : array();
        return \is_string($delimiter)
            ? \implode($delimiter, $header)
            : $header;
    }

    /**
     * Return all header values
     *
     * Header values are pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, values are pulled from emitted headers via `headers_list()`
     *
     * @param string $asString return as a single string/block of headers?
     *
     * @return array
     */
    public function getResponseHeaders($asString = false)
    {
        $headers = $this->debug->response
            ? $this->debug->response->getHeaders()
            : $this->debug->utility->getEmittedHeaders();
        if (!$asString) {
            return $headers;
        }
        $serverParams = $this->debug->request->getServerParams();
        $protocol = isset($serverParams['SERVER_PROTOCOL'])
            ? $serverParams['SERVER_PROTOCOL']
            : 'HTTP/1.0';
        $responseCode = $this->getResponseCode();
        $headersAll = array(
            $protocol . ' ' . $responseCode . ' ' . $this->debug->utility->httpStatusPhrase($responseCode),
        );
        foreach ($headers as $k => $vals) {
            foreach ($vals as $val) {
                $headersAll[] = $k . ': ' . $val;
            }
        }
        return \join("\n", $headersAll);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        if ($this->debug->parentInstance) {
            // we are a child channel
            return array(
                'debug.config' => array('onConfig', PHP_INT_MAX),
            );
        }
        // root instance
        return array(
            'debug.config' => array('onConfig', PHP_INT_MAX),
            'debug.bootstrap' => array('onBootstrap', PHP_INT_MAX * -1),
        );
    }

    /**
     * Do we have log entries?
     *
     * @return bool
     */
    public function hasLog()
    {
        $entryCountInitial = $this->debug->getData('entryCountInitial');
        $entryCountCurrent = $this->debug->getData('log/__count__');
        $lastEntryMethod = $this->debug->getData('log/__end__/method');
        return $entryCountCurrent > $entryCountInitial && $lastEntryMethod !== 'clear';
    }

    /**
     * debug.bootstrap subscriber
     *
     * @return void
     */
    public function onBootstrap()
    {
        $this->isBootstraped = true;
        $route = $this->debug->getCfg('route');
        if ($route === 'stream') {
            // normally we don't init the route until output
            // but stream needs to begin listening now
            $this->debug->setCfg('route', $route);
        }
        $this->debug->addPlugin(new \bdk\Debug\Plugin\LogEnv());
        $this->debug->addPlugin(new \bdk\Debug\Plugin\LogReqRes());
    }

    /**
     * debug.config subscriber
     *
     * @param Event $event event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event->getValues();
        if (isset($cfg['routeStream']['stream'])) {
            $this->debug->addPlugin($this->debug->routeStream);
        }
        if (empty($cfg['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfg = $cfg['debug'];
        $valActions = array(
            'onBootstrap' => array($this, 'onCfgOnBootstrap'),
            'key' => array($this, 'onCfgKey'),
            'redactKeys' => array($this, 'onCfgRedactKeys'),
            'redactReplace' => function ($val) {
                $this->cfg['redactReplace'] = $val;
            },
            'route' => function ($val, Event $event) {
                $event['debug']['route'] = $this->setRoute($val);
            },
        );
        foreach ($valActions as $key => $callable) {
            if (isset($cfg[$key])) {
                $callable($cfg[$key], $event);
            }
        }
        if (!static::$profilingEnabled) {
            $cfgAll = \array_merge(
                $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
                $cfg
            );
            if ($cfgAll['enableProfiling'] && $cfgAll['collect']) {
                static::$profilingEnabled = true;
                $pathsExclude = array(
                    __DIR__,
                );
                FileStreamWrapper::setEventManager($this->debug->eventManager);
                FileStreamWrapper::setPathsExclude($pathsExclude);
                FileStreamWrapper::register();
            }
        }
    }

    /**
     * Publish/Trigger/Dispatch event
     * Event will get published on ancestor channels if propagation not stopped
     *
     * @param string $eventName event name
     * @param Event  $event     event instance
     * @param Debug  $debug     specify Debug instance to start on
     *                            if not specified will check if getSubject returns Debug instance
     *                            fallback this->debug
     *
     * @return Event
     */
    public function publishBubbleEvent($eventName, Event $event, Debug $debug = null)
    {
        if (!$debug) {
            $subject = $event->getSubject();
            $debug = $subject instanceof Debug
                ? $subject
                : $this->debug;
        }
        do {
            $debug->eventManager->publish($eventName, $event);
            if (!$debug->parentInstance) {
                break;
            }
            $debug = $debug->parentInstance;
        } while (!$event->isPropagationStopped());
        return $event;
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
            if ($val['type'] === 'object') {
                $props = $val['properties'];
                foreach ($props as $name => $prop) {
                    $props[$name]['value'] = $this->redact($prop['value'], $name);
                }
                $val['properties'] = $props;
                $val['stringified'] = $this->redact($val['stringified']);
                if (isset($val['methods']['__toString']['returnValue'])) {
                    $val['methods']['__toString']['returnValue'] = $this->redact($val['methods']['__toString']['returnValue']);
                }
            } elseif ($val['value']) {
                $val['value'] = $this->redact($val['value']);
            }
        }
        if (\is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = $this->redact($v, $k);
            }
        }
        return $val;
    }

    /**
     * Test $_REQUEST['debug'] against passed configured key
     * Update collect & output values based on key value
     *
     * @param string $key   configured debug key
     * @param Event  $event debug.config event instance
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgKey($key, Event $event)
    {
        if (\strpos($this->debug->utility->getInterface(), 'cli') !== false) {
            return;
        }
        $cookieParams = $this->debug->request->getCookieParams();
        $queryParams = $this->debug->request->getQueryParams();
        $requestKey = null;
        if (isset($queryParams['debug'])) {
            $requestKey = $queryParams['debug'];
        } elseif (isset($cookieParams['debug'])) {
            $requestKey = $cookieParams['debug'];
        }
        $isValidKey = $requestKey === $key;
        $valsNew = array();
        if ($isValidKey) {
            // only enable collect / don't disable it
            $valsNew['collect'] = true;
        }
        $valsNew['output'] = $isValidKey;
        $event['debug'] = \array_merge($event['debug'], $valsNew);
    }

    /**
     * Handle "onBootstrap" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnBootstrap($val)
    {
        if ($this->isBootstraped) {
            // boostrap has already occured, so go ahead and call
            \call_user_func($val, new Event($this->debug));
            return;
        }
        // we're bootstraping
        $this->debug->eventManager->subscribe('debug.bootstrap', $val);
    }

    /**
     * Handle "redactKeys" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRedactKeys($val)
    {
        $keys = array();
        foreach ($val as $key) {
            $keys[$key] = $this->redactBuildRegex($key);
        }
        $this->cfg['redactKeys'] = $keys;
    }

    /**
     * Build Regex that will search for key=val in string
     *
     * @param string $key key to redact
     *
     * @return string
     */
    private function redactBuildRegex($key)
    {
        return '#(?:'
            // xml
            . '<' . $key . '\b.*?>\s*([^<]*?)\s*</' . $key . '>'
            . '|'
            // json
            . \json_encode($key) . '\s*:\s*"([^"]*?)"'
            . '|'
            // url encoded
            . '\b' . $key . '=([^\s&]+\b)'
            . ')#i';
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
        if (\is_string($key)) {
            // do exact match against array key or object property
            foreach (\array_keys($this->cfg['redactKeys']) as $redactKey) {
                if ($redactKey === $key) {
                    return \call_user_func($this->cfg['redactReplace'], $val, $key);
                }
            }
        }
        foreach ($this->cfg['redactKeys'] as $key => $regex) {
            $val = \preg_replace_callback($regex, function ($matches) use ($key) {
                $matches = \array_filter($matches, 'strlen');
                $substr = \end($matches);
                $replacement = \call_user_func($this->cfg['redactReplace'], $substr, $key);
                return \str_replace($substr, $replacement, $matches[0]);
            }, $val);
        }
        return $val;
    }

    /**
     * Set route value
     * instantiate object if necessary & addPlugin if not already subscribed
     *
     * @param RouteInterface|string $route RouteInterface instance, or (short) classname
     *
     * @return RouteInterface|string
     */
    private function setRoute($route)
    {
        if ($this->isBootstraped) {
            /*
                Only need to wory about previous route if we're bootstrapped
                There can only be one 'route' at a time:
                If multiple output routes are desired, use debug->addPlugin()
                unsubscribe current OutputInterface
            */
            $routePrev = $this->debug->getCfg('route');
            if (\is_object($routePrev)) {
                $this->debug->removePlugin($routePrev);
            }
        }
        if (\is_string($route) && $route !== 'auto') {
            $prop = 'route' . \ucfirst($route);
            $route = $this->debug->{$prop};
        }
        if ($route instanceof RouteInterface) {
            $this->debug->addPlugin($route);
            $classname = \get_class($route);
            $prefix = __NAMESPACE__ . '\\Route\\';
            if (\strpos($classname, $prefix) === 0) {
                $prop = 'route' . \substr($classname, \strlen($prefix));
                $this->debug->{$prop} = $route;
            }
            return $route;
        }
        return 'auto';
    }
}
