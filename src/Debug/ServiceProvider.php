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
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function register(Container $container)
    {
        /*
            These "services" are reused between channels
            each debug "rootInstance" gets at most one instance of the following
        */
        $container['services'] = array(
            'arrayUtil',
            'backtrace',
            'customMethodGeneral',
            'customMethodReqRes',
            'data',
            'errorHandler',
            'errorLevel',
            'html',
            'methodClear',
            'methodCount',
            'methodGroup',
            'methodProfile',
            'methodTable',
            'methodTime',
            'phpDoc',
            'pluginHighlight',
            'response',
            'serverRequest',
            'stringUtil',
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
            $backtrace->addInternalClass(array(
                'bdk\\Debug',
            ));
            return $backtrace;
        };
        $container['config'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Config($debug);
        };
        $container['configEvents'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\ConfigEvents($debug);
        };
        $container['customMethodGeneral'] = function () {
            return new \bdk\Debug\Plugin\CustomMethod\General();
        };
        $container['customMethodReqRes'] = function () {
            return new \bdk\Debug\Plugin\CustomMethod\ReqRes();
        };
        $container['data'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Data($debug);
        };
        $container['errorLevel'] = function () {
            return new \bdk\Debug\Utility\ErrorLevel();
        };
        $container['errorHandler'] = function (Container $container) {
            $debug = $container['debug'];
            $existingInstance = \bdk\ErrorHandler::getInstance();
            $cfg = \array_merge(array(
                'onEUserError' => null, // don't halt script / log E_USER_ERROR to system_log when 'continueToNormal'
                'emailer' => array(
                    'emailBacktraceDumper' => function ($backtrace) use ($debug) {
                        return $debug->getDump('text')->valDumper->dump($backtrace);
                    },
                ),
            ), $debug->getCfg('errorHandler', \bdk\Debug::CONFIG_INIT));
            if ($existingInstance) {
                $existingInstance->setCfg($cfg);
                return $existingInstance;
            }
            return new \bdk\ErrorHandler($debug->eventManager, $cfg);
        };
        $container['eventManager'] = function () {
            return new \bdk\PubSub\Manager();
        };
        $container['findExit'] = function () {
            return new \bdk\Debug\Utility\FindExit();
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
        $container['logger'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr3\Logger($debug);
        };
        $container['methodClear'] = function () {
            return new \bdk\Debug\Method\Clear();
        };
        $container['methodCount'] = function () {
            return new \bdk\Debug\Method\Count();
        };
        $container['methodGroup'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Method\Group($debug);
        };
        $container['methodHelper'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Method\Helper($debug);
        };
        $container['methodProfile'] = function () {
            return new \bdk\Debug\Method\Profile();
        };
        $container['methodTable'] = function () {
            return new \bdk\Debug\Method\Table();
        };
        $container['methodTime'] = function () {
            return new \bdk\Debug\Method\Time();
        };
        $container['middleware'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr15\Middleware($debug);
        };
        $container['php'] = function () {
            return new \bdk\Debug\Utility\Php();
        };
        $container['phpDoc'] = function () {
            return new \bdk\Debug\Utility\PhpDoc();
        };
        $container['pluginChannel'] = function () {
            return new \bdk\Debug\Plugin\Channel();
        };
        $container['pluginHighlight'] = function () {
            return new \bdk\Debug\Plugin\Highlight();
        };
        $container['pluginLogEnv'] = function () {
            return new \bdk\Debug\Plugin\LogEnv();
        };
        $container['pluginLogFiles'] = function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Plugin\LogFiles(
                $debug->getCfg('logFiles', \bdk\Debug::CONFIG_INIT),
                $debug
            );
        };
        $container['pluginLogPhp'] = function () {
            return new \bdk\Debug\Plugin\LogPhp();
        };
        $container['pluginLogReqRes'] = function () {
            return new \bdk\Debug\Plugin\LogReqRes();
        };
        $container['pluginManager'] = function () {
            return new \bdk\Debug\Plugin\Manager();
        };
        $container['pluginRedaction'] = function () {
            return new \bdk\Debug\Plugin\Redaction();
        };
        $container['response'] = null;
        $container['routeWamp'] = function (Container $container) {
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
        $container['serverRequest'] = function () {
            // Psr\Http\Message\ServerRequestInterface
            return \bdk\HttpMessage\ServerRequest::fromGlobals();
        };
        $container['stringUtil'] = function () {
            return new \bdk\Debug\Utility\StringUtil();
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
            // @codeCoverageIgnoreStart
            if (\class_exists('\\bdk\\WampPublisher') === false) {
                throw new \RuntimeException('PHPDebugConsole does not include WampPublisher.  Install separately');
            }
            $debug = $container['debug'];
            return new \bdk\WampPublisher(
                $debug->getCfg('wampPublisher', \bdk\Debug::CONFIG_INIT)
            );
            // @codeCoverageIgnoreEnd
        };
    }
}
