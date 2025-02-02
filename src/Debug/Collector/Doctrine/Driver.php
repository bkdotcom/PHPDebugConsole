<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2024-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Collector\Doctrine;

use bdk\Debug;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Doctrine driver middleware for logging
 */
class Driver extends AbstractDriverMiddleware
{
    /** @var Debug */
    protected $debug;

    /**
     * Constructor
     *
     * @param DriverInterface $driver Wrapped Driver instance
     * @param Debug           $debug  Debug instance
     */
    public function __construct(
        DriverInterface $driver,
        Debug $debug
    )
    {
        parent::__construct($driver);
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function connect(
        #[\SensitiveParameter]
        array $params
    ): ConnectionInterface
    {
        return new Connection(
            parent::connect($params),
            $params,
            $this->debug
        );
    }
}
