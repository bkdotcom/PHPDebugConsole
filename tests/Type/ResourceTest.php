<?php

namespace bdk\DebugTests\Type;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class ResourceTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
        $fh = \fopen(__FILE__, 'r');
        $value = \print_r($fh, true) . ': stream';
        $entry = array(
            'method' => 'log',
            'args' => array(
                array(
                    'debug' => Abstracter::ABSTRACTION,
                    'type' => Abstracter::TYPE_RESOURCE,
                    'value' => $value,
                ),
            ),
            'meta' => array(),
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
