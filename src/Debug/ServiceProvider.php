<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;
use bdk\Debug;

/**
 * Register service
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services and factories
     *
     * @param Container $container Container instances
     *
     * @return void
     */
    public function register(Container $container) // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength
    {
        /*
            These "services" are reused between channels
            each debug "rootInstance" gets at most one instance of the following
        */
        $container['services'] = array(
            'arrayUtil',
            'backtrace',
            'data',
            'errorHandler',
            'errorLevel',
            'html',
            'phpDoc',
            'pluginHighlight',
            'response',
            'serverRequest',
            'stringUtil',
            'utf8',
            'utility',
        );

        $container['abstracter'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Abstraction\Abstracter($debug, $debug->getCfg('abstracter', Debug::CONFIG_INIT));
        };
        $container['arrayUtil'] = static function () {
            return new \bdk\Debug\Utility\ArrayUtil();
        };
        $container['backtrace'] = static function (Container $container) {
            $debug = $container['debug'];
            $backtrace = $debug->errorHandler->backtrace;
            $backtrace->addInternalClass(array(
                'bdk\\Debug',
            ));
            return $backtrace;
        };
        $container['config'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Config($debug);
        };
        $container['data'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Data($debug);
        };
        $container['errorLevel'] = static function () {
            return new \bdk\Debug\Utility\ErrorLevel();
        };
        $container['errorHandler'] = static function (Container $container) {
            $debug = $container['debug'];
            $existingInstance = \bdk\ErrorHandler::getInstance();
            $cfg = \array_merge(array(
                'emailer' => array(
                    'emailBacktraceDumper' => static function ($backtrace) use ($debug) {
                        $backtrace = \array_map(static function ($frame) {
                            if (!empty($frame['context'])) {
                                $frame['context'] = \array_map(static function ($line) {
                                    return \rtrim($line, "\n");
                                }, $frame['context']);
                            }
                            return $frame;
                        }, $backtrace);
                        $maxDepthBak = $debug->setCfg('maxDepth', 4, Debug::CONFIG_NO_PUBLISH);
                        $traceStr = $debug->getDump('text')->valDumper->dump($backtrace);
                        $debug->setCfg('maxDepth', $maxDepthBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
                        return $traceStr;
                    },
                ),
                'onEUserError' => null, // don't halt script / log E_USER_ERROR to system_log when 'continueToNormal'
            ), $debug->getCfg('errorHandler', Debug::CONFIG_INIT));
            if ($existingInstance) {
                $existingInstance->setCfg($cfg);
                return $existingInstance;
            }
            return new \bdk\ErrorHandler($debug->eventManager, $cfg);
        };
        $container['eventManager'] = static function () {
            return new \bdk\PubSub\Manager();
        };
        $container['findExit'] = static function () {
            return new \bdk\Debug\Utility\FindExit();
        };
        $container['html'] = static function () {
            return new \bdk\Debug\Utility\Html();
        };
        $container['logger'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr3\Logger($debug);
        };
        $container['middleware'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr15\Middleware($debug);
        };
        $container['php'] = static function () {
            return new \bdk\Debug\Utility\Php();
        };
        $container['phpDoc'] = static function () {
            return new \bdk\Debug\Utility\PhpDoc();
        };
        $container['pluginHighlight'] = static function () {
            return new \bdk\Debug\Plugin\Highlight();
        };
        $container['pluginManager'] = static function () {
            return new \bdk\Debug\Plugin\Manager();
        };
        $container['response'] = null;
        $container['routeWamp'] = static function (Container $container) {
            try {
                $wampPublisher = $container['wampPublisher'];
                // @codeCoverageIgnoreStart
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Wamp route requires \bdk\WampPublisher, which must be installed separately');
                // @codeCoverageIgnoreEnd
            }
            $debug = $container['debug'];
            return new \bdk\Debug\Route\Wamp($debug, $wampPublisher);
        };
        $container['serverRequest'] = static function () {
            // Psr\Http\Message\ServerRequestInterface
            return \bdk\HttpMessage\ServerRequest::fromGlobals();
        };
        $container['stringUtil'] = static function () {
            return new \bdk\Debug\Utility\StringUtil();
        };
        $container['stopWatch'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Utility\StopWatch(array(
                'requestTime' => $debug->getServerParam('REQUEST_TIME_FLOAT'),
            ));
        };
        $container['utf8'] = static function () {
            return new \bdk\Debug\Utility\Utf8();
        };
        $container['utility'] = static function () {
            return new \bdk\Debug\Utility();
        };
        $container['wampPublisher'] = static function (Container $container) {
            // @codeCoverageIgnoreStart
            if (\class_exists('\\bdk\\WampPublisher') === false) {
                throw new \RuntimeException('PHPDebugConsole does not include WampPublisher.  Install separately');
            }
            $debug = $container['debug'];
            return new \bdk\WampPublisher(
                $debug->getCfg('wampPublisher', Debug::CONFIG_INIT)
            );
            // @codeCoverageIgnoreEnd
        };
    }
}
