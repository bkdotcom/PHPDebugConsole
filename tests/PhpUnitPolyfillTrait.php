<?php

namespace bdk\DebugTests;

trait PhpUnitPolyFillTrait
{

    private $forwardCompatExpectedExceptionMessage = '';
    private $forwardCompatExpectedExceptionCode = null;

    /**
     * Polyfill
     *
     * @param string $exception exception class
     *
     * @return void
     *
     * @see https://github.com/symfony/symfony/pull/32869/files
     */
    public function expectException($exception)
    {
        if (\method_exists('\\PHPUnit\\Framework\\TestCase', 'expectException')) {
            parent::expectException($exception);
            return;
        }
        parent::setExpectedException(
            $exception,
            $this->forwardCompatExpectedExceptionMessage,
            $this->forwardCompatExpectedExceptionCode
        );
    }

    /**
     * @param string $exceptionName
     * @param string $exceptionMessage
     *
     * @return void
     */
    public function setExpectedException($exceptionName, $exceptionMessage = '', $exceptionCode = null)
    {
        $this->forwardCompatExpectedExceptionMessage = $exceptionMessage;
        $this->forwardCompatExpectedExceptionCode = $exceptionCode;
        parent::setExpectedException($exceptionName, $exceptionMessage, $exceptionCode);
    }


}
