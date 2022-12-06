<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.2
 */

namespace bdk\ErrorHandler\Plugin;

use bdk\ErrorHandler;
use bdk\ErrorHandler\AbstractComponent;
use bdk\ErrorHandler\Error;
use bdk\ErrorHandler\Plugin\StatsStoreFile;
use bdk\PubSub\SubscriberInterface;

/**
 * Keep track of when errors were last emailed or other
 *
 * @property array $summaryErrors
 */
class Stats extends AbstractComponent implements SubscriberInterface
{
    protected $dataStore;

    /**
     * Constructor
     *
     * @param array $cfg Configuration
     */
    public function __construct($cfg = array())
    {
        $this->cfg = array(
            'dataStoreFactory' => static function () {
                return new StatsStoreFile();
            }
        );
        $this->setCfg(\array_merge($this->cfg, $cfg));
    }

    /**
     * Get stats for given error
     *
     * @param string|Error $errorOrHash error-hash, or Error instance
     *
     * @return array returns empty array if no stats
     */
    public function find($errorOrHash)
    {
        return $errorOrHash instanceof Error
            ? $this->dataStore->findByError($errorOrHash)
            : $this->dataStore->findByHash($errorOrHash);
    }

    /**
     * Clear data
     *
     * @return void
     */
    public function flush()
    {
        $this->dataStore->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            ErrorHandler::EVENT_ERROR => array(
                array('onErrorHighPri', PHP_INT_MAX),
                array('onErrorLowPri', PHP_INT_MAX * -1),
            ),
        );
    }

    /**
     * Return list of errors that have
     * not occured since their cutoff
     * have occured since their last email
     *
     * @return array
     */
    public function getSummaryErrors()
    {
        return $this->dataStore->getSummaryErrors();
    }

    /**
     * Initialize stats on error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorHighPri(Error $error)
    {
        $error['stats'] = array(
            'tsAdded' => \time(),
            'tsLastOccur' => null,
            'count' => 1,
        );
        $errorStats = $this->dataStore->findByError($error);
        if ($errorStats) {
            unset($errorStats['info']);
            $errorStats['count'] ++;
            $error['stats'] = \array_merge($error['stats'], $errorStats);
        }
    }

    /**
     * Save the stats to file
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorLowPri(Error $error)
    {
        $this->dataStore->errorUpsert($error);
    }

    /**
     * Handle updated cfg values
     *
     * @param array $cfg  new config values
     * @param array $prev previous config values
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $cfgDataStore = \array_diff_key($cfg, array('dataStoreFactory' => null));
        if (isset($cfg['dataStoreFactory'])) {
            $this->dataStore = $cfg['dataStoreFactory']($cfgDataStore);
        } elseif ($cfgDataStore) {
            $this->dataStore->setCfg($cfgDataStore);
        }
    }
}
