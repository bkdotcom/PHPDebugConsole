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

\bdk\Debug::getInstance()->proxyManager->autoloadProxyClass('mysqli');

/**
 * mysqli "proxy" with debugging
 *
 * Unable to truly proxy mysqli due to how mysqli properties are resolved by internal property handlers before magic methods
 *   (mysqli properties can not be proxied via __get/__set)
 *
 * Must instantiate and use this class as mysqli replacement
 *
 * @mixin \bdk\Proxy\ProxyTrait
 * @mixin \mysqli
 */
class MySqli extends \mysqliProxy
{
    /**
     * Constructor
     *
     * @param string     $host     host name or IP
     * @param string     $username MySQL user name
     * @param string     $passwd   password
     * @param string     $dbname   default database used when performing queries
     * @param int        $port     port number
     * @param string     $socket   socket or named pipe that should be used
     * @param Debug|null $debug    (optional) Specify Debug instance
     *                               if not passed, will create MySqli channel on singleton instance
     *                               if root channel is specified, will create a MySqli channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null, $debug = null) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        \bdk\Debug\Utility\PhpType::assertType($debug, 'bdk\Debug|null', 'debug');

        $listener = new MySqliProxyListener($debug);

        $this->setSubject($this);
        $this->setListener($listener);

        $constructorArgs = \array_slice(\func_get_args(), 0, 6);
        $constructorArgs = $this->getConstructorArgs($constructorArgs, $listener);

        $this->proxyCall('__construct', $constructorArgs);
    }

    /**
     * Call mysqli constructor with appropriate params
     *
     * Default values will be used for all empty values
     *
     * @param array               $args     host, username, etc
     * @param MySqliProxyListener $listener Listener instance
     *
     * @return array
     */
    private function getConstructorArgs(array $args, MySqliProxyListener $listener)
    {
        if (empty($args)) {
            /*
                Calling the constructor with no parameters is the same as calling mysqli_init().
            */
            return [];
        }
        $params = $listener->connectionParamsKeyValue($args);
        $paramsDefault = array(
            'dbname' => null,
            'host' => \ini_get('mysqli.default_host') ?: '127.0.0.1',
            'password' => \ini_get('mysqli.default_pw'),
            'port' => \ini_get('mysqli.default_port'),
            'socket' => \ini_get('mysqli.default_socket'),
            'username' => \ini_get('mysqli.default_user'),
        );
        $params = \array_merge(
            \array_fill_keys(\array_keys($paramsDefault), null),
            \array_filter($paramsDefault),
            \array_filter($params)
        );
        return [
            $params['host'],
            $params['username'],
            $params['password'],
            $params['dbname'],
            $params['port'],
            $params['socket'],
        ];
    }
}
