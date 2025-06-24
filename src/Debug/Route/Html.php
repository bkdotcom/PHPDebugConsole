<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Route\Html\ErrorSummary;
use bdk\Debug\Route\Html\Tabs;
use bdk\PubSub\Event;

/**
 * Output log as HTML
 */
class Html extends AbstractRoute
{
    /** @var ErrorSummary */
    protected $errorSummary;

    /** @var Tabs */
    protected $tabs;

    /** @var array<string,mixed> */
    protected $cfg = array();

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->errorSummary = new ErrorSummary($this, $debug->errorHandler);
        $this->tabs = new Tabs($this);
        $this->cfg = array(
            'css' => '',            // additional "override" css
            'drawer' => true,
            'filepathCss' => './css/Debug.css',   // relative paths are relative to Debug dir
            'filepathScript' => './js/Debug.min.js',
            'filepathZest' => './js/zest.min.js',
            'outputCss' => true,
            'outputScript' => true,
            'sidebar' => true,
            'tooltip' => true,
        );
        $this->dumper = $debug->getDump('html');
        $this->setCfg($this->cfg); // call to trigger postSetCfg
    }

    /**
     * Add/register css or javascript
     *
     * @param string $what     "css" or "script"
     * @param string $asset    css, javascript, or filepath
     * @param int    $priority (optional) priority of asset
     *
     * @return void
     *
     * @deprecated 3.5
     */
    public function addAsset($what, $asset, $priority = 0)
    {
        $this->debug->assetManager->addAsset($what, $asset, $priority);
    }

    /**
     * Build icon markup
     *
     * @param string|null $icon Icon css class or html markup
     *
     * @return string
     */
    public function buildIcon($icon)
    {
        if (empty($icon)) {
            return '';
        }
        if (\strpos($icon, '<') === false) {
            $icon = '<i class="' . $icon . '" aria-hidden="true"></i>';
        }
        return $icon;
    }

    /**
     * Build <script> tag
     *
     * @return string
     */
    public function buildScriptTag()
    {
        if (!$this->cfg['outputScript']) {
            return '';
        }
        return ''
            . '<script>'
                . $this->debug->assetManager->getAssetsAsString('script') . "\n"
            . '</script>' . "\n";
    }

    /**
     * Build <style> tag
     *
     * @return string
     */
    public function buildStyleTag()
    {
        if (!$this->cfg['outputCss']) {
            return '';
        }
        return '<style type="text/css">' . "\n"
                . $this->debug->assetManager->getAssetsAsString('css') . "\n"
            . '</style>' . "\n";
    }

    /**
     * Return all assets or return assets of specific type ("css" or "script")
     *
     * @param string $what (optional) specify "css" or "script"
     *
     * @return array
     *
     * @deprecated 3.5
     */
    public function getAssets($what = null)
    {
        return $this->debug->assetManager->getAssets($what);
    }

    /**
     * Return CSS
     *
     * @return string
     *
     * @deprecated 3.5
     */
    public function getCss()
    {
        return $this->debug->assetManager->getAssetsAsString('css');
    }

    /**
     * Return javascript
     *
     * @return string
     *
     * @deprecated 3.5
     */
    public function getScript()
    {
        return $this->debug->assetManager->getAssetsAsString('script');
    }

    /**
     * Return the log as HTML
     *
     * @param Event|null $event Debug::EVENT_OUTPUT event object
     *
     * @return string|void
     */
    public function processLogEntries($event = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($event, 'bdk\PubSub\Event|null');

        if ($event['isTarget'] === false) {
            return;
        }
        $this->dumper->crateRaw = false;
        $this->data = $this->debug->data->get();
        // this could go in an extended processAlerts method
        $errorSummary = $this->errorSummary->build($this->debug->errorStats());
        if ($errorSummary['args'][0]) {
            \array_unshift($this->data['alerts'], $errorSummary);
        }
        $event['return'] .= $this->buildOutput();
        $this->data = array();
        $this->dumper->crateRaw = true;
    }

    /**
     * Remove css or javascript asset
     *
     * @param string $what  "css" or "script"
     * @param string $asset css, javascript, or filepath
     *
     * @return bool
     *
     * @deprecated 3.5
     */
    public function removeAsset($what, $asset)
    {
        return $this->debug->assetManager->removeAsset($what, $asset);
    }

    /**
     * Build debug attributes
     *
     * @return array
     */
    private function buildAttribs()
    {
        $lftDefault = \strtr(\ini_get('xdebug.file_link_format'), array(
            '%f' => '%file',
            '%l' => '%line',
        ));
        return array(
            'class' => 'debug',
            // channel list gets built as log processed...  we'll str_replace this...
            'data-channel-key-root' => $this->channelKeyRoot,
            'data-channels' => '{{channels}}',
            'data-options' => array(
                'drawer' => $this->cfg['drawer'],
                'linkFilesTemplateDefault' => $lftDefault ?: null,
                'tooltip' => $this->cfg['tooltip'],
            ),
        );
    }

    /**
     * Build a tree of all channels that have been output
     *
     * @return array
     */
    protected function buildChannelTree()
    {
        $channels = $this->dumper->channels;
        $tree = array();
        \ksort($channels, SORT_NATURAL | SORT_FLAG_CASE);
        \array_walk($channels, function (Debug $channel, $key) use ($channels, &$tree) {
            $ref = &$tree;
            $path = \explode('.', $key);
            foreach ($path as $i => $k) {
                // output may have only output general.foo
                //   we still need general
                $pathFq = \implode('.', \array_slice($path, 0, $i + 1));
                $channel = isset($channels[$pathFq])
                    ? $channels[$pathFq]
                    : $this->debug->rootInstance->getChannel($pathFq);
                if (!isset($ref[$k])) {
                    $ref[$k] = array(
                        'channels' => array(),
                        'name' => $channel->getCfg('channelName', Debug::CONFIG_DEBUG),
                        'options' => array(
                            'icon' => $channel->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                            'show' => $channel->getCfg('channelShow', Debug::CONFIG_DEBUG),
                        ),
                    );
                }
                $ref = &$ref[$k]['channels'];
            }
        });
        return $tree;
    }

    /**
     * Build <header> tag
     *
     * @return string
     */
    private function buildHeader()
    {
        return '<header class="debug-bar debug-menu-bar">'
            . 'PHPDebugConsole'
            . '<nav role="tablist"' . ($this->cfg['outputScript'] ? ' style="display:none;"' : '') . '>'
                . $this->tabs->buildTabList()
            . '</nav>'
            . '</header>' . "\n";
    }

    /**
     * Build "loading" spinner
     *
     * @return string
     */
    private function buildLoading()
    {
        return $this->cfg['outputScript']
            ? '<div class="loading">Loading ' . $this->buildIcon($this->debug->getCfg('icons.loading', Debug::CONFIG_DEBUG)) . '</div>' . "\n"
            : '';
    }

    /**
     * Build HTML output
     *
     * @return string
     */
    private function buildOutput()
    {
        $str = '<div' . $this->debug->html->buildAttribString($this->buildAttribs()) . ">\n"
            . $this->buildStyleTag()
            . $this->buildScriptTag()
            . $this->buildHeader()
            . $this->buildLoading()
            . $this->tabs->buildTabPanes()
            . '</div>' . "\n"; // close .debug

        $str = \preg_replace('#(<ul[^>]*>)\s+</ul>#', '$1</ul>', $str); // ugly, but want to be able to use :empty
        $str = \strtr($str, array(
            '{{channels}}' => \htmlspecialchars(\json_encode($this->buildChannelTree(), JSON_FORCE_OBJECT)),
        ));
        return $str;
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $assetTypeAndPriority = array(
            'css' => ['css', 0],
            'filepathCss' => ['css', 1],
            'filepathScript' => ['script', 0],
            'filepathZest' => ['script', 1],
        );
        $assetValues = \array_intersect_key($cfg, $assetTypeAndPriority);
        foreach ($assetValues as $k => $v) {
            list($type, $priority) = $assetTypeAndPriority[$k];
            $this->debug->assetManager->removeAsset($type, $prev[$k]);
            $this->debug->assetManager->addAsset($type, $v, $priority);
        }
    }
}
