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

        $container['abstracter'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Abstraction\Abstracter($debug, $debug->getCfg('abstracter', \bdk\Debug::CONFIG_INIT));
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
        $container['configEvents'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\ConfigEvents($debug);
        };
        $container['customMethodGeneral'] = static function () {
            return new \bdk\Debug\Plugin\CustomMethod\General();
        };
        $container['customMethodReqRes'] = static function () {
            return new \bdk\Debug\Plugin\CustomMethod\ReqRes();
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
                'onEUserError' => null, // don't halt script / log E_USER_ERROR to system_log when 'continueToNormal'
                'emailer' => array(
                    'emailBacktraceDumper' => static function ($backtrace) use ($debug) {
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
        $container['eventManager'] = static function () {
            return new \bdk\PubSub\Manager();
        };
        $container['findExit'] = static function () {
            return new \bdk\Debug\Utility\FindExit();
        };
        $container['html'] = static function () {
            return new \bdk\Debug\Utility\Html();
        };
        $container['internal'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Internal($debug);
        };
        $container['internalEvents'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\InternalEvents($debug);
        };
        $container['logger'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Psr3\Logger($debug);
        };
        $container['methodClear'] = static function () {
            return new \bdk\Debug\Method\Clear();
        };
        $container['methodCount'] = static function () {
            return new \bdk\Debug\Method\Count();
        };
        $container['methodGroup'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Method\Group($debug);
        };
        $container['methodHelper'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Method\Helper($debug);
        };
        $container['methodProfile'] = static function () {
            return new \bdk\Debug\Method\Profile();
        };
        $container['methodTable'] = static function () {
            return new \bdk\Debug\Method\Table();
        };
        $container['methodTime'] = static function () {
            return new \bdk\Debug\Method\Time();
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
        $container['pluginChannel'] = static function () {
            return new \bdk\Debug\Plugin\Channel();
        };
        $container['pluginHighlight'] = static function () {
            return new \bdk\Debug\Plugin\Highlight();
        };
        $container['pluginLogEnv'] = static function () {
            return new \bdk\Debug\Plugin\LogEnv();
        };
        $container['pluginLogFiles'] = static function (Container $container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Plugin\LogFiles(
                $debug->getCfg('logFiles', \bdk\Debug::CONFIG_INIT),
                $debug
            );
        };
        $container['pluginLogPhp'] = static function () {
            return new \bdk\Debug\Plugin\LogPhp();
        };
        $container['pluginLogReqRes'] = static function () {
            return new \bdk\Debug\Plugin\LogReqRes();
        };
        $container['pluginManager'] = static function () {
            return new \bdk\Debug\Plugin\Manager();
        };
        $container['pluginRedaction'] = static function () {
            return new \bdk\Debug\Plugin\Redaction();
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
                $debug->getCfg('wampPublisher', \bdk\Debug::CONFIG_INIT)
            );
            // @codeCoverageIgnoreEnd
        };
    }
}
