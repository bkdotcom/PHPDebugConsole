<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Backtrace;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
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
    private $serverParams = array();

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->debug->eventManager->addSubscriberInterface($this);
        // see also self::init()
    }

    /**
     * Append logEntry
     * Adds default arguments and "stringifies"
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doGroup(LogEntry $logEntry)
    {
        if (!$logEntry['args']) {
            // give a default label
            $logEntry['args'] = array( 'group' );
            $caller = $this->debug->backtrace->getCallerInfo(0, Backtrace::INCL_ARGS);
            $args = $this->doGroupAutoArgs($caller);
            if ($args) {
                $logEntry['args'] = $args;
                $logEntry->setMeta('isFuncName', true);
            }
        }
        $this->doGroupStringify($logEntry);
        $this->debug->log($logEntry);
    }

    /**
     * Log timeEnd() and timeGet()
     *
     * @param float|false $elapsed  elapsed time in seconds
     * @param LogEntry    $logEntry LogEntry instance
     *
     * @return void
     */
    public function doTime($elapsed, LogEntry $logEntry)
    {
        $meta = $logEntry['meta'];
        if ($meta['silent']) {
            return;
        }
        $label = isset($logEntry['args'][0])
            ? $logEntry['args'][0]
            : 'time';
        $str = $elapsed === false
            ? 'Timer \'' . $label . '\' does not exist'
            : \strtr($meta['template'], array(
                '%label' => $label,
                '%time' => $this->debug->utility->formatDuration($elapsed, $meta['unit'], $meta['precision']),
            ));
        $this->debug->log(new LogEntry(
            $this->debug,
            'time',
            array($str),
            \array_diff_key($meta, \array_flip(array('precision','silent','template','unit')))
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
            $key = $error['inConsole'] ? 'inConsole' : 'notInConsole';
            $stats['counts'][$category][$key]++;
        }
        foreach ($stats['counts'] as $a) {
            $stats['inConsole'] += $a['inConsole'];
            $stats['notInConsole'] += $a['notInConsole'];
            if ($a['inConsole'] > 0) {
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
     * @param 'auto'|'main'|int $where 'auto', 'main' or summary priority
     *
     * @return LogEntry[] kwys are maintained
     */
    public function getCurrentGroups($where = 'auto')
    {
        if ($where === 'auto') {
            $where = $this->getCurrentPriority();
        }

        /*
            Determine current depth
        */
        $curDepth = 0;
        $groupStacks = $this->debug->getData(array('groupStacks', $where));
        foreach ($groupStacks as $group) {
            $curDepth += (int) $group['collect'];
        }

        $entries = array();
        /*
            curDepth will fluctuate as we go back through log
            minDepth will decrease as we work our way down/up the groups
        */
        $logEntries = $where === 'main'
            ? $this->debug->getData(array('log'))
            : $this->debug->getData(array('logSummary', $where));
        $minDepth = $curDepth;
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
     * GEt current group priority
     *
     * @return 'main'|int
     */
    public function getCurrentPriority()
    {
        $priorityStack = $this->debug->getData('groupPriorityStack');
        $priority = \end($priorityStack);
        return $priority !== false
            ? $priority
            : 'main';
    }

    /**
     * Determine default route
     *
     * @return string
     */
    public function getDefaultRoute()
    {
        $interface = $this->getInterface();
        if (\strpos($interface, 'ajax') !== false) {
            return $this->debug->getCfg('routeNonHtml', Debug::CONFIG_DEBUG);
        }
        if ($interface === 'http') {
            $contentType = \implode(', ', $this->getResponseHeader('Content-Type'));
            if ($contentType && \strpos($contentType, 'text/html') === false) {
                return $this->debug->getCfg('routeNonHtml', Debug::CONFIG_DEBUG);
            }
            return 'html';
        }
        return 'stream';
    }

    /**
     * Returns cli, cron, ajax, or http
     *
     * @return string cli | "cli cron" | http | "http ajax"
     */
    public function getInterface()
    {
        $return = 'http';
        /*
            notes:
                $_SERVER['argv'] could be populated with query string if register_argc_argv = On
                don't use request->getMethod()... Psr7 implementation likely defaults to GET
                we used to check for `defined('STDIN')`,
                    but it's not unit test friendly
                we used to check for getServerParam['REQUEST_METHOD'] === null
                    not particularly psr7 friendly
        */
        $argv = $this->getServerParam('argv');
        $isCliOrCron = $argv && \implode('+', $argv) !== $this->getServerParam('QUERY_STRING');
        if ($isCliOrCron) {
            // TERM is a linux/unix thing
            $return = $this->getServerParam('TERM') !== null || $this->getServerParam('PATH') !== null
                ? 'cli'
                : 'cli cron';
        } elseif ($this->getServerParam('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') {
            $return = 'http ajax';
        }
        return $return;
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
            'routeStream',
        )));
        $cfg['debug'] = \array_diff_key($cfg['debug'], \array_flip(array(
            'channelIcon',
            'onBootstrap',
            'route',
        )));
        if (isset($cfg['debug']['services'])) {
            $cfg['debug']['services'] = \array_intersect_key($cfg['debug']['services'], \array_flip(array(
                // these services aren't tied to a debug channel instance... allow inheritance
                'backtrace',
                'html',
                'methodClear',
                'methodTable',
                'request',
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
        $response = $this->debug->response;
        return $response
            ? $response->getStatusCode()
            : \http_response_code();
    }

    /**
     * Return the response header value(s) for specified header
     *
     * Header value is pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, value is pulled from emitted headers via `headers_list()`
     *
     * @param string $header ('Content-Type') header to return
     *
     * @return array
     */
    public function getResponseHeader($header = 'Content-Type')
    {
        $headers = $this->getResponseHeaders();
        if (isset($headers['header'])) {
            return $headers[$header];
        }
        $header = \strtolower($header);
        foreach ($headers as $k => $v) {
            if ($header === \strtolower($k)) {
                return $v;
            }
        }
        return array();
    }

    /**
     * Return all header values
     *
     * Header values are pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, values are pulled from emitted headers via `headers_list()`
     *
     * @param bool $asString return as a single string/block of headers?
     *
     * @return array|string
     */
    public function getResponseHeaders($asString = false)
    {
        $response = $this->debug->response;
        $headers = $response
            ? $response->getHeaders()
            : $this->debug->utility->getEmittedHeaders();
        if (!$asString) {
            return $headers;
        }
        $protocol = $this->getServerParam('SERVER_PROTOCOL') ?: 'HTTP/1.0';
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
     * Get $_SERVER param
     * Gets serverParams from serverRequest interface
     *
     * @param string $name    $_SERVER key/name
     * @param mixed  $default default value
     *
     * @return mixed
     */
    public function getServerParam($name, $default = null)
    {
        if ($this->debug->parentInstance) {
            // we are a child channel
            // re-call via rootInstance so we use the same serverParams cache
            return $this->debug->rootInstance->getServerParam($name, $default);
        }
        if (!$this->serverParams) {
            $request = $this->debug->request;
            $this->serverParams = $request->getServerParams();
        }
        return \array_key_exists($name, $this->serverParams)
            ? $this->serverParams[$name]
            : $default;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        if ($this->debug->parentInstance) {
            // we are a child channel
            return array(
                Debug::EVENT_CONFIG => array('onConfig', PHP_INT_MAX),
            );
        }
        // root instance
        return array(
            Debug::EVENT_CONFIG => array('onConfig', PHP_INT_MAX),
            Debug::EVENT_BOOTSTRAP => array('onBootstrap', PHP_INT_MAX * -1),
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
     * Initialize
     * This is not called from __construct as we may call debug->internal which hasn't been set yet
     *
     * @return void
     */
    public function init()
    {
        /*
            Initial setCfg has already occured... so we missed the initial Debug::EVENT_CONFIG event
            manually call onConfig here
        */
        $cfgInit = $this->debug->getCfg(null, Debug::CONFIG_INIT);
        $cfgEvent = new Event(
            $this->debug,
            $cfgInit
        );
        $this->onConfig($cfgEvent);
        if ($this->debug->parentInstance) {
            return;
        }
        if ($cfgEvent['debug'] !== $cfgInit['debug']) {
            /*
                config changed via onConfig
                publish Debug::EVENT_CONFIG event so event listeners will get the change
            */
            $cfgEvent['isInternalUpdate'] = true;
            $this->debug->eventManager->publish(
                Debug::EVENT_CONFIG,
                $cfgEvent
            );
        }
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
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
        $this->debug->addPlugin($this->debug->logEnv);
        $this->debug->addPlugin($this->debug->logReqRes);
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
        $cfg = $event->getValues();
        if (isset($cfg['routeStream']['stream'])) {
            $this->debug->addPlugin($this->debug->getRoute('stream'));
        }
        if (empty($cfg['debug'])) {
            // no debug config values have changed
            return;
        }
        if ($event['isInternalUpdate']) {
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
            'route' => array($this, 'onCfgRoute'),
        );
        foreach ($valActions as $key => $callable) {
            if (isset($cfg[$key])) {
                /** @psalm-suppress TooManyArguments */
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
                FileStreamWrapper::setEventManager($this->debug->eventManager);
                FileStreamWrapper::setPathsExclude(array(
                    __DIR__,
                ));
                FileStreamWrapper::register();
            }
        }
    }

    /**
     * Prettify string
     *
     * format whitepace
     *    json, xml  (or anything else handled via Debug::EVENT_PRETTIFY)
     * add attributes to indicate value should be syntax highlighted
     *    html, json, xml
     *
     * @param string $string      string to prettify]
     * @param string $contentType mime type
     *
     * @return Abstraction|string
     */
    public function prettify($string, $contentType)
    {
        $event = $this->debug->rootInstance->eventManager->publish(
            Debug::EVENT_PRETTIFY,
            $this->debug,
            array(
                'value' => $string,
                'contentType' => $contentType,
            )
        );
        return $event['value'];
    }

    /**
     * Publish/Trigger/Dispatch event
     * Event will get published on ancestor channels if propagation not stopped
     *
     * @param string $eventName Event name
     * @param Event  $event     Event instance
     * @param Debug  $debug     Specify Debug instance to start on.
     *                            If not specified will check if getSubject returns Debug instance
     *                            Fallback: this->debug
     *
     * @return Event
     */
    public function publishBubbleEvent($eventName, Event $event, Debug $debug = null)
    {
        if ($debug === null) {
            $subject = $event->getSubject();
            /** @var Debug */
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
            if ($val['type'] === Abstracter::TYPE_OBJECT) {
                $val['properties'] = $this->redact($val['properties']);
                $val['stringified'] = $this->redact($val['stringified']);
                if (isset($val['methods']['__toString']['returnValue'])) {
                    $val['methods']['__toString']['returnValue'] = $this->redact($val['methods']['__toString']['returnValue']);
                }
                return $val;
            }
            if ($val['value']) {
                $val['value'] = $this->redact($val['value']);
            }
            if ($val['valueDecoded']) {
                $val['valueDecoded'] = $this->redact($val['valueDecoded']);
            }
            return $val;
        }
        if (\is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = $this->redact($v, $k);
            }
        }
        return $val;
    }

    /**
     * Generate a unique request id
     *
     * @return string
     */
    public function requestId()
    {
        $unique = \md5(\uniqid((string) \rand(), true));
        return \hash(
            'crc32b',
            $this->getServerParam('REMOTE_ADDR', 'terminal')
                . ($this->getServerParam('REQUEST_TIME_FLOAT') ?: $unique)
                . $this->getServerParam('REMOTE_PORT', '')
        );
    }

    /**
     * Automatic group/groupCollapsed arguments
     *
     * @param array $caller CallerInfo
     *
     * @return array
     */
    private function doGroupAutoArgs($caller = array())
    {
        $args = array();
        if (isset($caller['function']) === false) {
            return $args;
        }
        // default args if first call inside function... and debugGroup is likely first call
        $function = null;
        $callerStartLine = 1;
        if ($caller['class']) {
            $refClass = new \ReflectionClass($caller['class']);
            $refMethod = $refClass->getMethod($caller['function']);
            $callerStartLine = $refMethod->getStartLine();
            $function = $caller['class'] . $caller['type'] . $caller['function'];
        } elseif (!\in_array($caller['function'], array('include', 'include_once', 'require', 'require_once'))) {
            $refFunction = new \ReflectionFunction($caller['function']);
            $callerStartLine = $refFunction->getStartLine();
            $function = $caller['function'];
        }
        if ($function && $caller['line'] <= $callerStartLine + 2) {
            $args[] = $function;
            $args = \array_merge($args, $caller['args']);
            // php < 7.0 debug_backtrace args are references!
            $args = $this->debug->utility->arrayCopy($args, false);
        }
        return $args;
    }

    /**
     * Use string representation for group args if available
     *
     * @param LogEntry $logEntry Log entry
     *
     * @return void
     */
    private function doGroupStringify(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $abstracter = $this->debug->abstracter;
        foreach ($args as $k => $v) {
            /*
                doGroupStringify is called before appendLog.
                values have not yet been abstracted.
                abstract now
            */
            $typeInfo = $abstracter->getType($v);
            if ($typeInfo[0] !== Abstracter::TYPE_OBJECT) {
                continue;
            }
            $v = $abstracter->crate($v, $logEntry['method']);
            if ($v['stringified']) {
                $v = $v['stringified'];
            } elseif (isset($v['methods']['__toString']['returnValue'])) {
                $v = $v['methods']['__toString']['returnValue'];
            }
            $args[$k] = $v;
        }
        $logEntry['args'] = $args;
    }

    /**
     * Test $_REQUEST['debug'] against passed configured key
     * Update collect & output values based on key value
     *
     * @param string $key   configured debug key
     * @param Event  $event Debug::EVENT_CONFIG event instance
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgKey($key, Event $event)
    {
        if (\strpos($this->getInterface(), 'cli') !== false) {
            return;
        }
        $request = $this->debug->request;
        $cookieParams = $request->getCookieParams();
        $queryParams = $request->getQueryParams();
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
        $this->debug->eventManager->subscribe(Debug::EVENT_BOOTSTRAP, $val);
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
     * Set route value
     * instantiate object if necessary & addPlugin if not already subscribed
     *
     * @param RouteInterface|string $route RouteInterface instance, or (short) classname
     * @param Event                 $event Event instance
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRoute($route, Event $event)
    {
        if ($this->isBootstraped) {
            /*
                Only need to worry about previous route if we're bootstrapped
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
            $route = $this->debug->getRoute($route);
        }
        if ($route instanceof RouteInterface) {
            $this->debug->addPlugin($route);
            $event['debug']['route'] = $route;
        }
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
            . '<(?:\w+:)?' . $key . '\b.*?>\s*([^<]*?)\s*</(?:\w+:)?' . $key . '>'
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
}
