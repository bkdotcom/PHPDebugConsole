<?php

namespace bdk\Debug\Collector\SoapClient;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict otherwise
*/
if (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    /**
     * Trait for PHP 8.5+
     */
    trait CompatTrait
    {
        /**
         * {@inheritDoc}
         */
        #[\ReturnTypeWillChange]
        public function __doRequest($request, $location, $action, $version, $oneWay = 0, $uriParserClass = null)
        {
            return $this->doDoRequest($request, $location, $action, $version, $oneWay, $uriParserClass);
        }
    }
}
