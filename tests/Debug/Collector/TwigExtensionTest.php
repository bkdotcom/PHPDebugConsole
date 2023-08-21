<?php

namespace bdk\Test\Debug\Collector;

use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Collector\TwigExtension
 */
class TwigExtensionTest extends DebugTestFramework
{
    public function testRender()
    {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__);
        $twig = new \Twig\Environment($loader);

        $twig->addExtension(new \bdk\Debug\Collector\TwigExtension());
        $twig->render('test.twig', array(
            'name' => 'Test',
        ));

        $output = $this->debug->output();

        $expect = <<<'EOD'
%A
<ul class="debug-log group-body">
<li class="m_time" data-channel="general.Twig"><span class="no-quotes t_string">Twig: template: test.twig: %f %s</span></li>
</ul>
%A
EOD;
        self::assertStringMatchesFormatNormalized($expect, $output);
    }
}
