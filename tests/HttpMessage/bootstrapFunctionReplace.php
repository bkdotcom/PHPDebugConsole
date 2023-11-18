<?php

namespace bdk\HttpMessage\Utility;

$GLOBALS['collectedHeaders'] = array();

/**
 * Overwrite php's header method
 *
 * @param string $header           Header line
 * @param bool   $replace          whether to replace previous value
 * @param int    $httpResponseCode Forces the HTTP response code to the specified value
 *
 * @return void
 */
function header($header, $replace = true, $httpResponseCode = 0)
{
    $vals = array($header, $replace);
    if ($httpResponseCode) {
        $vals[] = $httpResponseCode;
    }
    $GLOBALS['collectedHeaders'][] = $vals;
}
