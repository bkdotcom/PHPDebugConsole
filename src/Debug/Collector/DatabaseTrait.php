<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\StatementInfo;
use bdk\Debug\Collector\StatementInfoLogger;
use Pdo as PdoBase;

/**
 * Used by DoctrineLogger, MySqli, and Pdo
 */
trait DatabaseTrait
{
    /** @var string */
    protected $icon = ':database:';

    /** @var StatementInfoLogger */
    protected $statementInfoLogger;

    /** @var Debug */
    private $debug;

    /** @var array connection params */
    private $params;

    /**
     * Logs StatementInfo
     *
     * @param StatementInfo $info statement info instance
     *
     * @return void
     */
    public function addStatementInfo(StatementInfo $info)
    {
        $this->statementInfoLogger->log($info);
    }

    /**
     * Get StatementInfoLogger
     *
     * @return StatementInfoLogger
     */
    public function getStatementInfoLogger()
    {
        return $this->statementInfoLogger;
    }

    /**
     * Extend me to return the current database name
     *
     * @return string|null
     */
    protected function currentDatabase()
    {
        return null;
    }

    /**
     * Log runtime information
     *
     * @param string $connectionString (optional) connection string
     *
     * @return void
     */
    private function logRuntime($connectionString = null)
    {
        $currentDatabase = $connectionString
            ? null
            : $this->currentDatabase();
        if ($connectionString) {
            $this->debug->log($this->debug->i18n->trans('db.connection-string'), $connectionString, $this->debug->meta('redact'));
        } elseif ($currentDatabase) {
            $this->debug->log('database', $currentDatabase);
        }
        $this->statementInfoLogger->logStats();
        $serverInfo = $this->serverInfo();
        if ($serverInfo) {
            $this->debug->log($this->debug->i18n->trans('db.server-info'), $serverInfo);
        }
    }

    /**
     * Call debug method with styling
     *
     * Replace/wrap %c with style
     *
     * @param string $method  Debug method
     * @param string $message Log message
     *
     * @return void
     */
    protected function logWithStyling($method, $message)
    {
        $params = [
            $message,
        ];
        $cCount = \substr_count($params[0], '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $params[] = 'font-family:monospace;';
            $params[] = '';
        }
        \call_user_func_array([$this->debug, $method], $params);
    }

    /**
     * Get meta argument
     *
     * @param array $values Values to metafy
     *
     * @return array
     */
    protected function meta(array $values = array())
    {
        return $this->debug->meta(\array_merge(array(
            'icon' => $this->debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
        ), $values));
    }

    /**
     * Return server information
     *
     * @param PdoBase $pdo Pdo instance
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) -> called via DatabaseTrait
     */
    protected function pdoServerInfo(PdoBase $pdo)
    {
        $driverName = $pdo->getAttribute(PdoBase::ATTR_DRIVER_NAME);
        // parse server info
        $serverInfo = $driverName !== 'sqlite'
            ? $pdo->getAttribute(PdoBase::ATTR_SERVER_INFO)
            : '';
        $matches = array();
        \preg_match_all('/([^:]+): ([a-zA-Z0-9.]+)\s*/', $serverInfo, $matches);
        $serverInfo = \array_map(static function ($val) {
            /** @psalm-suppress InvalidOperand */
            return $val * 1;
        }, \array_combine($matches[1], $matches[2]));
        $serverInfo['Version'] = $pdo->getAttribute(PdoBase::ATTR_SERVER_VERSION);
        \ksort($serverInfo);
        return $serverInfo;
    }

    /**
     * Extend me to return database server information
     *
     * @return array
     */
    protected function serverInfo()
    {
        return array();
    }

    /**
     * Initialize Debug and StatementInfoLogger
     *
     * @param Debug|null $debug       (optional) Specify Debug instance
     *                                  if not passed, will create MySqli channel on singleton instance
     *                                  if root channel is specified, will create a MySqli channel
     * @param string     $channelName Channel name to use for debug instance
     *
     * @return void
     */
    protected function traitInit($debug, $channelName = 'SQL')
    {
        $channelKey = \strtolower($channelName);
        $channelOptions = array(
            'channelIcon' => $this->icon,
            'channelName' => $channelName,
        );
        if (!$debug) {
            $debug = Debug::getChannel($channelKey, $channelOptions);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($channelKey, $channelOptions);
        }
        $this->debug = $debug;
        $this->statementInfoLogger = new StatementInfoLogger($debug);
    }
}
