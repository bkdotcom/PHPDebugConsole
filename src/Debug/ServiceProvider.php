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

use bdk\Container;
use bdk\Container\ServiceProviderInterface;

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
    public function register(Container $container)
    {

        $container['services'] = array(
            'arrayUtil',
            'backtrace',
            'errorHandler',
            'html',
            'methodClear',
            'methodProfile',
            'methodTable',
            'request',
            'response',
            'utf8',
            'utility',
        );

        $container['abstracter'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Abstraction\Abstracter($debug, $debug->getCfg('abstracter', \bdk\Debug::CONFIG_INIT));
        };
        $container['arrayUtil'] = function () {
            return new \bdk\Debug\Utility\ArrayUtil();
        };
        $container['backtrace'] = function (Container $container) {
            $debug = $container['debug'];
            $backtrace = $debug->errorHandler->backtrace;
            $backtrace->addInternalClass('bdk\\Debug');
            return $backtrace;
        };
        $container['config'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Config($debug);
        };
        $container['errorEmailer'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\ErrorHandler\ErrorEmailer($debug->getCfg('errorEmailer', \bdk\Debug::CONFIG_INIT));
        };
        $container['errorHandler'] = function (Container $container) {
            $debug = $container['debug'];
            $existingInstance = \bdk\ErrorHandler::getInstance();
            if ($existingInstance) {
                return $existingInstance;
            }
            $errorHandler = new \bdk\ErrorHandler($debug->eventManager);
            /*
                log E_USER_ERROR to system_log without halting script
            */
            $errorHandler->setCfg('onEUserError', 'log');
            return $errorHandler;
        };
        $container['eventManager'] = function () {
            return new \bdk\PubSub\Manager();
        };
        $container['html'] = function () {
            return new \bdk\Debug\Utility\Html();
        };
        $container['internal'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Internal($debug);
        };
        $container['internalEvents'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\InternalEvents($debug);
        };
        $container['logEnv'] = function () {
            return new \bdk\Debug\Plugin\LogEnv();
        };
        $container['logFiles'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Plugin\LogFiles(
                $debug->getCfg('logFiles', \bdk\Debug::CONFIG_INIT),
                $debug
            );
        };
        $container['logReqRes'] = function () {
            return new \bdk\Debug\Plugin\LogReqRes();
        };
        $container['logger'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr3\Logger($debug);
        };
        $container['methodClear'] = function () {
            return new \bdk\Debug\Method\Clear();
        };
        $container['methodProfile'] = $container->factory(function () {
            return new \bdk\Debug\Method\Profile();
        });
        $container['methodTable'] = function () {
            return new \bdk\Debug\Method\Table();
        };
        $container['middleware'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr15\Middleware($debug);
        };
        $container['request'] = function () {
            /*
                This can return Psr\Http\Message\ServerRequestInterface
            */
            return \bdk\Debug\Psr7lite\ServerRequest::fromGlobals();
        };
        $container['response'] = null;
        $container['routeWamp'] = function (Container $container) {
            try {
                $wampPublisher = $container['wampPublisher'];
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Wamp route requires \bdk\WampPublisher, which must be installed separately');
            }
            $debug = $container['debug'];
            return new \bdk\Debug\Route\Wamp($debug, $wampPublisher);
        };
        $container['stopWatch'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Utility\StopWatch(array(
                'requestTime' => $debug->getServerParam('REQUEST_TIME_FLOAT'),
            ));
        };
        $container['utf8'] = function () {
            return new \bdk\Debug\Utility\Utf8();
        };
        $container['utility'] = function () {
            return new \bdk\Debug\Utility();
        };
        $container['wampPublisher'] = function (Container $container) {
            if (\class_exists('\\bdk\\WampPublisher') === false) {
                throw new \RuntimeException('PHPDebugConsole does not include WampPublisher.  Install separately');
            }
            $debug = $container['debug'];
            return new \bdk\WampPublisher(
                $debug->getCfg('wampPublisher', \bdk\Debug::CONFIG_INIT)
            );
        };
    }
}
