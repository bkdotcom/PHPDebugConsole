<?php

namespace bdk\Test\Debug\Route;

use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Html route
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\Route\AbstractRoute
 * @covers \bdk\Debug\Route\Html
 * @covers \bdk\Debug\Route\Html\AssetManager
 * @covers \bdk\Debug\Plugin\Highlight
 * @covers \bdk\Debug\Plugin\JavascriptStrings
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
        self::assertTrue(true);
        $debug = new \bdk\Debug(array(
            'collect' => false,
            'output' => false,
            'logResponse' => false,
            'serviceProvider' => array(
                'serverRequest' => static function () {
                    return new \bdk\HttpMessage\ServerRequest(
                        'GET',
                        'http://test.example.com/noun/id/verb?lang=es'
                    );
                },
            ),
        ));
        $routeHtml = $debug->getRoute('html');
        $assets = $routeHtml->getAssets();
        $this->assertArrayHasKey('css', $assets);
        $this->assertArrayHasKey('script', $assets);

        // test removeAsset
        $dir = \realpath(__DIR__ . '/../../../src/Debug/Plugin');
        $css = \realpath($dir . '/../css/Debug.css');
        $assets['css'] = \array_values(\array_diff($assets['css'], array($css)));
        $routeHtml->removeAsset('css', './css/Debug.css');
        $this->assertSame($assets, $routeHtml->getAssets(), 'Assets should match after removing Debug.css');

        // test add/remove assetProvider
        $highlight = new \bdk\Debug\Plugin\Highlight();
        $debug->addPlugin($highlight);
        $assetsCss = $routeHtml->getAssets('css');
        $assetsScript = $routeHtml->getAssets('script');
        $this->assertCount(2, $assetsCss);   // prism & highlight  (Debug.css was removed)
        $this->assertCount(5, $assetsScript); // zest, debug, prism & highlight, & javascriptStrings
        self::assertStringContainsString('/js/zest.min.js', $assetsScript[0]);
        self::assertStringContainsString('/js/Debug.min.js', $assetsScript[1]);
        self::assertStringContainsString('phpDebugConsole.setCfg({', $assetsScript[2]); // javascrptStrings
        self::assertStringContainsString('"attributes":"Atributos"', $assetsScript[2]);
        self::assertStringContainsString('/js/prism.js', $assetsScript[3]);
        self::assertStringContainsString('Prism.manual = true', $assetsScript[4]);

        $debug->removePlugin($highlight);
        $this->assertCount(0, $routeHtml->getAssets('css'));
        $this->assertCount(3, $routeHtml->getAssets('script')); // zest, debug, & javascriptStrings
        $this->assertFalse($routeHtml->removeAsset('css', 'does not exist'));
    }
}
