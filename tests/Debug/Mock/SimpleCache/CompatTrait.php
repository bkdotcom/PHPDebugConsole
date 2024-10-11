<?php

namespace bdk\Test\Debug\Mock\SimpleCache;

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
