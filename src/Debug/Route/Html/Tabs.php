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

namespace bdk\Debug\Route\Html;

use bdk\Debug;
use bdk\Debug\Route\Html as HtmlRoute;

/**
 * Build tabs & tab panes
 */
class Tabs
{
    /** @var Debug */
    protected $debug;

    /** @var HtmlRoute */
    protected $route;

    /**
     * Constructor
     *
     * @param HtmlRoute $route Html Route instance
     */
    public function __construct(HtmlRoute $route)
    {
        $this->route = $route;
        $this->debug = $route->debug;
    }

    /**
     * Build tab selection links
     *
     * @return string html fragment
     */
    public function buildTabList()
    {
        $channels = $this->debug->getChannelsTop();
        \uasort($channels, [$this, 'sortChannelCallback']);
        $tabs = [];
        foreach ($channels as $instance) {
            if ($instance->getCfg('output', Debug::CONFIG_DEBUG)) {
                $tabs[] = $this->buildTab($instance);
            }
        }
        return \count($tabs) > 1
            ? \implode('', $tabs)
            : '';
    }

    /**
     * Build tab panes/content
     *
     * @return string html
     */
    public function buildTabPanes()
    {
        $channels = $this->debug->getChannelsTop();
        /*
            Sort channel names.
            We want "Request / Response" & "Files" to come first in case we're not outputting tab UI
        */
        $this->debug->arrayUtil->sortWithOrder(
            $channels,
            ['Request / Response', 'Files'],
            'key'
        );
        $html = '<div class="tab-panes"' . ($this->route->getCfg('outputScript') ? ' style="display:none;"' : '') . '>' . "\n";
        foreach ($channels as $instance) {
            if ($instance->getCfg('output', Debug::CONFIG_DEBUG) === false) {
                continue;
            }
            $html .= $this->buildTabPane($instance);
        }
        $html .= '</div>' . "\n"; // close .tab-panes
        return $html;
    }

    /**
     * uasort callback
     *
     * @param Debug $channelA Debug instance
     * @param Debug $channelB Debug instance
     *
     * @return int
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    public function sortChannelCallback(Debug $channelA, Debug $channelB)
    {
        $sortA = $channelA->getCfg('channelSort', Debug::CONFIG_DEBUG);
        $sortB = $channelB->getCfg('channelSort', Debug::CONFIG_DEBUG);
        $nameA = $channelA->getCfg('channelName', Debug::CONFIG_DEBUG);
        $nameB = $channelB->getCfg('channelName', Debug::CONFIG_DEBUG);
        // "root" channel should come first
        if ($channelA === $this->debug) {
            return -1;
        }
        if ($channelB === $this->debug) {
            return 1;
        }
        return $sortB - $sortA ?: \strcasecmp($nameA, $nameB);
    }

    /**
     * Build tab nav-link
     *
     * @param Debug $debug Debug instance
     *
     * @return string html fragment
     */
    private function buildTab(Debug $debug)
    {
        $name = $debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $isActive = false;
        $label = $name;
        if ($debug === $this->debug) {
            $isActive = true;
            $label = 'Log';
        }
        $channelIcon = $this->route->buildIcon($debug->getCfg('channelIcon', Debug::CONFIG_DEBUG));
        return $this->debug->html->buildTag(
            'a',
            array(
                'class' => array(
                    'active' => $isActive,
                    'nav-link' => true,
                ),
                'data-target' => '.' . $this->nameToClassname($name),
                'data-toggle' => 'tab',
                'role' => 'tab',
            ),
            $channelIcon . $label
        ) . "\n";
    }

    /**
     * Build primary log content
     *
     * @param Debug $debug Debug instance
     *
     * @return string html
     */
    private function buildTabPane(Debug $debug)
    {
        $name = $debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $isActive = $debug === $this->debug;
        $this->route->setChannelRegex('#^' . \preg_quote($name, '#') . '(\.|$)#');
        return $this->debug->html->buildTag(
            'div',
            array(
                'class' => array(
                    $this->nameToClassname($name) => true,
                    'active' => $isActive,
                    'tab-pane' => true,
                    'tab-primary' => $isActive,
                ),
                'data-options' => array(
                    'sidebar' => $this->route->getCfg('sidebar'),
                ),
                'role' => 'tabpanel',
            ),
            $this->buildTabPaneBody()
        ) . "\n";
    }

    /**
     * Build primary tab pane body
     *
     * @return string html
     */
    private function buildTabPaneBody()
    {
        return "\n"
            . '<div class="tab-body">' . "\n"
            . $this->route->processAlerts()
            /*
                If outputting script, initially hide the output..
                this will help page load performance (fewer redraws)... by magnitudes
            */
            . '<ul class="debug-log-summary group-body">' . "\n"
                . $this->route->processSummary()
                . '</ul>' . "\n"
            . '<hr />' . "\n"
            . '<ul class="debug-log group-body">' . "\n"
                . $this->route->processLog()
                . '</ul>' . "\n"
            . '</div>' . "\n"; // close .tab-body
    }

    /**
     * Translate channel name to classname
     *
     * @param string $name channelName
     *
     * @return string
     */
    private function nameToClassname($name)
    {
        return 'debug-tab-' . \preg_replace('/\W+/', '-', \strtolower($name));
    }
}
