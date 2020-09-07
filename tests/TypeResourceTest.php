<?php

namespace bdk\DebugTests;

/**
 * PHPUnit tests for Debug class
 */
class TypeResourceTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
        $fh = \fopen(__FILE__, 'r');
        $value = \print_r($fh, true) . ': stream';
        $entry = array(
            'log',
            array(
                new \bdk\Debug\Abstraction\Abstraction(array(
                    'type' => 'resource',
                    'value' => $value,
                )),
            ),
            array(),
        );
        return array(
            array(
                'log',
                array( $fh ),
                array(
                    'custom' => function () use ($fh) {
                        \fclose($fh);
                    },
                    'entry' => $entry,
                    'chromeLogger' => '[["Resource id #' . (int) $fh . ': stream"],null,""]',
                    'html' => '<li class="m_log"><span class="t_resource">Resource id #' . (int) $fh . ': stream</span></li>',
                    'script' => 'console.log("Resource id #' . (int) $fh . ': stream");',
                    'text' => 'Resource id #' . (int) $fh . ': stream',
                    'wamp' => \json_decode(\json_encode($entry), true),
                )
            ),
        );
    }
}
