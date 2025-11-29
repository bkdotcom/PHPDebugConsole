<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Utility\FileTree;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Utility class
 *
 * @covers \bdk\Debug\Utility\FileTree
 */
class FileTreeTest extends TestCase
{
    /**
     * Test
     *
     * @return void
     */
    public function testFileTree()
    {
        $files = array(
            '/var/www/bootstrap.php',
            '/var/www/index.php',
        );
        $excluded = array(
            '/var/www/excludedDir' => 2,
        );
        $fileTreeUtil = new FileTree();
        $fileTree = $fileTreeUtil->filesToTree($files, $excluded, true);
        $expect = array(
            '/var/www' => array(
                'excludedDir' => array(
                    array(
                        'attribs' => array(
                            'class' => ['exclude-count'],
                        ),
                        'debug' => Abstracter::ABSTRACTION,
                        'type' => 'string',
                        'value' => '2 omitted',
                    ),
                ),
                array(
                    'attribs' => array(
                        'class' =>  [],
                        'data-file' =>  '/var/www/bootstrap.php',
                    ),
                    'debug' => Abstracter::ABSTRACTION,
                    'type' => 'string',
                    'value' => 'bootstrap.php',
                ),
                array(
                    'attribs' => array(
                        'class' =>  [],
                        'data-file' => '/var/www/index.php',
                    ),
                    'debug' => Abstracter::ABSTRACTION,
                    'type' => 'string',
                    'value' => 'index.php',
                ),
            ),
        );
        self::assertSame($expect, \json_decode(\json_encode($fileTree), true));
        /*
        $debug->log(
            $debug->abstracter->crateWithVals(
                $fileTree,
                array(
                    'options' => array(
                        'asFileTree' => true,
                        'expand' => true,
                    ),
                )
            ),
            $debug->meta(array(
                'detectFiles' => true,
            ))
        );
        */
    }
}
