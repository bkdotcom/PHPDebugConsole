<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\StatementInfo;
use bdk\PubSub\Event;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Log Doctrine queries
 *
 * http://doctrine-project.org
 */
class DoctrineLogger implements SQLLogger
{
    use DatabaseTrait;

    /** @var string */
    protected $icon = 'fa fa-database';

    /** @var StatementInfo|null */
    protected $statementInfo;

    /** @var Connection */
    private $connection;

    /** @var Debug */
    private $debug;

    /**
     * Constructor
     *
     * @param Connection $connection Optional Doctrine DBAL connection instance
     *                                  pass to log connection info
     * @param Debug      $debug      Optional DebugInstance
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Connection $connection = null, Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::getChannel('Doctrine', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Doctrine', array('channelIcon' => $this->icon));
        }
        $this->connection = $connection;
        $this->debug = $debug;
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, array($this, 'onDebugOutput'), 1);
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
            $debug->meta(array(
                'argsAsParams' => false,
                'icon' => $this->icon,
                'level' => 'info',
            )),
        ));
        \call_user_func_array(array($debug, 'groupCollapsed'), $groupParams);
        $debug->log('logged operations: ', \count($this->loggedStatements));
        $debug->time('total time', $this->getTimeSpent());
        $debug->log('max memory usage', $debug->utility->getBytes($this->getPeakMemoryUsage()));
        if ($connectionInfo) {
            $debug->log('connection info', $connectionInfo);
        }
        $debug->groupEnd();  // groupCollapsed
        $debug->groupEnd();  // groupSummary
    }

    /**
     * {@inheritDoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        $this->statementInfo = new StatementInfo($sql, $params, $types);
    }

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
