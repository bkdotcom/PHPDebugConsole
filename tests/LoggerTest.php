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
            'method' => 'log',
            'args' => array('good enough'),
            'meta' => array(
                'psr3level' => 'debug',
            ),
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public function testPlaceholders()
    {
        $this->debug->logger->debug('{adj} debugging', array(
            'adj' => 'Awesome',
            'foo' => 'bar',
        ));
        $this->assertSame(array(
            'method' => 'log',
            'args' => array(
                'Awesome debugging',
                array('foo' => 'bar'),
            ),
            'meta' => array(
                'glue' => ', ',
                'psr3level' => 'debug',
            ),
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
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
            'method' => 'error',
            'args' => array('Emergency broadcast system'),
            'meta' => $metaExpect,
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
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
            'method' => 'error',
            'args' => array('Critical test'),
            'meta' => $metaExpect,
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public function testExceptionContext()
    {
        parent::$allowError = true;
        $this->debug->logger->critical('Make an exception', array(
            'exception' => new \Exception('some exception'),
            'file' => __FILE__,
            'foo' => 'bar',
        ));
        $metaSubset = array(
            'detectFiles' => true,
            'file' => __FILE__,
            'line' => __LINE__ - 7, // line of Exception
            'uncollapse' => true,
        );
        $this->assertSame('error', $this->debug->data->get('log/__end__/method'));
        $this->assertSame(array(
            'some exception',
        ), $this->debug->data->get('log/__end__/args'));
        $this->assertArraySubset($metaSubset, $this->debug->data->get('log/__end__/meta'));
        $backtrace = $this->debug->data->get('log/__end__/meta/trace');
        $this->assertIsArray($backtrace);
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
            'method' => 'error',
            'args' => array('Error test'),
            'meta' => $meta,
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
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
            'method' => 'warn',
            'args' => array('You\'ve been warned'),
            'meta' => $meta,
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
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
            'method' => 'warn',
            'args' => array('Final Notice'),
            'meta' => $meta,
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public function testAlert()
    {
        $this->debug->logger->alert('Alert');
        $this->assertSame(array(
            'method' => 'alert',
            'args' => array(
                'Alert',
            ),
            'meta' => array(
                'dismissible' => false,
                'level' => 'error',
                'psr3level' => 'alert',
            ),
        ), $this->logEntryToArray($this->debug->data->get('alerts/__end__')));
    }

    public function testInfo()
    {
        $this->debug->logger->info('For your information');
        $this->assertSame(array(
            'method' => 'info',
            'args' => array('For your information'),
            'meta' => array(
                'psr3level' => 'info',
            ),
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
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
            'method' => 'table',
            'args' => array(
                $tableDataLogged
            ),
            'meta' => array(
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
                    'indexLabel' => null,
                    'rows' => array(),
                    'summary' => null,
                ),
            ),
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public function testDebug()
    {
        $this->debug->logger->debug('Hello World');
        $this->assertSame(array(
            'method' => 'log',
            'args' => array('Hello World'),
            'meta' => array(
                'psr3level' => 'debug',
            ),
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
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
            'method' => 'table',
            'args' => array(
                $tableDataLogged
            ),
            'meta' => array(
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
                    'indexLabel' => null,
                    'rows' => array(),
                    'summary' => null,
                ),
            ),
        ), $this->logEntryToArray($this->debug->data->get('log/__end__')));
    }
}
