<?php

namespace bdk\Test\ErrorHandler;

use bdk\ErrorHandler\Error;

/**
 * PHPUnit tests
 *
 * @covers \bdk\ErrorHandler
 * @covers \bdk\ErrorHandler\AbstractError
 * @covers \bdk\ErrorHandler\AbstractErrorHandler
 * @covers \bdk\ErrorHandler\Error
 */
class ErrorTest extends AbstractTestCase // extends DebugTestFramework
{
    public function testConstruct()
    {
        $this->errorHandler->setErrorCaller(array(
            'file' => '/path/to/file.php',
            'line' => 42,
        ));
        $error = new Error($this->errorHandler, array(
            'type' => E_NOTICE,
            'message' => 'some notice',
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        self::assertSame(
            array(
                'file' => '/path/to/file.php',
                'line' => 42,
            ),
            array(
                'file' => $error['file'],
                'line' => $error['line'],
            )
        );
    }

    public function testMissingValThrowsException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Error values must include: type, message, file, & line');
        new Error($this->errorHandler, array(
            'type' => E_NOTICE,
            'message' => 'some notice',
        ));
    }

    public function testInvalidTypeThrowsException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('invalid error type specified');
        new Error($this->errorHandler, $this->randoErrorVals(false, array('type' => E_ALL)));
    }

    public function testInvalidVARSThrowsException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Error vars must be an array');
        new Error($this->errorHandler, $this->randoErrorVals(false, array('vars' => null)));
    }

    public function testAnonymous()
    {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped('anonymous classes are a php 7.0 thing');
        }
        // self::$allowError = true;
        $anonymous = require __DIR__ . '/Fixture/Anonymous.php';

        $error = new Error($this->errorHandler, array(
            'file' => 'foo.bar',
            'line' => 12,
            'message' => 'foo ' . \get_class($anonymous) . ' bar',
            'type' => E_WARNING,
        ));
        self::assertSame('foo stdClass@anonymous bar', $error['message']);
    }

    public function testAsException()
    {
        $exception = new \Exception('exception notice!');
        $error = new Error($this->errorHandler, array(
            'exception' => $exception,
            'file' => __FILE__,
            'line' => __LINE__,
            'message' => 'some notice',
            'type' => E_NOTICE,
        ));
        self::assertSame($exception, $error->asException());
    }

    public function testGetFileAndLine()
    {
        $line = __LINE__;
        $evalLine = 42;
        $error = new Error($this->errorHandler, array(
            'file' => __FILE__ . '(' . $line . ') : eval()\'d code',
            'line' => $evalLine,
            'message' => 'Some notice',
            'type' => E_NOTICE,
        ));
        $this->assertSame(
            \sprintf('%s (line %s, eval\'d line %s)', __FILE__, $line, $evalLine),
            $error->getFileAndLine()
        );
    }

    public function testGetMessage()
    {
        $msgOrig = 'this was totally expected - <a href="https://github.com/bkdotcom/ErrorHandler/">more info</a>';
        $expectText = 'this was totally expected - more info';
        $expectHtml = 'this was totally expected - <a target="phpRef" href="https://github.com/bkdotcom/ErrorHandler/">more info</a>';
        $expectHtmlEscaped = \htmlspecialchars($msgOrig);

        \ini_set('html_errors', 0);
        $error = new Error($this->errorHandler, array(
            'type' => E_NOTICE,
            'message' => $msgOrig,
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        self::assertSame($expectText, $error['message']);
        self::assertSame($expectHtmlEscaped, $error->getMessageHtml());
        self::assertSame($expectText, $error->getMessageText());

        \ini_set('html_errors', 1);
        $error = new Error($this->errorHandler, array(
            'type' => E_NOTICE,
            'message' => $msgOrig,
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        self::assertSame($expectHtml, $error['message']);
        self::assertSame($expectHtml, $error->getMessageHtml());
        self::assertSame($expectText, $error->getMessageText());

        \ini_set('html_errors', 0);
    }

    public function testGetTraceParseError()
    {
        if (\class_exists('ParseError') === false) {
            $this->markTestSkipped('ParseError class does not available');
        }
        $exception = new \ParseError('Parse Error!');
        $error = new Error($this->errorHandler, array(
            'type' => E_PARSE,
            'message' => 'parse error',
            'file' => __FILE__,
            'line' => __LINE__,
            'exception' => $exception,
        ));
        self::assertNull($error->getTrace());
    }

    public function testGetTrace()
    {
        $exception = new \Exception('exceptional error');
        $line = __LINE__ - 1;
        $error = new Error($this->errorHandler, array(
            'type' => E_WARNING,
            'message' => 'dang',
            'file' => __FILE__,
            'line' => __LINE__,
            'exception' => $exception,
        ));
        $trace = $error->getTrace();
        self::assertSame(array(
            'evalLine' => null,
            'file' => __FILE__,
            'line' => $line,
        ), $trace[0]);
    }

    public function testGetOffsetContext()
    {
        $line = __LINE__;
        $error = new Error($this->errorHandler, array(
            'type' => E_WARNING,
            'message' => 'dang',
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        $context = $error['context'];
        $linesExpect = array(
            $line++ => '        $line = __LINE__;' . "\n",
            $line++ => '        $error = new Error($this->errorHandler, array(' . "\n",
            $line++ => '            \'type\' => E_WARNING,' . "\n",
            $line++ => '            \'message\' => \'dang\',' . "\n",
            $line++ => '            \'file\' => __FILE__,' . "\n",
            $line++ => '            \'line\' => __LINE__,' . "\n",
            $line++ => '        ));' . "\n",
            $line++ => '        $context = $error[\'context\'];' . "\n",
        );
        self::assertCount(13, $context);
        self::assertSame($linesExpect, \array_intersect_assoc($context, $linesExpect));
    }

    public function testLog()
    {
        $errorLogWas = \ini_get('error_log');
        $file = __DIR__ . '/error_log.txt';
        \ini_set('error_log', $file);
        $error = new Error($this->errorHandler, array(
            'type' => E_WARNING,
            'message' => 'dang',
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        $error->log();
        $logContents = \file_get_contents($file);
        \unlink($file);
        \ini_set('error_log', $errorLogWas);
        self::assertStringContainsString('PHP Warning: dang in ' . __FILE__ . ' on line ' . $error['line'], $logContents);
    }

    public function testPrevNotSuppressed()
    {
        $errorVals = array(
            'type' => E_WARNING,
            'message' => 'dang',
            'file' => __FILE__,
            'line' => __LINE__,
        );
        $this->raiseError($errorVals);
        $error = $this->raiseError($errorVals);
        self::assertFalse($error['isSuppressed']);
    }

    public function testTypeStr()
    {
        self::assertSame('User Error', Error::typeStr(E_USER_ERROR));
        self::assertSame('', Error::typeStr('bogus'));
    }
}
