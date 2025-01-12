<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Collector\SimpleCache;

$refClass = new \ReflectionClass('Psr\SimpleCache\CacheInterface');
$refMethod = $refClass->getMethod('get');
$refParameters = $refMethod->getParameters();

if (\method_exists($refMethod, 'hasReturnType') && $refMethod->hasReturnType()) {
    // psr/simple-cache 3.0
    require __DIR__ . '/CompatTrait_3.php';
} elseif (\method_exists($refParameters[0], 'hasType') && $refParameters[0]->hasType()) {
    // psr/simple-cache 2.0
    require __DIR__ . '/CompatTrait_2.php';
} elseif (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    // psr/simple-cache 1.0
    require __DIR__ . '/CompatTrait_1.php';
}
