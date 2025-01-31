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

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\ConfigurableInterface;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Add additional public methods to debug instance
 */
class General implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = [
        'email',
        'errorStats',
        'getDump',
        'getRoute',
        'hasLog',
        'obEnd',
        'obStart',
        'setErrorCaller',
    ];

    /**
     * Send an email
     *
     * @param string $toAddr  to
     * @param string $subject subject
     * @param string $body    body
     *
     * @return bool
     */
    public function email($toAddr, $subject, $body)
    {
        $addHeadersStr = '';
        $fromAddr = $this->debug->getCfg('emailFrom', Debug::CONFIG_DEBUG);
        if ($fromAddr) {
            $addHeadersStr .= 'From: ' . $fromAddr;
        }
        $body = \str_replace("\x00", '\x00', $body);
        return \call_user_func(
            $this->debug->getCfg('emailFunc', Debug::CONFIG_DEBUG),
            $toAddr,
            $subject,
            $body,
            $addHeadersStr
        );
    }

    /**
     * Get error statistics from errorHandler
     * how many errors were captured in/out of console
     * breakdown per error category
     *
     * @return array{
     *   counts: array<string,array{
     *     inConsole: int,
     *     notInConsole: int,
     *     suppressed: int}>,
     *   inConsole: int,
     *   inConsoleCategories: list<string>,
     *   notInConsole: int,
     * }
     */
    public function errorStats()
    {
        $stats = array(
            'counts' => \array_fill_keys(
                ['deprecated','error','fatal','notice','strict','warning'],
                array('inConsole' => 0, 'notInConsole' => 0, 'suppressed' => 0)
            ),
            'inConsole' => 0,
            'inConsoleCategories' => array(),
            'notInConsole' => 0,
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
     * Get dumper
     *
     * @param string $name      classname
     * @param bool   $checkOnly (false) only check if initialized
     *
     * @return \bdk\Debug\Dump\Base|bool
     *
     * @psalm-return ($checkOnly is true ? bool : \bdk\Debug\Dump\Base)
     */
    public function getDump($name, $checkOnly = false)
    {
        /** @var \bdk\Debug\Dump\Base|bool */
        return $this->getDumpRoute('dump', $name, $checkOnly);
    }

    /**
     * Get route
     *
     * @param string $name      classname
     * @param bool   $checkOnly (false) only check if initialized
     *
     * @return \bdk\Debug\Route\RouteInterface|bool
     *
     * @psalm-return ($checkOnly is true ? bool : \bdk\Debug\Route\RouteInterface)
     */
    public function getRoute($name, $checkOnly = false)
    {
        /** @var \bdk\Debug\Route\RouteInterface|bool */
        return $this->getDumpRoute('route', $name, $checkOnly);
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
     * Flush the buffer and end buffering
     *
     * @return void
     */
    public function obEnd()
    {
        if ($this->debug->data->get('isObBuffer') === false) {
            return;
        }
        if (\ob_get_level()) {
            \ob_end_flush();
        }
        $this->debug->data->set('isObBuffer', false);
    }

    /**
     * Conditionally start output buffering
     *
     * @return void
     */
    public function obStart()
    {
        if ($this->debug->data->get('isObBuffer')) {
            return;
        }
        if ($this->debug->rootInstance->getCfg('collect', Debug::CONFIG_DEBUG) !== true) {
            return;
        }
        \ob_start();
        $this->debug->data->set('isObBuffer', true);
    }

    /**
     * A wrapper for errorHandler->setErrorCaller
     *
     * @param array $caller (optional) null (default) determine automatically
     *                      empty value (false, "", 0, array()) clear
     *                      array manually set
     *
     * @return void
     */
    public function setErrorCaller($caller = null)
    {
        if ($caller === null) {
            $caller = $this->debug->backtrace->getCallerInfo(1);
            $caller = array(
                'evalLine' => $caller['evalLine'],
                'file' => $caller['file'],
                'line' => $caller['line'],
            );
        }
        if ($caller) {
            // groupEnd will check depth and potentially clear errorCaller
            $caller['groupDepth'] = $this->debug->rootInstance->getPlugin('methodGroup')->getDepth();
        }
        $this->debug->errorHandler->setErrorCaller($caller);
    }

    /**
     * Get Dump or Route instance
     *
     * @param 'dump'|'route' $cat       "Category" (dump or route)
     * @param string         $name      html, text, etc)
     * @param bool           $checkOnly Only check if initialized?
     *
     * @return \bdk\Debug\Dump\Base|RouteInterface|bool
     *
     * @psalm-return ($checkOnly is true ? bool : \bdk\Debug\Dump\Base|RouteInterface)
     */
    private function getDumpRoute($cat, $name, $checkOnly)
    {
        $property = $cat . \ucfirst($name);
        $isDefined = isset($this->debug->{$property});
        if ($checkOnly) {
            return $isDefined;
        }
        if ($isDefined) {
            return $this->debug->{$property};
        }
        $classname = 'bdk\\Debug\\' . \ucfirst($cat) . '\\' . \ucfirst($name);
        if (\class_exists($classname)) {
            return $this->getDumpRouteInit($property, $classname);
        }
        $caller = $this->debug->backtrace->getCallerInfo();
        $this->debug->errorHandler->handleError(
            E_USER_NOTICE,
            '"' . $property . '" is not accessible',
            $caller['file'],
            $caller['line']
        );
        return false;
    }

    /**
     * Instantiate dumper or route
     *
     * @param string $property  ServiceProvider key
     * @param string $classname Classname to instantiate
     *
     * @return \bdk\Debug\Dump\Base|RouteInterface
     */
    private function getDumpRouteInit($property, $classname)
    {
        /** @var \bdk\Debug\Dump\Base|RouteInterface */
        $val = new $classname($this->debug);
        if ($val instanceof ConfigurableInterface) {
            $cfg = $this->debug->getCfg($property, Debug::CONFIG_INIT);
            $val->setCfg($cfg);
        }
        // update container
        $this->debug->onCfgServiceProvider(array(
            $property => $val,
        ));
        return $val;
    }
}
