<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use PDO as PdoBase;

\bdk\Debug::getInstance()->proxyManager->autoloadProxyClass('PDO');

/**
 * A PDO decorator/proxy which traces statements
 *
 * @mixin \bdk\Proxy\ProxyTrait
 * @mixin \PDO
 */
class Pdo extends \PDOProxy
{
    /**
     * Constructor
     *
     * Two signatures:
     * __construct($dsn, $username, $password, $options, $debug)
     * __construct($pdo, $debug)
     *
     * $debug param:
     *    if not passed, will create PDO channel on singleton instance
     *    if root channel is specified, will create a PDO channel
     *
     * @param string|PdoBase    $dsn      Data Source Name (or PDO instance if using 2nd signature)
     * @param string|Debug|null $username (optional) The user name for the DSN string (or Debug instance if using 2nd signature)
     * @param string|null       $password (optional) The password for the DSN string
     * @param array|null        $options  (optional) A key=>value array of driver-specific connection options.
     * @param Debug|null        $debug    (optional) Specify Debug instance (1st signature only)
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($dsn, $username = null, $password = null, array $options = [], $debug = null) // PdoBase $pdo, $debug = null
    {
        \bdk\Debug\Utility\PhpType::assertType($debug, 'bdk\Debug|null', 'debug');

        $subject = $dsn instanceof PdoBase
            ? $dsn
            : new PdoBase($dsn, $username, $password, $options);
        $debug = $username instanceof Debug
            ? $username
            : $debug;

        $listener = new PdoProxyListener($debug);

        $this->setSubject($subject);
        $this->setListener($listener);
    }
}
