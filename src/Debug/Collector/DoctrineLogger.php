<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\Doctrine\LoggerCompatTrait;
use bdk\Debug\Collector\StatementInfo;
use bdk\PubSub\Event;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\SQLLogger as SQLLoggerInterface;

/**
 * Log Doctrine queries
 *
 * http://doctrine-project.org
 *
 * Deprecated as of Doctrine 3.2
 */
class DoctrineLogger implements SQLLoggerInterface
{
    use DatabaseTrait;
    use LoggerCompatTrait;

    /** @var StatementInfo|null */
    protected $statementInfo;

    /** @var Connection */
    private $connection;

    /** @var Debug */
    private $debug;

    /**
     * Constructor
     *
     * @param Connection|null $connection Optional Doctrine DBAL connection instance
     *                                      pass to log connection info
     * @param Debug|null      $debug      Optional DebugInstance
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($connection = null, $debug = null)
    {
        \bdk\Debug\Utility::assertType($connection, 'Doctrine\DBAL\Connection');
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');

        if (!$debug) {
            $debug = Debug::getChannel('Doctrine', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Doctrine', array('channelIcon' => $this->icon));
        }
        $this->connection = $connection;
        $this->debug = $debug;
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, [$this, 'onDebugOutput'], 1);
        $this->debug->addPlugin($debug->pluginHighlight);
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onDebugOutput(Event $event)
    {
        $debug = $event->getSubject();
        $connectionInfo = $this->connection
            ? $this->connection->getParams()
            : array();
        $debug->groupSummary(0);
        $groupParams = \array_filter(array(
            'Doctrine',
            $connectionInfo
                ? $connectionInfo['url']
                : null,
            $this->meta(array(
                'argsAsParams' => false,
                'level' => 'info',
            )),
        ));
        \call_user_func_array([$debug, 'groupCollapsed'], $groupParams);
        $this->logRuntime($debug);
        $debug->groupEnd();  // groupCollapsed
        $debug->groupEnd();  // groupSummary
    }

    // startQuery defined in Doctrine\CompatTrait

    /**
     * {@inheritDoc}
     */
    public function stopQuery()
    {
        $statementInfo = $this->statementInfo;
        $statementInfo->end();
        $statementInfo->appendLog($this->debug);
        $this->loggedStatements[] = $statementInfo;
    }
}
