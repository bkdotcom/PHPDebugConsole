<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Collector;

 use bdk\Debug;
 use bdk\Debug\Collector\Doctrine\Driver;
 use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
 use Doctrine\DBAL\Driver as DriverInterface;
 use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

 /**
  * Doctrine 3.x requires php 7.3+
  * Doctrine 3.2 introduces middleware for logging
  * Doctrine 3.3 adds AbstractXxxxxMiddleware classes (which we use)
  * Doctrine 3.4 deprecates SqlLogger
  */
#[AsMiddleware(priority: -10)]
class DoctrineMiddleware implements MiddlewareInterface
{
    /** @var Debug */
    protected $debug;

    /** @var string */
    protected $icon = ':database:';

    /**
     * Constructor
     *
     * @param Debug|null $debug Debug instance
     */
    public function __construct(?Debug $debug = null)
    {
        $channelKey = 'Doctrine';
        $channelOptions = array(
            'channelIcon' => $this->icon,
            'channelName' => 'Doctrine',
        );
        if (!$debug) {
            $debug = Debug::getChannel($channelKey, $channelOptions);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($channelKey, $channelOptions);
        }
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver, $this->debug);
    }
}
