<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;
use bdk\Debug;
use bdk\HttpMessage\ServerRequestExtended;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Register service
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services and factories
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    public function register(Container $container)
    {
        $this->registerCoreServices($container);
        $this->registerRoutes($container);
        $this->registerUtilities($container);
        $this->registerMisc($container);

        /*
            These "services" are reused between channels
            each debug "rootInstance" gets at most one instance of the following
        */
        $container['services'] = [
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
        ];

        // ensure that PHPDebugConsole receives ServerRequestExtended
        $container->extend('serverRequest', static function (ServerRequestInterface $serverRequest) {
            return ServerRequestExtended::fromServerRequest($serverRequest);
        });
    }

    /**
     * Register "core" services
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    protected function registerCoreServices(Container $container) // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength
    {
        $container['abstracter'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Abstraction\Abstracter($debug, $debug->getCfg('abstracter', Debug::CONFIG_INIT));
        };
        $container['backtrace'] = static function (Container $container) {
            $debug = $container['debug'];
            $backtrace = $debug->errorHandler->backtrace;
            $backtrace->addInternalClass([
                'bdk\\Debug',
            ]);
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
        $container['errorHandler'] = static function (Container $container) {
            $debug = $container['debug'];
            $existingInstance = \bdk\ErrorHandler::getInstance();
            $cfg = \array_merge(array(
                'emailer' => array(
                    'emailBacktraceDumper' => static function ($backtrace) use ($debug) {
                        $backtrace = \array_map(static function ($frame) {
                            if (empty($frame['evalLine'])) {
                                unset($frame['evalLine']);
                            }
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
        $container['pluginManager'] = static function () {
            return new \bdk\Debug\Plugin\Manager();
        };
    }

    /**
     * Register miscellaneous services
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    protected function registerMisc(Container $container)
    {
        $container['logger'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr3\Logger($debug);
        };
        $container['middleware'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr15\Middleware($debug);
        };
        $container['pluginHighlight'] = static function () {
            return new \bdk\Debug\Plugin\Highlight();
        };
        $container['response'] = null; // app may provide \Psr\Http\Message\ServerRequestInterface
        $container['serverRequest'] = static function () {
            // should return instance of either
            //    \Psr\Http\Message\ServerRequestInterface
            //    or
            //    \bdk\HttpMessage\ServerRequestExtendedInterface
            return \bdk\HttpMessage\Utility\ServerRequest::fromGlobals();
        };
    }

    /**
     * Register route services
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    protected function registerRoutes(Container $container)
    {
        $container['routeWamp'] = static function (Container $container) {
            try {
                $wampPublisher = $container['wampPublisher'];
                // @codeCoverageIgnoreStart
            } catch (RuntimeException $e) {
                throw new RuntimeException('Wamp route requires \bdk\WampPublisher, which must be installed separately');
                // @codeCoverageIgnoreEnd
            }
            $debug = $container['debug'];
            return new \bdk\Debug\Route\Wamp($debug, $wampPublisher);
        };
        $container['wampPublisher'] = static function (Container $container) {
            // @codeCoverageIgnoreStart
            if (\class_exists('bdk\\WampPublisher') === false) {
                throw new RuntimeException('PHPDebugConsole does not include WampPublisher.  Install separately');
            }
            $debug = $container['debug'];
            return new \bdk\WampPublisher(
                $debug->getCfg('wampPublisher', Debug::CONFIG_INIT)
            );
            // @codeCoverageIgnoreEnd
        };
    }

    /**
     * Register utility services
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    protected function registerUtilities(Container $container) // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength
    {
        $container['arrayUtil'] = static function () {
            return new \bdk\Debug\Utility\ArrayUtil();
        };
        $container['errorLevel'] = static function () {
            return new \bdk\Debug\Utility\ErrorLevel();
        };
        $container['findExit'] = static function () {
            return new \bdk\Debug\Utility\FindExit();
        };
        $container['html'] = static function () {
            return new \bdk\Debug\Utility\Html();
        };
        $container['php'] = static function () {
            return new \bdk\Debug\Utility\Php();
        };
        $container['phpDoc'] = static function () {
            return new \bdk\Debug\Utility\PhpDoc();
        };
        $container['reflection'] = static function () {
            return new \bdk\Debug\Utility\Reflection();
        };
        $container['sql'] = static function () {
            return new \bdk\Debug\Utility\Sql();
        };
        $container['sqlQueryAnalysis'] = static function (Container $container) {
            return new \bdk\Debug\Utility\SqlQueryAnalysis($container['debug']);
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
    }
}
