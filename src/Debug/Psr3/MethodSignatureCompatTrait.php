<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr3;

/*
    psr/log's function signature takes many forms
*/

$refClass = new \ReflectionClass('Psr\Log\LoggerInterface');
$refMethod = $refClass->getMethod('log');
$refParameters = $refMethod->getParameters();

if (\method_exists($refMethod, 'hasReturnType') && $refMethod->hasReturnType()) {
    // psr/log 3.0
    require __DIR__ . '/MethodSignatureCompatTrait_3.php';
} elseif (\method_exists($refParameters[1], 'hasType') && $refParameters[1]->hasType()) {
    // psr/log 2.0
    require __DIR__ . '/MethodSignatureCompatTrait_2.php';
} elseif (\trait_exists(__NAMESPACE__ . '\\MethodSignatureCompatTrait', false) === false) {
    // psr/log 1.0
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
        public function log($level, $message, array $context = array())
        {
            $this->doLog($level, $message, $context);
        }
    }
}
