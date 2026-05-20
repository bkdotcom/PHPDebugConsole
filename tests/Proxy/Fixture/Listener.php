<?php

namespace bdk\Test\Proxy\Fixture;

use bdk\Proxy\ListenerInterface;

/**
 * Test proxy listener
 */
class Listener implements ListenerInterface
{
    /** @var array */
    private $log = [];

    /**
     * {@inheritDoc}
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception)
    {
        $this->log[] = [
            'arguments' => $arguments,
            'exception' => $exception,
            'initValues' => $initValues,
            'method' => $methodName,
            'result' => $result,
        ];
        return $result;
    }

    public function init($subject, $proxy)
    {
        $this->log[] = [
            'event' => 'init',
            'proxy' => \get_class($proxy),
            'subject' => \get_class($subject),
        ];
    }

    public function clearLog()
    {
        $this->log = [];
    }

    public function getLog()
    {
        return $this->log;
    }
}
