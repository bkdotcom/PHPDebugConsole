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

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Collector\Pdo;
use CApplicationComponent;
use CDbConnection;
use ReflectionObject;
use Yii;

/**
 * Collect Pdo info
 */
class PdoCollector
{
    /** @var CApplicationComponent */
    protected $component;

    /** @var array<string,Pdo> */
    protected $pdoInstances = array();

	/**
	 * Constructor
	 *
	 * @param CApplicationComponent $component Debug component
	 */
	public function __construct(CApplicationComponent $component)
	{
		$this->component = $component;
	}

    /**
     * Setup up PDO collector
     * Log to PDO channel
     *
     * @param CDbConnection|null $dbConnection CDbConnection instance
     *
     * @return void
     */
    public function collect($dbConnection = null)
    {
        \bdk\Debug\Utility::assertType($dbConnection, 'CDbConnection');

        if ($this->component->shouldCollect('pdo') === false) {
            return;
        }
        $dbConnection = $dbConnection ?: Yii::app()->db;
        $dbConnection->active = true; // creates pdo obj
        $pdo = $dbConnection->pdoInstance;
        if ($pdo instanceof Pdo) {
            // already wrapped
            return;
        }
        $connectionString = $dbConnection->connectionString;
        $pdoChannel = $this->getChannel($dbConnection);
        $pdoCollector = new Pdo($pdo, $pdoChannel);
        $this->updateDbConnection($dbConnection, $pdoCollector);
        $this->pdoInstances[$connectionString] = $pdoCollector;
    }

    /**
     * Get PDO instance for given connection string
     *
     * @param string $connectionString connection string
     *
     * @return Pdo
     */
    public function getInstance($connectionString)
    {
        return $this->pdoInstances[$connectionString];
    }

    /**
     * Get PDO Debug Channel for given db connection
     *
     * @param CDbConnection $dbConnection CDbConnection instance
     *
     * @return Debug
     */
    private function getChannel(CDbConnection $dbConnection)
    {
        $channelName = 'PDO';
        if (\strpos($dbConnection->connectionString, 'master=true')) {
            $channelName .= ' (master)';
        } elseif (\strpos($dbConnection->connectionString, 'slave=true')) {
            $channelName .= ' (slave)';
        }
        // nest the PDO channel under our Yii channel
        return $this->component->debug->getChannel($channelName, array(
            'channelIcon' => ':database:',
            'channelShow' => false,
        ));
    }

    /**
     * Attach PDO Collector to dbConnection
     *
     * @param CDbConnection $dbConnection CDbConnection instance
     * @param Pdo           $pdoCollector PDO collector instance
     *
     * @return void
     */
    private function updateDbConnection(CDbConnection $dbConnection, Pdo $pdoCollector)
    {
        $dbRefObj = new ReflectionObject($dbConnection);
        while (!$dbRefObj->hasProperty('_pdo')) {
            $dbRefObj = $dbRefObj->getParentClass();
            if ($dbRefObj === false) {
                $this->component->debug->warn('unable to initiate PDO collector');
            }
        }
        $pdoPropObj = $dbRefObj->getProperty('_pdo');
        $pdoPropObj->setAccessible(true);
        $pdoPropObj->setValue($dbConnection, $pdoCollector);
    }
}
