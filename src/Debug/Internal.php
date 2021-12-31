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

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Psr7lite\Response;
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
    /**
     * duplicate/store frequently used cfg vals
     *
     * @var array
     */
    private $cfg = array(
        'collect' => false,
    );

    private $debug;
    private $isConfigured = false;
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
    }


    /**
     * Does alert contain substitutions
     *
     * @param array $args alert arguments
     *
     * @return bool
     */
    public function alertHasSubstitutions(array $args)
    {
        /*
            Create a temporary LogEntry so we can test if we passed substitutions
        */
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            $args
        );
        $levelsAllowed = array('danger','error','info','success','warn','warning');
        return $logEntry->containsSubstitutions() && \array_key_exists(1, $args) && !\in_array($args[1], $levelsAllowed);
    }

    /**
     * Set alert()'s alert level'\
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function alertLevel(LogEntry $logEntry)
    {
        $level = $logEntry->getMeta('level');
        $levelsAllowed = array('danger','error','info','success','warn','warning');
        /*
            Continue to allow bootstrap "levels"
        */
        $levelTrans = array(
            'danger' => 'error',
            'warning' => 'warn',
        );
        if (isset($levelTrans[$level])) {
            $level = $levelTrans[$level];
        } elseif (!\in_array($level, $levelsAllowed)) {
            $level = 'error';
        }
        $logEntry->setMeta('level', $level);
    }

    /**
     * Store the arguments
     * if collect is false -> does nothing
     * otherwise:
     *   + abstracts values
     *   + publishes Debug::EVENT_LOG event
     *   + appends log (if event propagation not stopped)
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool whether or not entry got appended
     */
    public function appendLog(LogEntry $logEntry)
    {
        if (!$this->cfg['collect'] && !$logEntry['forcePublish']) {
            return false;
        }
        $cfgRestore = array();
        if (isset($logEntry['meta']['cfg'])) {
            $cfgRestore = $this->debug->setCfg($logEntry['meta']['cfg']);
            $logEntry->setMeta('cfg', null);
        }
        if (\count($logEntry['args']) === 1 && $this->debug->utility->isThrowable($logEntry['args'][0])) {
            $exception = $logEntry['args'][0];
            $logEntry['args'][0] = $exception->getMessage();
            $logEntry->setMeta(array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->debug->backtrace->get(null, 0, $exception),
            ));
        }
        $logEntry->crate();
        $this->publishBubbleEvent(Debug::EVENT_LOG, $logEntry);
        if ($cfgRestore) {
            $this->debug->setCfg($cfgRestore);
        }
        if ($logEntry['appendLog']) {
            $this->debug->data->appendLog($logEntry);
            return true;
        }
        return false;
    }

    /**
     * Handle error & warn methods
     *
     * @param string $method "error" or "warn"
     * @param array  $args   arguments passed to error or warn
     *
     * @return void
     */
    public function doError($method, $args)
    {
        $logEntry = new LogEntry(
            $this->debug,
            $method,
            $args,
            array(
                'detectFiles' => true,
                'uncollapse' => true,
            )
        );
        // file & line meta may already be set (ie coming via errorHandler)
        // file & line may also be defined as null
        $default = "\x00default\x00";
        if ($logEntry->getMeta('file', $default) === $default) {
            $callerInfo = $this->debug->backtrace->getCallerInfo();
            $logEntry->setMeta(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ));
        }
        $this->debug->log($logEntry);
    }

    /**
     * Handle trace()
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doTrace(LogEntry $logEntry)
    {
        $caption = $logEntry->getMeta('caption');
        if (!\is_string($caption)) {
            $this->warn(\sprintf(
                'trace caption should be a string.  %s provided',
                \is_object($caption)
                    ? \get_class($caption)
                    : \gettype($caption)
            ));
            $logEntry->setMeta('caption', 'trace');
        }
        // Get trace and include args if we're including context
        $inclContext = $logEntry->getMeta('inclContext');
        $inclArgs = $logEntry->getMeta('inclArgs');
        $backtrace = isset($logEntry['meta']['trace'])
            ? $logEntry['meta']['trace']
            : $this->debug->backtrace->get($inclArgs ? \bdk\Backtrace::INCL_ARGS : 0);
        $logEntry->setMeta('trace', null);
        if ($backtrace && $inclContext) {
            $backtrace = $this->debug->backtrace->addContext($backtrace);
            $this->debug->addPlugin(new \bdk\Debug\Plugin\Highlight());
        }
        $logEntry['args'] = array($backtrace);
        $this->debug->methodTable->doTable($logEntry);
        $this->debug->log($logEntry);
    }

    /**
     * Get error statistics from errorHandler
     * how many errors were captured in/out of console
     * breakdown per error category
     *
     * @return array
     */
    public function errorStats()
    {
        $stats = array(
            'inConsole' => 0,
            'inConsoleCategories' => array(),
            'notInConsole' => 0,
            'counts' => \array_fill_keys(
                array('fatal','error','warning','deprecated','notice','strict'),
                array('inConsole' => 0, 'notInConsole' => 0, 'suppressed' => 0)
            )
        );
        foreach ($this->debug->errorHandler->get('errors') as $error) {
            $category = $error['category'];
            $key = $error['inConsole']
                ? 'inConsole'
                : 'notInConsole';
            $stats[$key]++;
            $stats['counts'][$category][$key]++;
            $stats['counts'][$category]['suppressed'] += (int) $error['isSuppressed'];
            if ($key === 'inConsole') {
                $stats['inConsoleCategories'][] = $category;
            }
        }
        $stats['inConsoleCategories'] = \array_unique($stats['inConsoleCategories']);
        return $stats;
    }

    /**
     * Determine default route
     *
     * @return string
     */
    public function getDefaultRoute()
    {
        $interface = $this->debug->getInterface();
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
     *
     * @psalm-return ($asString is false ? array : string)
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
        $code = $this->getResponseCode();
        $phrase = Response::codePhrase($code);
        $headersAll = array(
            $protocol . ' ' . $code . ' ' . $phrase,
        );
        foreach ($headers as $k => $vals) {
            foreach ($vals as $val) {
                $headersAll[] = $k . ': ' . $val;
            }
        }
        return \join("\n", $headersAll);
    }

    /**
     * Get $_SERVER param/value
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
        return array(
            Debug::EVENT_CONFIG => array('onConfig', PHP_INT_MAX),
        );
    }

    /**
     * Create config meta argument/value
     *
     * @param string|array $key key or array of key/values
     * @param mixed        $val config value
     *
     * @return array
     */
    public function metaCfg($key, $val)
    {
        if (\is_array($key)) {
            return array(
                'cfg' => $key,
                'debug' => Debug::META,
            );
        }
        if (\is_string($key)) {
            return array(
                'cfg' => array(
                    $key => $val,
                ),
                'debug' => Debug::META,
            );
        }
        // invalid cfg key / return empty meta array
        return array('debug' => Debug::META);
    }

    /**
     * Flush the buffer and end buffering
     *
     * @return void
     */
    public function obEnd()
    {
        if ($this->debug->data->get('isObCache') === false) {
            return;
        }
        if (\ob_get_level()) {
            \ob_end_flush();
        }
        $this->debug->data->set('isObCache', false);
    }

    /**
     * Conditionally start output buffering
     *
     * @return void
     */
    public function obStart()
    {
        if ($this->debug->data->get('isObCache')) {
            return;
        }
        if ($this->debug->rootInstance->getCfg('collect', Debug::CONFIG_DEBUG) !== true) {
            return;
        }
        \ob_start();
        $this->debug->data->set('isObCache', true);
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
        if (isset($configs['routeStream']['stream'])) {
            $this->debug->addPlugin($this->debug->getRoute('stream'));
        }
        if (empty($configs['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfgDebug = $this->onConfigInit($configs['debug']);
        $valActions = array(
            'serviceProvider' => array($this, 'onCfgServiceProvider'),
            'collect' => function ($val) {
                $this->cfg['collect'] = $val;
                return $val;
            },
        );
        $valActions = \array_intersect_key($valActions, $cfgDebug);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfgDebug[$key] = $callable($cfgDebug[$key], $event);
        }
        $event['debug'] = \array_merge($event['debug'], $cfgDebug);
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
     * Publish Debug::EVENT_OUTPUT
     *    on all descendant channels
     *    rootInstance
     *    finally ourself
     * This isn't outputing each channel, but for performing any per-channel "before output" activities
     *
     * @return string output
     */
    public function publishOutputEvent()
    {
        $debug = $this->debug;
        $channels = $debug->getChannels(true);
        if ($debug !== $debug->rootInstance) {
            $channels[] = $debug->rootInstance;
        }
        $channels[] = $debug;
        foreach ($channels as $channel) {
            $event = $channel->eventManager->publish(
                Debug::EVENT_OUTPUT,
                $channel,
                array(
                    'headers' => array(),
                    'isTarget' => $channel === $debug,
                    'return' => '',
                )
            );
        }
        return $event['return'];
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
     * Create timeEnd & timeGet LogEntry
     *
     * @param string $method 'timeEnd' or 'timeGet'
     * @param array  $args   arguments passed to method
     *
     * @return LogEntry
     */
    public function timeLogEntry($method, $args)
    {
        $logEntry = new LogEntry(
            $this->debug,
            $method,
            $args,
            array(
                'precision' => 4,
                'silent' => false,
                'template' => '%label: %time',
                'unit' => 'auto',
            ),
            array(
                'label' => null,
                'log' => true,
            )
        );
        list($label, $log) = $logEntry['args'];
        $silent = !$log;
        if ($logEntry['numArgs'] === 1 && \is_bool($label)) {
            // $log passed as single arg
            $silent = !$label;
            $logEntry['args'] = array(null, $label);
        }
        $logEntry->setMeta('silent', $silent || $logEntry->getMeta('silent'));
        return $logEntry;
    }

    /**
     * Handle updates to serviceProvider here
     * We need to clear serverParamCache
     *
     * @param mixed $val ServiceProvider value
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgServiceProvider($val)
    {
        $this->serverParams = array();
        return $this->debug->onCfgServiceProvider($val);
    }

    /**
     * Merge in default config values if not yet configured
     *
     * @param array $cfg Config vals being updated
     *
     * @return array
     */
    private function onConfigInit($cfg)
    {
        if ($this->isConfigured) {
            return $cfg;
        }
        $this->isConfigured = true;
        return \array_merge(
            \array_diff_key(
                // remove current collect & output values,
                //   so don't trigger updates for existing values
                $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
                \array_flip(array('collect','output'))
            ),
            $cfg
        );
    }
}
