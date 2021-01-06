<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use bdk\PubSub\Event;

/**
 * Output log as HTML
 */
class Html extends Base
{

    protected $errorSummary;
    protected $cfg = array();
    private $assets = array(
        'css' => array(),
        'script' => array(),
    );

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->errorSummary = new HtmlErrorSummary($this, $debug->errorHandler);
        $this->cfg = array(
            'css' => '',            // additional "override" css
            'drawer' => true,
            'filepathCss' => __DIR__ . '/../css/Debug.css',
            'filepathScript' => __DIR__ . '/../js/Debug.jquery.min.js',
            'jqueryUrl' => '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
            'outputCss' => true,
            'outputScript' => true,
            'sidebar' => true,
            'tooltip' => true,
        );
        $this->dump = $debug->getDump('html');
    }

    /**
     * Add/register css or javascript
     *
     * @param string $what  "css" or "script"
     * @param string $mixed css, javascript, or filepath
     *
     * @return void
     */
    public function addAsset($what, $mixed)
    {
        if ($what === 'css') {
            $this->assets['css'][] = $mixed;
        } elseif ($what === 'script') {
            $this->assets['script'][] = $mixed;
        }
    }

    /**
     * Get and register assets from passed provider
     *
     * @param AssetProviderInterface $assetProvider Asset provider
     *
     * @return void
     */
    public function addAssetProvider(AssetProviderInterface $assetProvider)
    {
        $assets = \array_merge(array(
            'css' => array(),
            'script' => array(),
        ), $assetProvider->getAssets());
        foreach ((array) $assets['css'] as $css) {
            $this->addAsset('css', $css);
        }
        foreach ((array) $assets['script'] as $script) {
            $this->addAsset('script', $script);
        }
    }

    /**
     * Return the log's CSS
     *
     * @return string
     */
    public function getCss()
    {
        $return = '';
        if ($this->cfg['filepathCss']) {
            $return = \file_get_contents($this->cfg['filepathCss']);
            if ($return === false) {
                $return = '/* Unable to read filepathCss */';
                $this->debug->alert('unable to read filepathCss');
            }
        }
        /*
            add "plugin" css
        */
        $return .= $this->buildAssetOutput($this->assets['css']);
        if (!empty($this->cfg['css'])) {
            $return .= $this->cfg['css'];
        }
        return $return;
    }

    /**
     * Return the log's javascript
     *
     * @return string
     */
    public function getScript()
    {
        $return = '';
        if ($this->cfg['filepathScript']) {
            $return = \file_get_contents($this->cfg['filepathScript']);
            if ($return === false) {
                $return = 'console.warn("PHPDebugConsole: unable to read filepathScript");';
                $this->debug->alert('unable to read filepathScript');
            }
        }
        /*
            add "plugin" scripts
        */
        return $return . $this->buildAssetOutput($this->assets['script']);
    }

    /**
     * Return the log as HTML
     *
     * @param Event $event Debug::EVENT_OUTPUT event object
     *
     * @return string|void
     */
    public function processLogEntries(Event $event)
    {
        $this->dump->crateRaw = false;
        $this->data = $this->debug->getData();
        // this could go in an extended processAlerts method
        $errorSummary = $this->errorSummary->build($this->debug->errorStats());
        if ($errorSummary) {
            \array_unshift($this->data['alerts'], $errorSummary);
        }
        $lftDefault = \strtr(\ini_get('xdebug.file_link_format'), array(
            '%f' => '%file',
            '%l' => '%line',
        ));
        $str = '<div' . $this->debug->html->buildAttribString(array(
            'class' => 'debug',
            // channel list gets built as log processed...  we'll str_replace this...
            'data-channels' => '{{channels}}',
            'data-channel-name-root' => $this->channelNameRoot,
            'data-options' => array(
                'drawer' => $this->cfg['drawer'],
                'linkFilesTemplateDefault' => $lftDefault ?: null,
                'tooltip' => $this->cfg['tooltip'],
            ),
        )) . ">\n";
        if ($this->cfg['outputCss']) {
            $str .= '<style type="text/css">' . "\n"
                    . $this->getCss() . "\n"
                . '</style>' . "\n";
        }
        if ($this->cfg['outputScript']) {
            $str .= '<script>window.jQuery || document.write(\'<script src="' . $this->cfg['jqueryUrl'] . '"><\/script>\')</script>' . "\n";
            $str .= '<script>'
                    . $this->getScript() . "\n"
                . '</script>' . "\n";
        }
        $str .= '<header class="debug-menu-bar">'
            . 'PHPDebugConsole'
            . '<nav role="tablist"' . ($this->cfg['outputScript'] ? ' style="display:none;"' : '') . '>'
                . $this->buildTabList()
            . '</nav>'
            . '</header>' . "\n";
        if ($this->cfg['outputScript']) {
            $str .= '<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>' . "\n";
        }
        $str .= '<div class="debug-tabs"' . ($this->cfg['outputScript'] ? ' style="display:none;"' : '') . '>' . "\n";
        $str .= $this->buildTabPanes();
        $str .= '</div>' . "\n"; // close .debug-tabs
        $str .= '</div>' . "\n"; // close .debug
        $str = \preg_replace('#(<ul[^>]*>)\s+</ul>#', '$1</ul>', $str); // ugly, but want to be able to use :empty
        $str = \strtr($str, array(
            '{{channels}}' => \htmlspecialchars(\json_encode($this->buildChannelTree(), JSON_FORCE_OBJECT)),
        ));
        $this->data = array();
        $event['return'] .= $str;
        $this->dump->crateRaw = true;
    }

    /**
     * Add/register css or javascript
     *
     * @param string $what  "css" or "script"
     * @param string $mixed css, javascript, or filepath
     *
     * @return bool
     */
    public function removeAsset($what, $mixed)
    {
        foreach ($this->assets[$what] as $k => $v) {
            if ($mixed === $v) {
                unset($this->assets[$what][$k]);
                $this->assets[$what] = \array_values($this->assets[$what]);
                return true;
            }
        }
        return false;
    }

    /**
     * Get and register assets from passed provider
     *
     * @param AssetProviderInterface $assetProvider Asset provider
     *
     * @return void
     */
    public function removeAssetProvider(AssetProviderInterface $assetProvider)
    {
        $assets = \array_merge(array(
            'css' => array(),
            'script' => array(),
        ), $assetProvider->getAssets());
        foreach ((array) $assets['css'] as $css) {
            $this->removeAsset('css', $css);
        }
        foreach ((array) $assets['script'] as $script) {
            $this->removeAsset('script', $script);
        }
    }

    /**
     * Combine css or script assets into a single string
     *
     * @param array $assets array of assets (filepaths / strings)
     *
     * @return string
     */
    private function buildAssetOutput(array $assets)
    {
        $return = '';
        $hashes = array();
        foreach ($assets as $asset) {
            if (!\preg_match('#[\r\n]#', $asset)) {
                // single line... potential filepath
                $asset = \preg_replace('#^\./?#', __DIR__ . '/../', $asset);
                if (\file_exists($asset)) {
                    $asset = \file_get_contents($asset);
                }
            }
            $hash = \md5($asset);
            if (!\in_array($hash, $hashes)) {
                $return .= $asset . "\n";
                $hashes[] = $hash;
            }
        }
        return $return;
    }

    /**
     * Build a tree of all channels that have been output
     *
     * @return array
     */
    protected function buildChannelTree()
    {
        $channels = $this->dump->channels;
        \ksort($channels, SORT_NATURAL | SORT_FLAG_CASE);
        $tree = array();
        foreach ($channels as $name => $channel) {
            $ref = &$tree;
            $path = \explode('.', $name);
            foreach ($path as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array(
                        'options' => array(
                            'icon' => $channel->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                            'show' => $channel->getCfg('channelShow', Debug::CONFIG_DEBUG),
                        ),
                        'channels' => array(),
                    );
                }
                $ref = &$ref[$k]['channels'];
            }
        }
        return $tree;
    }

    /**
     * Build tab selection links
     *
     * @return string html
     */
    private function buildTabList()
    {
        $channels = $this->debug->getChannelsTop();
        $channels = $this->sortTabChannels($channels);
        if (\count($channels) < 2) {
            return '';
        }
        $html = '';
        foreach ($channels as $name => $instance) {
            $isActive = false;
            $nameTab = $name;
            if ($instance === $this->debug) {
                $isActive = true;
                $nameTab = 'Log';
            }
            $target = '.' . $this->nameToClassname($name);
            $channelIcon = $instance->getCfg('channelIcon', Debug::CONFIG_DEBUG);
            if ($channelIcon && \strpos($channelIcon, '<') === false) {
                $channelIcon = '<i class="' . $channelIcon . '"></i>';
            }
            $html .= $this->debug->html->buildTag(
                'a',
                array(
                    'class' => array(
                        'nav-link',
                        $isActive ? 'active' : null,
                    ),
                    'data-target' => $target,
                    'data-toggle' => 'tab',
                    'role' => 'tab',
                ),
                $channelIcon . $nameTab
            ) . "\n";
        }
        return $html;
    }

    /**
     * Build tab panes/content
     *
     * @return string html
     */
    private function buildTabPanes()
    {
        $html = '';
        $names = \array_keys($this->debug->getChannelsTop());
        /*
            We want Request / Response to come first in case we're not outputting tab UI
        */
        $order = array('Request / Response', 'Files');
        \usort($names, function ($valA, $valB) use ($order) {
            $aPos = \array_search($valA, $order);
            $bPos = \array_search($valB, $order);
            if ($aPos === false && $bPos === false) {   // both items are dont cares
                return 0;
            }
            if ($aPos === false) {                      // $a is a dont care
                return 1;                               //   $a > $b
            }
            if ($bPos === false) {                      // $b is a dont care
                return -1;                              //   $a < $b
            }
        });
        foreach ($names as $name) {
            $html .= $this->buildTabPane($name);
        }
        return $html;
    }

    /**
     * Build primary log content
     *
     * @param string $name channel name
     *
     * @return string html
     */
    private function buildTabPane($name)
    {
        $this->channelRegex = '#^' . \preg_quote($name, '#') . '(\.|$)#';
        $isActive = $name === $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $str = '<div' . $this->debug->html->buildAttribString(array(
            'class' => array(
                'tab-pane',
                $isActive ? 'active' : null,
                $isActive ? 'tab-primary' : null,
                $this->nameToClassname($name),
            ),
            'data-options' => array(
                'sidebar' => $this->cfg['sidebar'],
            ),
            'role' => 'tabpanel',
        )) . ">\n";
        $str .= '<div class="tab-body">' . "\n";

        $str .= $this->processAlerts();
        /*
            If outputing script, initially hide the output..
            this will help page load performance (fewer redraws)... by magnitudes
        */
        $str .= '<ul' . $this->debug->html->buildAttribString(array(
            'class' => 'debug-log-summary group-body',
        )) . ">\n" . $this->processSummary() . '</ul>' . "\n";
        $str .= '<ul' . $this->debug->html->buildAttribString(array(
            'class' => 'debug-log group-body',
        )) . ">\n" . $this->processLog() . '</ul>' . "\n";

        $str .= '</div>' . "\n"; // close .tab-body
        $str .= '</div>' . "\n"; // close .tab-pane
        return $str;
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

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array())
    {
        foreach (array('filepathCss', 'filepathScript') as $k) {
            $this->cfg[$k] = \preg_replace('#^\./?#', __DIR__ . '/../', $this->cfg[$k]);
        }
    }

    /**
     * Sort channels by channelSort & channelName
     *
     * @param Debug[] $channels Debug instances
     *
     * @return Debug[]
     */
    private function sortTabChannels($channels)
    {
        \uasort($channels, function (Debug $channelA, Debug $channelB) {
            $sortA = $channelA->getCfg('channelSort', Debug::CONFIG_DEBUG);
            $sortB = $channelB->getCfg('channelSort', Debug::CONFIG_DEBUG);
            $nameA = $channelA->getCfg('channelName', Debug::CONFIG_DEBUG);
            $nameB = $channelB->getCfg('channelName', Debug::CONFIG_DEBUG);
            if ($channelA === $this->debug) {
                return -1;
            }
            if ($channelB === $this->debug) {
                return 1;
            }
            return $sortB - $sortA ?: \strcasecmp($nameA, $nameB);
        });
        return $channels;
    }
}
