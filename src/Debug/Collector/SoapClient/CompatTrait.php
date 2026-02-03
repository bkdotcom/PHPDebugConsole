<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2024-2025 Brad Kent
 * @since     3.5.1
 */

 /*
    PHP 8.5 updates __doRequest signature
 */

namespace bdk\Debug\Collector\SoapClient;

$traitExists = \trait_exists(__NAMESPACE__ . '\\CompatTrait', false);

if (PHP_VERSION_ID >= 80500 && !$traitExists) {
    require __DIR__ . '/CompatTrait_8.5.php';
} elseif (!$traitExists) {
    /**
     * @phpcs:disable Generic.Classes.DuplicateClassName.Found
     */
    trait CompatTrait
    {
        /**
         * {@inheritDoc}
         */
        #[\ReturnTypeWillChange]
        public function __doRequest($request, $location, $action, $version, $oneWay = 0)
        {
            return $this->doDoRequest($request, $location, $action, $version, $oneWay);
        }
    }
}
