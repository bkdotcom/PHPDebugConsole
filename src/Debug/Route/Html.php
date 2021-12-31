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

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\Route\Html\ErrorSummary;
use bdk\Debug\Route\Html\Tabs;
use bdk\PubSub\Event;

/**
 * Output log as HTML
 */
class Html extends AbstractRoute
{
    protected $errorSummary;
    protected $tabs;
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
        $this->errorSummary = new ErrorSummary($this, $debug->errorHandler);
        $this->tabs = new Tabs($this);
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
        $this->dumper = $debug->getDump('html');
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
        foreach ($assetProvider->getAssets() as $type => $assetsOfType) {
            foreach ((array) $assetsOfType as $asset) {
                $this->addAsset($type, $asset);
            }
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
     * UnRegister assets from passed provider
     *
     * @param AssetProviderInterface $assetProvider Asset provider
     *
     * @return void
     */
    public function removeAssetProvider(AssetProviderInterface $assetProvider)
    {
        foreach ($assetProvider->getAssets() as $type => $assetsOfType) {
            foreach ((array) $assetsOfType as $asset) {
                $this->removeAsset($type, $asset);
            }
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
     * Build HTML output
     *
     * @return string
     */
    private function buildOutput()
    {
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
        $str .= $this->buildStyleTag();
        $str .= $this->buildScriptTag();
        $str .= $this->buildHeader();
        if ($this->cfg['outputScript']) {
            $str .= '<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>' . "\n";
        }
        $str .= $this->tabs->buildTabPanes();
        $str .= '</div>' . "\n"; // close .debug
        $str = \preg_replace('#(<ul[^>]*>)\s+</ul>#', '$1</ul>', $str); // ugly, but want to be able to use :empty
        $str = \strtr($str, array(
            '{{channels}}' => \htmlspecialchars(\json_encode($this->buildChannelTree(), JSON_FORCE_OBJECT)),
        ));
        return $str;
    }

    /**
     * Build a tree of all channels that have been output
     *
     * @return array
     */
    protected function buildChannelTree()
    {
        $channels = $this->dumper->channels;
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
     * Build <script> tag
     *
     * @return string
     */
    private function buildScriptTag()
    {
        if (!$this->cfg['outputScript']) {
            return '';
        }
        return '<script>window.jQuery || document.write(\'<script src="' . $this->cfg['jqueryUrl'] . '"><\/script>\')</script>' . "\n"
            . '<script>'
                . $this->getScript() . "\n"
            . '</script>' . "\n";
    }

    /**
     * Build <style> tag
     *
     * @return string
     */
    private function buildStyleTag()
    {
        if (!$this->cfg['outputCss']) {
            return '';
        }
        return '<style type="text/css">' . "\n"
                . $this->getCss() . "\n"
            . '</style>' . "\n";
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        foreach (array('filepathCss', 'filepathScript') as $k) {
            $this->cfg[$k] = \preg_replace('#^\./?#', __DIR__ . '/../', $this->cfg[$k]);
        }
    }
}
