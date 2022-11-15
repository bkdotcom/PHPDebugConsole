<?php

namespace bdk\Debug\Psr3;

/**
 * Provide log method with signature compatible with psr/log v2
 */
trait MethodSignatureCompatTrait
{
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed              $level   debug, info, notice, warning, error, critical, alert, emergency
     * @param string|\Stringable $message message
     * @param mixed[]            $context array
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = array())
    {
        $this->doLog($level, $message, $context);
    }
}
