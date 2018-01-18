<?php

// backward compatibility
$classMap = array(
    '\PHPUnit_Framework_Exception' => '\PHPUnit\Framework\Exception',
    '\PHPUnit_Framework_TestCase' => '\PHPUnit\Framework\TestCase',
    '\PHPUnit_Framework_TestSuite' => '\PHPUnit\Framework\TestSuite',
);
foreach ($classMap as $old => $new) {
    if (!class_exists($new)) {
        class_alias($old, $new);
    }
}

require __DIR__.'/../src/Debug/Debug.php';
require __DIR__.'/DOMTest/DOMTestCase.php';
require __DIR__.'/DOMTest/CssSelect.php';
require __DIR__.'/DebugTestFramework.php';

\bdk\Debug::getInstance(array(
	'objectsExclude' => array('PHPUnit_Framework_TestSuite', 'PHPUnit\Framework\TestSuite'),
));

require __DIR__.'/Class/Test.php';
require __DIR__.'/Class/Test2.php';
if (version_compare(PHP_VERSION, '5.6', '>=')) {
    require __DIR__.'/Class/TestVariadic.php';
    if (!defined('HHVM_VERSION')) {
        // HHVM does not support variadic by reference
        require __DIR__.'/Class/TestVariadicByReference.php';
    }
}
