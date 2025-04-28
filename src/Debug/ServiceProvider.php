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
    protected $utilities = [
        'arrayUtil',
        'errorLevel',
        'findExit',
        'html',
        'php',
        'phpDoc',
        'reflection',
        'sql',
        'sqlQueryAnalysis',
        'stopWatch',
        'stringUtil',
        'utf8',
        'utility',
    ];

    /**
     * Register services and factories
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    public function register(Container $container)
    {
        $this->registerCore($container);
        $this->registerRoutes($container);
        $this->registerUtilities($container);
        $this->registerMisc($container);

        /*
            These "services" are reused between channels
        */
        $container['services'] = \array_merge($this->utilities, [
            'backtrace',
            'configNormalizer',
            'data',
            'errorHandler',
            'i18n',
            'pluginHighlight',
            'response',  // app may provide \Psr\Http\Message\ServerRequestInterface
            'serverRequest',
        ]);

        // ensure that PHPDebugConsole receives ServerRequestExtended
        $container->extend('serverRequest', static function (ServerRequestInterface $serverRequest) {
            return ServerRequestExtended::fromServerRequest($serverRequest);
        });
    }

    /**
     * Register "core"
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    protected function registerCore(Container $container) // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength
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
            return new \bdk\Debug\Config($container['debug'], $container['debug']->configNormalizer);
        };
        $container['configNormalizer'] = static function (Container $container) {
            return new \bdk\Debug\ConfigNormalizer($container['debug']);
        };
        $container['data'] = static function (Container $container) {
            return new \bdk\Debug\Data($container['debug']);
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
        $container['i18n'] = static function (Container $container) {
            return new \bdk\I18n(
                $container['serverRequest'],
                $container['debug']->getCfg('i18n', Debug::CONFIG_DEBUG)
            );
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
            // we'll ensure it becomes a ServerRequestExtended instance
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
            $debug = $container['debug'];
            try {
                $wampPublisher = $container['wampPublisher'];
                // @codeCoverageIgnoreStart
            } catch (RuntimeException $e) {
                throw new RuntimeException($debug->i18n->trans('wamp.publisher-required'));
                // @codeCoverageIgnoreEnd
            }
            return new \bdk\Debug\Route\Wamp($debug, $wampPublisher);
        };
        $container['wampPublisher'] = static function (Container $container) {
            $debug = $container['debug'];
            // @codeCoverageIgnoreStart
            if (\class_exists('bdk\\WampPublisher') === false) {
                throw new RuntimeException($debug->i18n->trans('wamp.publisher-not-installed'));
            }
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
        $container['bdk\Debug\Utility\ArrayUtil'] = static function () {
            return new \bdk\Debug\Utility\ArrayUtil();
        };
        $container->addAlias('arrayUtil', 'bdk\Debug\Utility\ArrayUtil');

        $container['bdk\Debug\Utility\ErrorLevel'] = static function () {
            return new \bdk\Debug\Utility\ErrorLevel();
        };
        $container->addAlias('errorLevel', 'bdk\Debug\Utility\ErrorLevel');

        $container['bdk\Debug\Utility\FindExit'] = static function () {
            return new \bdk\Debug\Utility\FindExit();
        };
        $container->addAlias('findExit', 'bdk\Debug\Utility\FindExit');

        $container['bdk\Debug\Utility\Html'] = static function () {
            return new \bdk\Debug\Utility\Html();
        };
        $container->addAlias('html', 'bdk\Debug\Utility\Html');

        $container['bdk\Debug\Utility\Php'] = static function () {
            return new \bdk\Debug\Utility\Php();
        };
        $container->addAlias('php', 'bdk\Debug\Utility\Php');

        $container['bdk\Debug\Utility\PhpDoc'] = static function () {
            return new \bdk\Debug\Utility\PhpDoc();
        };
        $container->addAlias('phpDoc', 'bdk\Debug\Utility\PhpDoc');

        $container['bdk\Debug\Utility\Reflection'] = static function () {
            return new \bdk\Debug\Utility\Reflection();
        };
        $container->addAlias('reflection', 'bdk\Debug\Utility\Reflection');

        $container['bdk\Debug\Utility\Sql'] = static function () {
            return new \bdk\Debug\Utility\Sql();
        };
        $container->addAlias('sql', 'bdk\Debug\Utility\Sql');

        $container['bdk\Debug\Utility\SqlQueryAnalysis'] = static function () {
            return new \bdk\Debug\Utility\SqlQueryAnalysis();
        };
        $container->addAlias('sqlQueryAnalysis', 'bdk\Debug\Utility\SqlQueryAnalysis');

        $container['bdk\Debug\Utility\StopWatch'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Utility\StopWatch(array(
                'requestTime' => $debug->getServerParam('REQUEST_TIME_FLOAT'),
            ));
        };
        $container->addAlias('stopWatch', 'bdk\Debug\Utility\StopWatch');

        $container['bdk\Debug\Utility\StringUtil'] = static function () {
            return new \bdk\Debug\Utility\StringUtil();
        };
        $container->addAlias('stringUtil', 'bdk\Debug\Utility\StringUtil');

        $container['bdk\Debug\Utility\Utf8'] = static function () {
            return new \bdk\Debug\Utility\Utf8();
        };
        $container->addAlias('utf8', 'bdk\Debug\Utility\Utf8');

        $container['bdk\Debug\Utility'] = static function () {
            return new \bdk\Debug\Utility();
        };
        $container->addAlias('utility', 'bdk\Debug\Utility');
    }
}
