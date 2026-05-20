<?php

namespace bdk\Test\Backtrace\Fixture;

/**
 * Invokable processor for testing
 */
class InvokableProcessor
{
    public $called = false;

    /**
     * Process backtrace
     *
     * @param array $trace Backtrace array
     *
     * @return array
     */
    public function __invoke($trace)
    {
        $this->called = true;
        return $trace;
    }
}
