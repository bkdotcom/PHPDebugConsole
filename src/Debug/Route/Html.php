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
use bdk\Debug\AssetProviderInterface;
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

    /** @var array{css:array,script:array} */
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
            'jqueryUrl' => '//ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js',
            'outputCss' => true,
            'outputScript' => true,
            'sidebar' => true,
            'tooltip' => true,
        );
        $this->dumper = $debug->getDump('html');
        $this->addAsset('css', $this->cfg['filepathCss']);
        $this->addAsset('script', $this->cfg['filepathScript']);
    }

    /**
     * Add/register css or javascript
     *
     * @param string $what  "css" or "script"
     * @param string $asset css, javascript, or filepath
     *
     * @return void
     */
    public function addAsset($what, $asset)
    {
        $this->assets[$what][] = $this->normalizeAssetPath($asset);
        $this->assets[$what] = \array_unique($this->assets[$what]);
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
     * Return all assets or return assets of specific type ("css" or "script")
     *
     * @param string $what (optional) specify "css" or "script"
     *
     * @return array
     */
    public function getAssets($what = null)
    {
        if ($what === null) {
            return $this->assets;
        }
        return isset($this->assets[$what])
            ? $this->assets[$what]
            : array();
    }

    /**
     * Return the log's CSS
     *
     * @return string
     */
    public function getCss()
    {
        return $this->buildAssetOutput($this->assets['css'])
            . $this->cfg['css'];
    }

    /**
     * Return the log's javascript
     *
     * @return string
     */
    public function getScript()
    {
        return $this->buildAssetOutput($this->assets['script']);
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
        $this->debug->utility->assertType($event, 'bdk\PubSub\Event');

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
     * @param string $asset css, javascript, or filepath
     *
     * @return bool
     */
    public function removeAsset($what, $asset)
    {
        $asset = $this->normalizeAssetPath($asset);
        foreach ($this->assets[$what] as $k => $v) {
            if ($v === $asset) {
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
        foreach ($assets as $asset) {
            // isFile "safely" checks if the value is an existing regular file
            if ($this->debug->utility->isFile($asset)) {
                $asset = \ltrim(\file_get_contents($asset), "\xef\xbb\xbf");
            }
            if ($asset === false) {
                $asset = '/* PHPDebugConsole: unable to read file ' . $asset . ' */';
                $this->debug->alert('unable to read file ' . $asset);
            }
            $return .= $asset . "\n";
        }
        return $return;
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
            'data-channel-name-root' => $this->channelNameRoot,
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
        $channelRoot = $this->debug->rootInstance;
        $tree = array();
        \ksort($channels, SORT_NATURAL | SORT_FLAG_CASE);
        \array_walk($channels, static function (Debug $channel, $name) use ($channels, $channelRoot, &$tree) {
            $ref = &$tree;
            $path = \explode('.', $name);
            foreach ($path as $i => $k) {
                // output may have only output general.foo
                //   we still need general
                $pathFq = \implode('.', \array_slice($path, 0, $i + 1));
                $channel = isset($channels[$pathFq])
                    ? $channels[$pathFq]
                    : $channelRoot->getChannel($pathFq);
                if (!isset($ref[$k])) {
                    $ref[$k] = array(
                        'channels' => array(),
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
     * Build <script> tag
     *
     * @return string
     */
    private function buildScriptTag()
    {
        if (!$this->cfg['outputScript']) {
            return '';
        }
        return '<script defer>window.jQuery || document.write(\'<script src="' . $this->cfg['jqueryUrl'] . '"><\/script>\')</script>' . "\n"
            . '<script defer>'
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
     * Convert "./" relative path to absolute
     *
     * @param string $asset css, javascript, or filepath
     *
     * @return string
     */
    private function normalizeAssetPath($asset)
    {
        return \preg_match('#[\r\n]#', $asset) !== 1
            ? \preg_replace('#^\./?#', __DIR__ . '/../', $asset) // single line... see if begins with "./""
            : $asset;
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $assetTypes = array(
            'filepathCss' => 'css',
            'filepathScript' => 'script',
        );
        $assetValues = \array_intersect_key($cfg, $assetTypes);
        foreach ($assetValues as $k => $v) {
            $type = $assetTypes[$k];
            $this->removeAsset($type, $prev[$k]);
            $this->addAsset($type, $v);
        }
    }
}
