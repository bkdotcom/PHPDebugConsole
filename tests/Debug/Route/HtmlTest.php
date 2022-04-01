<?php

namespace bdk\Test\Debug\Route;

use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Html route
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\Route\AbstractRoute
 * @covers \bdk\Debug\Route\Html
 * @covers \bdk\Debug\Plugin\Highlight
 */
class HtmlTest extends DebugTestFramework
{
    public function testOutput()
    {
        $this->debug->setCfg(array(
            'outputCss' => true,
            'outputHeaders' => false,
            'outputScript' => true,
        ));
        $this->assertStringMatchesFormat('<div class="debug"%s>
<style type="text/css">%a</style>
<script>%s</script>
<script>%a</script>
<header class="debug-bar debug-menu-bar">PHPDebugConsole<nav role="tablist" style="display:none;">%A</nav></header>
<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>
<div class="tab-panes" style="display:none;">
%A<div class="active debug-tab-general tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
<div class="tab-body">
<ul class="debug-log-summary group-body">
<li class="m_info"><span class="no-quotes t_string">Built In %f %s</span></li>
<li class="m_info"><span class="no-quotes t_string">Peak Memory Usage <span title="Includes debug overhead">?&#x20dd;</span>: %s / %s</span></li>
%A</ul>
<ul class="debug-log group-body"></ul>
</div>
</div>
%A</div>
</div>
', $this->debug->output());
    }

    public function testAssets()
    {
        $dir = \realpath(__DIR__ . '/../../../src/Debug/Route');
        $css = $dir . '/../css/Debug.css';

        $assets = $this->debug->routeHtml->getAssets();
        $this->assertArrayHasKey('css', $assets);
        $this->assertArrayHasKey('script', $assets);

        // test removeAsset
        $assets['css'] = \array_values(\array_diff($assets['css'], array($css)));
        $this->debug->routeHtml->removeAsset('css', './css/Debug.css');
        $this->assertSame($assets, $this->debug->routeHtml->getAssets());

        // test add/remove assetProvider
        $highlight = new \bdk\Debug\Plugin\Highlight();
        $this->debug->routeHtml->addAssetProvider($highlight);
        $this->assertCount(2, $this->debug->routeHtml->getAssets('css'));
        $this->assertCount(3, $this->debug->routeHtml->getAssets('script'));
        $this->debug->routeHtml->removeAssetProvider($highlight);
        $this->assertCount(0, $this->debug->routeHtml->getAssets('css'));
        $this->assertCount(1, $this->debug->routeHtml->getAssets('script'));
        $this->assertFalse($this->debug->routeHtml->removeAsset('css', 'does not exist'));
    }
}
