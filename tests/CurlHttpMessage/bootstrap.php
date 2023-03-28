<?php

// Override curl_setopt_array() and curl_multi_setopt() to get the last set curl options
namespace bdk\CurlHttpMessage\Handler {
    /*
    function curl_setopt_array($handle, array $options)
    {
        if (!empty($_SERVER['curl_test'])) {
            $_SERVER['_curl'] = $options;
        } else {
            unset($_SERVER['_curl']);
        }
        return \curl_setopt_array($handle, $options);
    }
    */

    function curl_multi_setopt($handle, $option, $value)
    {
        /*
        if (!empty($_SERVER['curl_test'])) {
            $_SERVER['_curl_multi'][$option] = $value;
        } else {
            unset($_SERVER['_curl_multi']);
        }
        */
        $GLOBALS['curlMultiOptions'][$option] = $value;
        return \curl_multi_setopt($handle, $option, $value);
    }
}
