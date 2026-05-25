<?php

namespace bdk\Proxy;

/**
 * Modify internal PHP class definitions that do not play well with reflection.
 * This is used to update method definitions with missing or incorrect information such as default parameter values.
 */
class ClassDefModifier
{
    private $defaultNull = array( 'defaultValue' => null, 'isDefaultValueAvailable' => true );
    private $defaultArray = array( 'defaultValue' => array(), 'isDefaultValueAvailable' => true );

    /**
     * Update / override class definition
     *
     * @param array<string,mixed> $classDef Class definition
     *
     * @return array<string,mixed> Modified class definition
     */
    public function modify(array $classDef)
    {
        if ($classDef['name'] === 'mysqli') {
            return $this->modifyMysqli($classDef);
        }
        if ($classDef['name'] === 'OAuth') {
            return $this->modifyOAuth($classDef);
        }
        if ($classDef['name'] === 'PDO') {
            return $this->modifyPdo($classDef);
        }
        if ($classDef['name'] === 'SoapClient' && isset($classDef['methods']['SoapClient'])) {
            return $this->modifySoapClient($classDef);
        }
        return $classDef;
    }

    /**
     * Modify mysqli definition
     *
     * @param array $classDef
     *
     * @return array Modified class definition
     */
    private function modifyMysqli(array $classDef)
    {
        $connectionParams = [
            $this->defaultNull, // hostname
            $this->defaultNull, // username
            $this->defaultNull, // password
            $this->defaultNull, // database
            $this->defaultNull, // port
            $this->defaultNull, // socket
        ];
        $flags = [
            // flags
            array( 'defaultValue' => 0, 'isDefaultValueAvailable' => true ),
        ];
        $flagsAndName = [
            // flags
            array( 'defaultValue' => 0, 'isDefaultValueAvailable' => true ),
            // name
            $this->defaultNull,
        ];
        $classDef['methods']['begin_transaction']['parameters'] = \array_replace_recursive($classDef['methods']['begin_transaction']['parameters'], $flagsAndName);
        $classDef['methods']['commit']['parameters'] = \array_replace_recursive($classDef['methods']['commit']['parameters'], $flagsAndName);
        $classDef['methods']['connect']['parameters'] = \array_replace_recursive($classDef['methods']['connect']['parameters'], $connectionParams);
        if (isset($classDef['methods']['mysqli'])) {
            $classDef['methods']['mysqli']['parameters'] = \array_replace_recursive($classDef['methods']['mysqli']['parameters'], $connectionParams);
        }
        $classDef['methods']['real_connect']['parameters'] = \array_replace_recursive($classDef['methods']['real_connect']['parameters'], array_merge($connectionParams, $flags));
        $classDef['methods']['poll']['parameters'] = \array_replace_recursive($classDef['methods']['poll']['parameters'], [
            // int $usec = 0
            4 => array( 'defaultValue' => 0, 'isDefaultValueAvailable' => true ),
        ]);
        $classDef['methods']['rollback']['parameters'] = \array_replace_recursive($classDef['methods']['rollback']['parameters'], $flagsAndName);
        $classDef['methods']['store_result']['parameters'] = \array_replace_recursive($classDef['methods']['store_result']['parameters'], $flags);
        return $classDef;
    }

    /**
     * Modify OAuth definition
     *
     * @param array $classDef
     *
     * @return array Modified class definition
     */
    private function modifyOAuth(array $classDef)
    {
        // OAuth doesn't work well with reflection
        // We need to hardcode some of the method definitions
        $classDef['methods']['fetch']['parameters'] = \array_merge(array(
            // extra parameters
            1 => $this->defaultArray,
            // http_method
            2 => array( 'defaultValue' => 'OAUTH_HTTP_METHOD_GET', 'isDefaultValueAvailable' => true, 'isDefaultValueConstant' => true ),
            // request_headers
            3 => $this->defaultArray,
        ), $classDef['methods']['fetch']['parameters']);
        $classDef['methods']['generateSignature']['parameters'] = \array_merge(array(
            2 => $this->defaultArray, // extra parameters
        ), $classDef['methods']['generateSignature']['parameters']);
        $classDef['methods']['getAccessToken']['parameters'] = \array_merge(array(
            // auth_session_handle
            1 => array( 'defaultValue' => '', 'isDefaultValueAvailable' => true ),
            // verifier_token
            2 => array( 'defaultValue' => '', 'isDefaultValueAvailable' => true ),
            // http_method
            3 => array( 'defaultValue' => 'OAUTH_HTTP_METHOD_GET', 'isDefaultValueAvailable' => true, 'isDefaultValueConstant' => true ),
        ), $classDef['methods']['getAccessToken']['parameters']);
        $classDef['methods']['getRequestHeader']['parameters'] = \array_merge(array(
            2 => $this->defaultArray, // extra parameters
        ), $classDef['methods']['getRequestHeader']['parameters']);
        $classDef['methods']['getRequestToken']['parameters'] = \array_merge(array(
            // callback_url
            1 => array( 'defaultValue' => '', 'isDefaultValueAvailable' => true ),
            // http_method
            2 => array( 'defaultValue' => 'OAUTH_HTTP_METHOD_GET', 'isDefaultValueAvailable' => true, 'isDefaultValueConstant' => true ),
        ), $classDef['methods']['getRequestToken']['parameters']);
        return $classDef;
    }

    /**
     * Modify PDO definition
     *
     * @param array $classDef
     *
     * @return array Modified class definition
     */
    private function modifyPdo(array $classDef)
    {
        $classDef['methods']['prepare']['parameters'] = \array_replace_recursive($classDef['methods']['prepare']['parameters'], [
            1 => $this->defaultArray, // options
        ]);
        $classDef['methods']['lastInsertId']['parameters'] = \array_replace_recursive($classDef['methods']['lastInsertId']['parameters'], [
            0 => $this->defaultNull, // name
        ]);
        $classDef['methods']['quote']['parameters'] = \array_replace_recursive($classDef['methods']['quote']['parameters'], [
            1 => array( 'defaultValue' => 'PDO::PARAM_STR', 'isDefaultValueAvailable' => true, 'isDefaultValueConstant' => true ),
        ]);
        if (empty($classDef['methods']['query']['parameters']) || $classDef['methods']['query']['parameters'][2]['isVariadic'] === false) {
            $classDef['methods']['query']['proxyViaFuncGetArgs'] = true; // query is overloaded and does not like having default values passed, so proxy via func_get_args() to pass only what was given
            $classDef['methods']['query']['parameters'] = \array_replace_recursive($classDef['methods']['query']['parameters'], [
                array( 'name' => 'query' ),
                array( 'name' => 'fetchMode', 'defaultValue' => 'PDO::FETCH_BOTH', 'isDefaultValueAvailable' => true, 'isDefaultValueConstant' => true ),
                // arg 3 (colNo, className, or object based on fetch mode)
                array( 'name' => 'arg3', 'defaultValue' => null, 'isDefaultValueAvailable' => true ),
                // constructor args (for fetch_mode = PDO::FETCH_CLASS)
                array( 'name' => 'constructorArgs', 'defaultValue' => null, 'isDefaultValueAvailable' => true ),
            ]);
        }
        return $classDef;
    }

    /**
     * Modify SoapClient definition
     *
     * @param array $classDef
     *
     * @return array Modified class definition
     */
    private function modifySoapClient(array $classDef)
    {
        $classDef['methods']['SoapClient']['parameters'] = \array_replace_recursive($classDef['methods']['SoapClient']['parameters'], [
            1 => $this->defaultNull, // options
        ]);
        $classDef['methods']['__soapCall']['parameters'] = \array_replace_recursive($classDef['methods']['__soapCall']['parameters'], [
            2 => $this->defaultNull, // options
            3 => $this->defaultNull, // input_headers
            4 => $this->defaultNull, // output_headers
        ]);
        $classDef['methods']['__doRequest']['parameters'] = \array_replace_recursive($classDef['methods']['__doRequest']['parameters'], [
            4 => $this->defaultNull, // one_way
        ]);
        $classDef['methods']['__setCookie']['parameters'] = \array_replace_recursive($classDef['methods']['__setCookie']['parameters'], [
            1 => $this->defaultNull, // value
        ]);
        $classDef['methods']['__setLocation']['parameters'] = \array_replace_recursive($classDef['methods']['__setLocation']['parameters'], [
            0 => $this->defaultNull, // new_location
        ]);
        $classDef['methods']['__setSoapHeaders']['parameters'] = \array_replace_recursive($classDef['methods']['__setSoapHeaders']['parameters'], [
            0 => $this->defaultNull, // new_location
        ]);
        return $classDef;
    }
}
