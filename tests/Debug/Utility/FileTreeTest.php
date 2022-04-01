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
                        'value' => '2 omitted',
                        'attribs' => array(
                            'class' => array(
                                'exclude-count',
                            ),
                        ),
                        'type' => 'string',
                        'debug' => Abstracter::ABSTRACTION,
                    ),
                ),
                array(
                    'value' => 'bootstrap.php',
                    'attribs' => array(
                        'data-file' =>  '/var/www/bootstrap.php',
                        'class' =>  [],
                    ),
                    'type' => 'string',
                    'debug' => Abstracter::ABSTRACTION
                ),
                array(
                    'value' => 'index.php',
                    'attribs' => array(
                        'data-file' => '/var/www/index.php',
                        'class' =>  [],
                    ),
                    'type' => 'string',
                    'debug' => Abstracter::ABSTRACTION
                ),
            ),
        );
        $this->assertSame($expect, \json_decode(\json_encode($fileTree), true));
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
