<?php

namespace bdk\Debug\Collector\SoapClient;

/**
 * @phpcs:disable Generic.Classes.DuplicateClassName.Found
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
