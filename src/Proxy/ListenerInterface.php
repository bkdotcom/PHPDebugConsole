<?php

namespace bdk\Proxy;

use Exception;

interface ListenerInterface
{
    /**
     * Called after each call of a method.
     *
     * @param string         $methodName A called method in the {@see $subject}.
     * @param array          $arguments  A list of arguments passed to a called method. The order must be maintained.
     * @param mixed          $result     Return value of a called method.
     * @param array          $initValues Information about the method call, including memory usage and timestamp. For example: `['memoryStart' => 12345, 'timeStart' => 1656657586.4849]`.
     * @param Exception|null $exception  Exception thrown during method call, or null if no exception was thrown.
     *
     * @return mixed Return value of a called method.
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception);

    /**
     * Called when listener added to proxy object.
     * Can be used to initialize listener with subject or other data.
     *
     * @param object $subject Subject being proxied
     * @param object $proxy   Proxy object to which listener is added
     *
     * @return void
     */
    public function init($subject, $proxy);
}
