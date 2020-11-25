<?php

namespace bdk\DebugTests;

/**
 * PHPUnit tests for Debug class
 */
class LoggerTest extends DebugTestFramework
{

    public function testLog()
    {
        $this->debug->logger->log('debug', 'good enough');
        $this->assertSame(array(
            'log',
            array('good enough'),
            array(
                'psr3level' => 'debug',
            ),
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testPlaceholders()
    {
        $this->debug->logger->debug('{adj} debugging', array(
            'adj' => 'Awesome',
            'foo' => 'bar',
        ));
        $this->assertSame(array(
            'log',
            array(
                'Awesome debugging',
                array('foo' => 'bar'),
            ),
            array(
                'glue' => ', ',
                'psr3level' => 'debug',
            ),
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testEmergency()
    {
        $this->debug->logger->emergency('Emergency broadcast system');
        $metaExpect = array(
            'detectFiles' => true,
            'file' => __FILE__,
            'line' => __LINE__ - 4,
            'psr3level' => 'emergency',
            'uncollapse' => true,
        );
        $this->assertSame(array(
            'error',
            array('Emergency broadcast system'),
            $metaExpect,
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testCritical()
    {
        $this->debug->logger->critical('Critical test');
        $metaExpect = array(
            'detectFiles' => true,
            'file' => __FILE__,
            'line' => __LINE__ - 4,
            'psr3level' => 'critical',
            'uncollapse' => true,
        );
        $this->assertSame(array(
            'error',
            array('Critical test'),
            $metaExpect,
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));

        $this->debug->logger->critical('Make an exception', array(
            'exception' => new \Exception(),
            'file' => 'file',
            'foo' => 'bar',
        ));
        $metaSubset = array(
            'detectFiles' => true,
            'file' => 'file',
            'line' => __LINE__ - 7, // line of Exception
            'uncollapse' => true,
        );
        $metaActual = $this->debug->getData('log/__end__/meta');
        $this->assertSame('error', $this->debug->getData('log/__end__')['method']);
        $this->assertSame('Make an exception', $this->debug->getData('log/__end__/args/0'));
        // should just contain exception & foo...  file gets moved to meta
        $this->assertCount(2, $this->debug->getData('log/__end__/args/1'));
        $this->assertArraySubset(array(
            'foo' => 'bar',
        ), $this->debug->getData('log/__end__/args/1'));
        $exceptionAbs = $this->debug->getData('log/__end__/args/1/exception');
        $this->assertInstanceOf('bdk\\Debug\\Abstraction\\Abstraction', $exceptionAbs);
        $this->assertSame('Exception', $exceptionAbs['className']);
        $this->assertSame('object', $exceptionAbs['type']);
        $this->assertArraySubset($metaSubset, $metaActual);
        $backtrace = $this->debug->getData('log/__end__/meta/trace');
        $this->assertInternalType('array', $backtrace);
    }

    public function testError()
    {
        $this->debug->logger->error('Error test');
        $meta = array(
            'detectFiles' => true,
            'file' => __FILE__,
            'line' => __LINE__ - 4,
            'psr3level' => 'error',
            'uncollapse' => true,
        );
        $this->assertSame(array(
            'error',
            array('Error test'),
            $meta,
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testWarning()
    {
        $this->debug->logger->warning('You\'ve been warned');
        $meta = array(
            'detectFiles' => true,
            'file' => __FILE__,
            'line' => __LINE__ - 4,
            'psr3level' => 'warning',
            'uncollapse' => true,
        );
        $this->assertSame(array(
            'warn',
            array('You\'ve been warned'),
            $meta,
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testNotice()
    {
        $this->debug->logger->notice('Final Notice');
        $meta = array(
            'detectFiles' => true,
            'file' => __FILE__,
            'line' => __LINE__ - 4,
            'psr3level' => 'notice',
            'uncollapse' => true,
        );
        $this->assertSame(array(
            'warn',
            array('Final Notice'),
            $meta,
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testAlert()
    {
        $this->debug->logger->alert('Alert');
        $this->assertSame(array(
            'alert',
            array('Alert'),
            array(
                'dismissible' => false,
                'level' => 'error',
                'psr3level' => 'alert',
            ),
        ), $this->logEntryToArray($this->debug->getData('alerts/__end__')));
    }

    public function testInfo()
    {
        $this->debug->logger->info('For your information');
        $this->assertSame(array(
            'info',
            array('For your information'),
            array(
                'psr3level' => 'info',
            ),
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testInfoWithTable()
    {
        $tableData = array(
            array('name' => 'Bob', 'age' => '12', 'sex' => 'M', 'Naughty' => false),
            array('Naughty' => true, 'name' => 'Sally', 'extracol' => 'yes', 'sex' => 'F', 'age' => '10'),
        );
        $tableDataLogged = array(
            array('name' => 'Bob', 'age' => '12'),
            array('name' => 'Sally', 'age' => '10'),
        );
        $this->debug->logger->info('table caption', array(
            'table' => $tableData,
            'columns' => array('name', 'age'),
        ));
        $this->assertSame(array(
            'table',
            array(
                $tableDataLogged
            ),
            array(
                'caption' => 'table caption',
                'psr3level' => 'info',
                'sortable' => true,
                'tableInfo' => array(
                    'class' => null,
                    'columns' => array(
                        array(
                            'key' => 'name',
                        ),
                        array(
                            'key' => 'age',
                        ),
                    ),
                    'haveObjRow' => false,
                    'rows' => array(),
                    'summary' => null,
                ),
            ),
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testDebug()
    {
        $this->debug->logger->debug('Hello World');
        $this->assertSame(array(
            'log',
            array('Hello World'),
            array(
                'psr3level' => 'debug',
            ),
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }

    public function testDebugWithTable()
    {
        // see also testPlaceholders

        $tableData = array(
            array('name' => 'Bob', 'age' => '12', 'sex' => 'M', 'Naughty' => false),
            array('Naughty' => true, 'name' => 'Sally', 'extracol' => 'yes', 'sex' => 'F', 'age' => '10'),
        );
        $tableDataLogged = array(
            array('name' => 'Bob', 'age' => '12',),
            array('name' => 'Sally', 'age' => '10', ),
        );
        $this->debug->logger->debug('table caption', array(
            'table' => $tableData,
            'columns' => array('name', 'age'),
        ));
        $this->assertSame(array(
            'table',
            array(
                $tableDataLogged
            ),
            array(
                'caption' => 'table caption',
                'psr3level' => 'debug',
                'sortable' => true,
                'tableInfo' => array(
                    'class' => null,
                    'columns' => array(
                        array(
                            'key' => 'name',
                        ),
                        array(
                            'key' => 'age',
                        ),
                    ),
                    'haveObjRow' => false,
                    'rows' => array(),
                    'summary' => null,
                ),
            ),
        ), $this->logEntryToArray($this->debug->getData('log/__end__')));
    }
}
