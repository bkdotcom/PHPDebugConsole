<?php

/**
 * tests/Debug/Collector/DoctrineLoggerTest contains
 *  covers \bdk\Debug\Collector\DoctrineLogger
 * which implements \Doctrine\DBAL\Logging\SQLLogger
 * which may not exist
 *
 * tests/bootstrap.php adds a spl_autoload_register for this placeholder
 */

namespace Doctrine\DBAL\Logging;

interface SQLLogger
{
}
