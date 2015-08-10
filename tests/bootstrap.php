<?php

require __DIR__.'/../src/Debug.php';
if (version_compare(PHP_VERSION, '5.6', '>=')) {
	require __DIR__.'/TestClass_5.6.php';
} else {
	require __DIR__.'/TestClass.php';
}
require __DIR__.'/DOMTest/DOMTestCase.php';
require __DIR__.'/DOMTest/CssSelect.php';
\bdk\Debug\Debug::getInstance();	// invoke autoloader
