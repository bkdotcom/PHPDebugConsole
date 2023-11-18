<?php

namespace {
    require __DIR__ . '/CurlHttpMessage/bootstrapFunctionReplace.php';
    require __DIR__ . '/HttpMessage/bootstrapFunctionReplace.php';

    $GLOBALS['collectedHeaders'] = array();
    $GLOBALS['headersSent'] = array(); // set to ['file', line] for true
    $GLOBALS['sessionMock'] = array(
        'name' => false,
        'status' => PHP_SESSION_NONE,
    );
}

namespace bdk\Debug {
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

    function headers_list()
    {
        $headersByName = array();
        foreach ($GLOBALS['collectedHeaders'] as $pair) {
            list($header, $replace) = $pair;
            $name = \explode(': ', $header, 2)[0];
            if ($replace || !isset($headersByName[$name])) {
                $headersByName[$name] = array($header);
                continue;
            }
            $headersByName[$name][] = $header;
        }
        $values = \array_values($headersByName);
        return $values
            ? \call_user_func_array('array_merge', $values)
            : array();
    }

    function headers_sent(&$file = null, &$line = null)
    {
        if ($GLOBALS['headersSent']) {
            list($file, $line) = $GLOBALS['headersSent'];
            return true;
        }
        return false;
    }
}

namespace bdk\Debug\Plugin {
    function session_name($name = null)
    {
        $prev = $GLOBALS['sessionMock']['name'];
        if ($name) {
            $GLOBALS['sessionMock']['name'] = $name;
        }
        return $prev;
    }

    function session_start()
    {
        $GLOBALS['sessionMock']['status'] = PHP_SESSION_ACTIVE;
        return true;
    }

    function session_status()
    {
        return $GLOBALS['sessionMock']['status'];
    }
}
