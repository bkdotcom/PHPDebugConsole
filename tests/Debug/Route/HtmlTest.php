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
        $expect = '<div class="debug"%s>
<style type="text/css">%a</style>
<script>%s</script>
<script>%a</script>
<header class="debug-bar debug-menu-bar">PHPDebugConsole<nav role="tablist" style="display:none;">%A</nav></header>
<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>
<div class="tab-panes" style="display:none;">
%A<div class="active debug-tab-general tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
<div class="tab-body">
<ul class="debug-log-summary group-body">
<li class="m_info"><span class="no-quotes t_string">Built in %f %s</span></li>
<li class="m_info"><span class="no-quotes t_string">Peak memory usage <i class="fa fa-question-circle-o" title="Includes debug overhead"></i>: %s / %s</span></li>
%A</ul>
<hr />
<ul class="debug-log group-body"></ul>
</div>
</div>
%A</div>
</div>
';
        $output = $this->debug->output();
        // \bdk\Debug::varDump('expect', $expect);
        // \bdk\Debug::varDump('actual', $output);
        $this->assertStringMatchesFormat($expect, $output);
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
        $this->debug->addPlugin($highlight);
        $this->assertCount(2, $this->debug->routeHtml->getAssets('css'));
        $this->assertCount(3, $this->debug->routeHtml->getAssets('script')); // primary & 2 highlight scripts
        $this->debug->removePlugin($highlight);
        $this->assertCount(0, $this->debug->routeHtml->getAssets('css'));
        $this->assertCount(1, $this->debug->routeHtml->getAssets('script'));    // primary
        $this->assertFalse($this->debug->routeHtml->removeAsset('css', 'does not exist'));
    }
}
