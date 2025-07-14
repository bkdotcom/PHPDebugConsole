<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Collector\StatementInfo;
use bdk\Debug\Collector\StatementInfoLogger;
use bdk\Debug\Utility\Sql;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Wordpress uses procedural mysqli functions so we're stuck with wordpress' logging filter
 */
class WpDb extends AbstractComponent implements SubscriberInterface
{
    const I18N_DOMAIN = 'wordpress';

    /** @var array<string,mixed> */
    protected $cfg = array(
        'enabled' => true,
    );

    /** @var Debug */
    protected $debug;

    /** @var StatementInfoLogger */
    protected $statementInfoLogger;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_OUTPUT => 'onOutput',
        );
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @param Event $event Debug::EVENT_BOOTSTRAP event object
     *
     * @return void
     */
    public function onBootstrap(Event $event)
    {
        $this->debug = $event->getSubject()->getChannel('db', array(
            'channelIcon' => ':database:',
            'channelName' => 'channel.db|trans',
            'channelShow' => false,
        ));

        $this->statementInfoLogger = new StatementInfoLogger($this->debug);

        if ($this->cfg['enabled'] === false) {
            return;
        }

        if (!\defined('SAVEQUERIES')) {
            \define('SAVEQUERIES', true);
        } elseif (!SAVEQUERIES) {
            $this->debug->warn($this->debug->i18n->trans('savequeries.false', self::I18N_DOMAIN));
        }
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @return void
     */
    public function onOutput()
    {
        if ($this->cfg['enabled'] === false) {
            return;
        }
        $debug = $this->debug;
        $debug->groupSummary(0);
        $groupParams = \array_filter([
            'MySqli',
            $GLOBALS['wpdb']->dbh->host_info,
            $debug->meta(array(
                'argsAsParams' => false,
                'icon' => ':database:',
                'level' => 'info',
            )),
        ]);
        \call_user_func_array([$debug, 'groupCollapsed'], $groupParams);
        $this->logRuntime($debug);
        $debug->groupEnd();  // groupCollapsed
        $debug->groupEnd();  // groupSummary
    }

    /**
     * Handle WordPress's `log_query_custom_data` filter
     *
     * @param array  $queryData Custom query data.
     * @param string $query     The query's SQL.
     * @param float  $duration  Total time spent on the query, in seconds.
     *
     * @return array
     */
    public function onQuery($queryData, $query, $duration)
    {
        $statementInfo = new StatementInfo($query);
        $statementInfo->setDuration($duration);
        $this->statementInfoLogger->log($statementInfo);
        return $queryData;
    }

    /**
     * build connection-string / dsn from connection params
     *
     * @return string
     */
    private function getDsn()
    {
        $dbParams = array(
            'dbname' => $GLOBALS['wpdb']->dbname,
            'host' => $GLOBALS['wpdb']->dbhost,
            'isIpv6' => false,
            'password' => $GLOBALS['wpdb']->dbpassword,
            'port' => null,
            'scheme' => 'mysql',
            'socket' => null,
            'user' => $GLOBALS['wpdb']->dbuser,
        );

        $hostData = $GLOBALS['wpdb']->parse_db_host($dbParams['host']);
        if ($hostData) {
            $dbParamsMore = \array_combine(['host', 'port', 'socket', 'isIpv6'], $hostData);
            $dbParams = \array_merge($dbParams, $dbParamsMore);
        }

        return Sql::buildDsn($dbParams);
    }

    /**
     * Log runtime information
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logRuntime(Debug $debug)
    {
        $debug->log($debug->i18n->trans('db.connection-string'), $this->getDsn(), $debug->meta('redact'));
        $debug->log($debug->i18n->trans('runtime.logged-operations'), $this->statementInfoLogger->getLoggedCount());
        $debug->time($debug->i18n->trans('runtime.total-time'), $this->statementInfoLogger->getTimeSpent());
        $serverInfo = $this->serverInfo();
        if ($serverInfo) {
            $debug->log($debug->i18n->trans('db.server-info'), $serverInfo);
        }
        if ($this->statementInfoLogger->prettified() === false) {
            $debug->info('require jdorn/sql-formatter to prettify logged sql statements');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $isFirstConfig = empty($this->cfg['configured']);
        $enabledChanged = isset($cfg['enabled']) && $cfg['enabled'] !== $prev['enabled'];
        if ($enabledChanged === false && $isFirstConfig === false) {
            return;
        }
        $this->cfg['configured'] = true;
        if ($cfg['enabled']) {
            \add_filter('log_query_custom_data', [$this, 'onQuery'], 0, 3);
            return;
        }
        \remove_filter('log_query_custom_data', [$this, 'onQuery'], 0);
    }

    /**
     * `mysqli::stat()`, but parsed
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) -> called via DatabaseTrait
     */
    protected function serverInfo()
    {
        $matches = array();
        \preg_match_all('#([^:]+): ([a-zA-Z0-9.]+)\s*#', $GLOBALS['wpdb']->dbh->stat(), $matches);
        $serverInfo = \array_map(static function ($val) {
            /** @psalm-suppress InvalidOperand */
            return $val * 1;
        }, \array_combine($matches[1], $matches[2]));
        $serverInfo['Version'] = $GLOBALS['wpdb']->dbh->server_info;
        \ksort($serverInfo);
        return $serverInfo;
    }
}
