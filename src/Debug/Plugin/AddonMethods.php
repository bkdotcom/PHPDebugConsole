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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\LogEntry;
use bdk\Debug\Psr7lite\HttpFoundationBridge;
use bdk\Debug\Route\RouteInterface;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface; // PSR-7
use SplObjectStorage;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

/**
 * Add additional public methods to debug instance
 */
class AddonMethods implements SubscriberInterface
{

    private $debug;

    private $channels = array();

    /** @var SplObjectHash */
    protected $registeredPlugins;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registeredPlugins = new SplObjectStorage();
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Debug::EVENT_LOG event subscriber
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return void
     */
    public function onCustomMethod(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $methods = array(
            'addPlugin',
            'email',
            'getChannel',
            'getChannels',
            'getChannelsTop',
            'getInterface',
            'hasLog',
            'isCli',
            'removePlugin',
            'writeToResponse',
        );
        if (!\in_array($method, $methods)) {
            return;
        }
        $this->debug = $logEntry->getSubject();
        $logEntry['handled'] = true;
        $logEntry['return'] = \call_user_func_array(array($this, $method), $logEntry['args']);
        $logEntry->stopPropagation();
    }

    /**
     * Extend debug with a plugin
     *
     * @param AssetProviderInterface|SubscriberInterface $plugin object implementing SubscriberInterface and/or AssetProviderInterface
     *
     * @return Debug
     * @throws InvalidArgumentException
     */
    public function addPlugin($plugin)
    {
        $this->assertPlugin($plugin);
        if ($this->registeredPlugins->contains($plugin)) {
            return $this->debug;
        }
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->rootInstance->getRoute('html')->addAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->debug->eventManager->addSubscriberInterface($plugin);
            $subscriptions = $plugin->getSubscriptions();
            if (isset($subscriptions[Debug::EVENT_PLUGIN_INIT])) {
                /*
                    plugin we just added subscribes to Debug::EVENT_PLUGIN_INIT
                    call subscriber directly
                */
                \call_user_func(
                    array($plugin, $subscriptions[Debug::EVENT_PLUGIN_INIT]),
                    new Event($this->debug),
                    Debug::EVENT_PLUGIN_INIT,
                    $this->debug->eventManager
                );
            }
        }
        if ($plugin instanceof RouteInterface) {
            $refMethod = new \ReflectionMethod($this->debug, 'onCfgRoute');
            $refMethod->setAccessible(true);
            $refMethod->invoke($this->debug, $plugin, false);
        }
        $this->registeredPlugins->attach($plugin);
        return $this->debug;
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
        \call_user_func(
            $this->debug->getCfg('emailFunc', Debug::CONFIG_DEBUG),
            $toAddr,
            $subject,
            $body,
            $addHeadersStr
        );
    }

    /**
     * Returns cli, cron, ajax, or http
     *
     * @return string cli | "cli cron" | http | "http ajax"
     */
    public function getInterface()
    {
        /*
            notes:
                $_SERVER['argv'] could be populated with query string if register_argc_argv = On
                don't use request->getMethod()... Psr7 implementation likely defaults to GET
                we used to check for `defined('STDIN')`,
                    but it's not unit test friendly
                we used to check for getServerParam['REQUEST_METHOD'] === null
                    not particularly psr7 friendly
        */
        if ($this->debug->getServerParam('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') {
            return 'http ajax';
        }
        $argv = $this->debug->getServerParam('argv');
        $isCliOrCron = $argv && \implode('+', $argv) !== $this->debug->getServerParam('QUERY_STRING');
        if (!$isCliOrCron) {
            return 'http';
        }
        // TERM is a linux/unix thing
        return $this->debug->getServerParam('TERM') !== null || $this->debug->getServerParam('PATH') !== null
            ? 'cli'
            : 'cli cron';
    }

    /**
     * Do we have log entries?
     *
     * @return bool
     */
    public function hasLog()
    {
        $entryCountInitial = $this->debug->data->get('entryCountInitial');
        $entryCountCurrent = $this->debug->data->get('log/__count__');
        $lastEntryMethod = $this->debug->data->get('log/__end__/method');
        return $entryCountCurrent > $entryCountInitial && $lastEntryMethod !== 'clear';
    }

    /**
     * Is this a Command Line Interface request?
     *
     * @return bool
     */
    public function isCli()
    {
        return \strpos($this->getInterface(), 'cli') === 0;
    }

    /**
     * Return a named sub-instance... if channel does not exist, it will be created
     *
     * Channels can be used to categorize log data... for example, may have a framework channel, database channel, library-x channel, etc
     * Channels may have subchannels
     *
     * @param string $name   channel name
     * @param array  $config channel specific configuration
     *
     * @return static new or existing `Debug` instance
     */
    public function getChannel($name, $config = array())
    {
        // Split on "."
        // Split on "/" not adjacent to whitespace
        $names = \is_string($name)
            ? \preg_split('#(\.|(?<!\s)/(?!\s))#', $name)
            : $name;
        $name = \array_shift($names);
        $config = $names
            ? array()
            : $config;
        if (!isset($this->channels['name'])) {
            $channel = $this->createChannel($name, $config);
            $this->channels[$name] = $channel;
        }
        if ($names) {
            $channel = $channel->getChannel($names);
        }
        unset($config['nested']);
        if ($config) {
            $channel->setCfg($config);
        }
        return $channel;
    }

    /**
     * Return array of channels
     *
     * If $allDescendants == true :  key = "fully qualified" channel name
     *
     * @param bool $allDescendants (false) include all descendants?
     * @param bool $inclTop        (false) whether to incl topmost channels (ie "tabs")
     *
     * @return static[] Does not include self
     */
    public function getChannels($allDescendants = false, $inclTop = false)
    {
        $channels = $this->channels;
        if ($allDescendants) {
            $channels = array();
            foreach ($this->channels as $channel) {
                $channelName = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
                $channels = \array_merge(
                    $channels,
                    array(
                        $channelName => $channel,
                    ),
                    $channel->getChannels(true)
                );
            }
        }
        if ($inclTop) {
            return $channels;
        }
        if ($this->debug === $this->debug->rootInstance) {
            $channelsTop = $this->getChannelsTop();
            $channels = \array_diff_key($channels, $channelsTop);
        }
        return $channels;
    }

    /**
     * Get the topmost channels (ie "tabs")
     *
     * @return static[]
     */
    public function getChannelsTop()
    {
        $channelName = $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $channels = array(
            $channelName => $this->debug,
        );
        if ($this->debug->parentInstance) {
            return $channels;
        }
        foreach ($this->debug->rootInstance->getChannels(false, true) as $name => $channel) {
            $fqn = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
            if (\strpos($fqn, '.') === false) {
                $channels[$name] = $channel;
            }
        }
        return $channels;
    }

    /**
     * Remove plugin
     *
     * @param SubscriberInterface $plugin object implementing SubscriberInterface
     *
     * @return $this
     */
    public function removePlugin(SubscriberInterface $plugin)
    {
        $this->registeredPlugins->detach($plugin);
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->rootInstance->getRoute('html')->removeAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->debug->eventManager->RemoveSubscriberInterface($plugin);
        }
        return $this;
    }

    /**
     * Appends debug output (if applicable) and/or adds headers (if applicable)
     *
     * You should call this at the end of the request/response cycle in your PSR-7 project,
     * e.g. immediately before emitting the Response.
     *
     * @param ResponseInterface|HttpFoundationResponse $response PSR-7 or HttpFoundation response
     *
     * @return ResponseInterface|HttpFoundationResponse
     *
     * @throws InvalidArgumentException
     */
    public function writeToResponse($response)
    {
        if ($response instanceof ResponseInterface) {
            return $this->writeToResponseInterface($response);
        }
        if ($response instanceof HttpFoundationResponse) {
            return $this->writeToHttpFoundationResponse($response);
        }
        throw new InvalidArgumentException(\sprintf(
            'writeToResponse expects ResponseInterface or HttpFoundationResponse, but %s provided',
            \is_object($response) ? \get_class($response) : \gettype($response)
        ));
    }

    /**
     * Write output to HttpFoundationResponse
     *
     * @param HttpFoundationResponse $response HttpFoundationResponse interface
     *
     * @return HttpFoundationResponse
     */
    private function writeToHttpFoundationResponse(HttpFoundationResponse $response)
    {
        $this->debug->setCfg('outputHeaders', false);
        $content = $response->getContent();
        $pos = \strripos($content, '</body>');
        if ($pos !== false) {
            $content = \substr($content, 0, $pos)
                . $this->debug->output()
                . \substr($content, $pos);
            $response->setContent($content);
            // reset the content length
            $response->headers->remove('Content-Length');
        }
        $headers = $this->debug->getHeaders();
        foreach ($headers as $nameVal) {
            $response = $response->headers->set($nameVal[0], $nameVal[1]);
        }
        $this->debug->onCfgServiceProvider(array(
            'response' => HttpFoundationBridge::createResponse($response),
        ));
        return $response;
    }

    /**
     * Write output to PSR-7 ResponseInterface
     *
     * @param ResponseInterface $response ResponseInterface instance
     *
     * @return ResponseInterface
     */
    private function writeToResponseInterface(ResponseInterface $response)
    {
        $this->debug->setCfg('outputHeaders', false);
        $debugOutput = $this->debug->output();
        if ($debugOutput) {
            $stream = $response->getBody();
            $stream->seek(0, SEEK_END);
            $stream->write($debugOutput);
            $stream->rewind();
        }
        $headers = $this->debug->getHeaders();
        foreach ($headers as $nameVal) {
            $response = $response->withHeader($nameVal[0], $nameVal[1]);
        }
        $this->debug->onCfgServiceProvider(array(
            'response' => $response,
        ));
        return $response;
    }

    /**
     * Validate plugin
     *
     * @param [type] $plugin [description]
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertPlugin($plugin)
    {
        $isPlugin = false;
        if ($plugin instanceof AssetProviderInterface) {
            $isPlugin = true;
        }
        if ($plugin instanceof SubscriberInterface) {
            $isPlugin = true;
        }
        if (!$isPlugin) {
            $type = \is_object($plugin)
                ? \get_class($plugin)
                : \gettype($plugin);
            throw new InvalidArgumentException('addPlugin expects \\bdk\\Debug\\AssetProviderInterface and/or \\bdk\\PubSub\\SubscriberInterface.  ' . $type . ' provided');
        }
    }

    /**
     * Create a child channel
     *
     * @param string $name   Channel name
     * @param array  $config channel config
     *
     * @return array
     */
    private function createChannel($name, &$config)
    {
        $cfg = $this->debug->getCfg(null, Debug::CONFIG_INIT);
        $cfgChannels = $cfg['debug']['channels'];
        $config = \array_merge(
            array('nested' => true),  // true = regular child channel, false = tab
            $config,
            isset($cfgChannels[$name])
                ? $cfgChannels[$name]
                : array()
        );
        // echo $name . ' = ' . print_r($config, true) . "\n";
        $cfg = $this->debug->getPropagateValues($cfg);
        // set channel values
        $cfg['debug']['channelIcon'] = null;
        $cfg['debug']['channelName'] = $config['nested'] || $this->debug->parentInstance
            ? $cfg['debug']['channelName'] . '.' . $name
            : $name;
        $cfg['debug']['parent'] = $this->debug;
        unset($cfg['nested']);
        return new Debug($cfg);
    }
}
