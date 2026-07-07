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
use bdk\Debug\Collector\SimpleCacheProxyListener;
use Psr\SimpleCache\CacheInterface;

\bdk\Debug::getInstance()->proxyManager->autoloadProxyClass('Psr\SimpleCache\CacheInterface');

/**
 * A SimpleCache (PSR-16) proxy for logging SimpleCache operations
 *
 * @mixin \bdk\Proxy\ProxyTrait
 * @mixin \Psr\SimpleCache\CacheInterface
 */
class SimpleCache extends \Psr_SimpleCache_CacheInterfaceProxy
{
    /**
     * Constructor
     *
     * @param CacheInterface $cache SimpleCache instance
     * @param Debug|null     $debug (optional) Specify Debug instance
     *                                if not passed, will create PDO channel on singleton instance
     *                                if root channel is specified, will create a PDO channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(CacheInterface $cache, $debug = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($debug, 'bdk\Debug|null', 'debug');

        $listener = new SimpleCacheProxyListener($debug);

        $this->setSubject($cache);
        $this->setListener($listener);
    }
}
