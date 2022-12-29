<?php

namespace bdk\Debug\Psr3;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\MethodSignatureCompatTrait', false) === false) {
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
}
