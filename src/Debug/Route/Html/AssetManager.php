<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.5
 */

namespace bdk\Debug\Route\Html;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use InvalidArgumentException;

/**
 * Handle css and javascript assets
 */
class AssetManager
{
    /** @var Debug */
    protected $debug;

    /** @var array{css:array,script:array} */
    private $assets = array(
        'css' => array(),
        'script' => array(),
    );

    /** @var AssetProviderInterface[] */
    private $providers = [];

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
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
     * @throws InvalidArgumentException
     */
    public function addAsset($what, $asset, $priority = 0)
    {
        $this->assertWhat($what);
        if (!\is_int($priority)) {
            throw new InvalidArgumentException('Priority must be an integer');
        }
        if (empty($asset)) {
            return;
        }
        $this->assets[$what][$priority][] = $this->normalize($asset);
        $this->assets[$what][$priority] = \array_unique($this->assets[$what][$priority]);
    }

    /**
     * Add asset provider to list
     *
     * @param AssetProviderInterface $assetProvider Asset provider instance
     *
     * @return void
     */
    public function addProvider(AssetProviderInterface $assetProvider)
    {
        $this->providers[] = $assetProvider;
    }

    /**
     * Return all assets or return assets of specific type ("css" or "script")
     *
     * @param string|null $what "css" or "script"
     *
     * @return array
     */
    public function getAssets($what = null)
    {
        $this->assertWhat($what, true);
        foreach ($this->getProviders() as $assetProvider) {
            $this->processProvider($assetProvider);
        }
        if ($what === null) {
            return \array_map(static function ($assetsByPriority) {
                \krsort($assetsByPriority, SORT_NUMERIC);
                return \call_user_func_array('array_merge', $assetsByPriority);
            }, $this->assets);
        }
        if (isset($this->assets[$what])) {
            \krsort($this->assets[$what], SORT_NUMERIC);
            return \call_user_func_array('array_merge', $this->assets[$what]);
        }
        return array();
    }

    /**
     * Combine css or script assets into a single string
     *
     * @param string $what "css" or "script"
     *
     * @return string
     */
    public function getAssetsAsString($what)
    {
        $return = '';
        $assets = $this->getAssets($what);
        foreach ($assets as $asset) {
            $content = $asset;
            // isFile "safely" checks if the value is an existing regular file
            if ($this->debug->utility->isFile($asset)) {
                $content = \preg_replace('/^(' . Debug::BOM . ')+/', '', \file_get_contents($asset));
            }
            if ($content === false) {
                $content = '/* PHPDebugConsole: unable to read file ' . $asset . ' */';
                $this->debug->alert('unable to read file ' . $asset);
            }
            $return .= $content . "\n";
        }
        return $return;
    }

    /**
     * Get all registered asset providers
     * Clears the enqueued asset providers
     *
     * @return list<AssetProviderInterface>
     */
    public function getProviders()
    {
        $providers = $this->providers;
        $this->providers = [];
        return $providers;
    }

    /**
     * Remove css or javascript asset
     *
     * @param string $what  "css" or "script"
     * @param string $asset css, javascript, or filepath
     *
     * @return bool
     */
    public function removeAsset($what, $asset)
    {
        $this->assertWhat($what);
        $assetPath = $this->normalize($asset);
        foreach ($this->assets[$what] as $priority => $assets) {
            $index = \array_search($assetPath, $assets, true);
            if ($index !== false) {
                unset($this->assets[$what][$priority][$index]);
                $this->assets[$what][$priority] = \array_values($this->assets[$what][$priority]);
                return true;
            }
        }
        return false;
    }

    /**
     * Remove asset provider from list
     *
     * @param AssetProviderInterface $assetProvider Asset provider
     *
     * @return void
     */
    public function removeProvider(AssetProviderInterface $assetProvider)
    {
        $key = \array_search($assetProvider, $this->providers, true);
        if ($key !== false) {
            unset($this->providers[$key]);
        }
        // we may have already pulled assets / remove them
        foreach ($assetProvider->getAssets() as $type => $assetsOfType) {
            foreach ((array) $assetsOfType as $asset) {
                $this->removeAsset($type, $asset);
            }
        }
    }

    /**
     * Assert that $what is a valid asset type ("css" or "script")
     *
     * @param mixed $what      Value to assert
     * @param bool  $allowNull Allow null value?
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertWhat($what, $allowNull = false)
    {
        if ($what === null && $allowNull) {
            return;
        }
        if (!\in_array($what, ['css', 'script'], true)) {
            throw new InvalidArgumentException('Asset type must be "css" or "script"');
        }
    }

    /**
     * Convert "./" relative path to absolute
     *
     * @param string $asset css, javascript, or filepath
     *
     * @return string
     */
    private function normalize($asset)
    {
        return \preg_match('#[\r\n]#', $asset) !== 1
            ? \realpath(\preg_replace('#^\./?#', __DIR__ . '/../../', $asset))
            : \trim($asset);
    }

    /**
     * Get and register assets from passed provider
     *
     * @param AssetProviderInterface $assetProvider Asset provider
     *
     * @return void
     */
    private function processProvider(AssetProviderInterface $assetProvider)
    {
        foreach ($assetProvider->getAssets() as $type => $assetsOfType) {
            foreach ((array) $assetsOfType as $asset) {
                $this->addAsset($type, $asset);
            }
        }
    }
}
