<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Framework\Yii2;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Framework\Yii2\Module as DebugModule;
use bdk\Debug\LogEntry;
use DateTime;
use Exception;
use Yii;
use yii\rbac\ManagerInterface as rbacManagerInterface;

/**
 * Log current user info
 */
class LogUser
{
    /** @var Debug */
    protected $debug;

    /** @var DebugModule */
    private $debugModule;

    /**
     * Constructor
     *
     * @param DebugModule $debugModule Debug module
     */
    public function __construct(DebugModule $debugModule)
    {
        $this->debug = $debugModule->debug;
        $this->debugModule = $debugModule;
    }

    /**
     * Log current user info
     *
     * @return void
     */
    public function log()
    {
        if ($this->debugModule->shouldCollect('user') === false) {
            return;
        }

        $user = $this->debugModule->module->get('user', false);
        if ($user === null || $user->isGuest) {
            return;
        }

        $debug = $this->debug->rootInstance->getChannel('User');
        $debug->eventManager->subscribe(Debug::EVENT_LOG, function (LogEntry $logEntry) {
            $captions = ['roles', 'permissions'];
            $isRolesPermissions = $logEntry['method'] === 'table' && \in_array($logEntry->getMeta('caption'), $captions, true);
            if (!$isRolesPermissions) {
                return;
            }
            $logEntry['args'] = [$this->tableTsToString($logEntry['args'][0])];
        });

        $this->logUserIdentity($debug);
        $this->logUserRolesPermissions($debug);
    }

    /**
     * Log user Identity attributes
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logUserIdentity(Debug $debug)
    {
        $user = $this->debugModule->module->get('user', false);
        $identityData = $user->identity->attributes;
        if ($user->identity instanceof Model) {
            $identityData = array();
            foreach ($user->identity->attributes as $key => $val) {
                $key = $user->identity->getAttributeLabel($key);
                $identityData[$key] = $val;
            }
        }
        $debug->table($identityData);
    }

    /**
     * Log user permissions
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logUserRolesPermissions(Debug $debug)
    {
        try {
            $authManager = Yii::$app->getAuthManager();
            if (!($authManager instanceof rbacManagerInterface)) {
                return;
            }
            $user = $this->debugModule->module->get('user', false);
            $cols = [
                'description',
                'ruleName',
                'data',
                'createdAt',
                'updatedAt',
            ];
            $debug->table('roles', $authManager->getRolesByUser($user->id), $cols);
            $debug->table('permissions', $authManager->getPermissionsByUser($user->id), $cols);
        } catch (Exception $e) {
            $debug->error('Exception logging user roles and permissions', $e);
        }
    }

    /**
     * Convert unix-timestamps to strings
     *
     * @param array $rows table rows
     *
     * @return array rows
     */
    private function tableTsToString($rows)
    {
        foreach ($rows as $i => $row) {
            $tsCols = ['createdAt', 'updatedAt'];
            $nonEmptyTsVals = \array_filter(\array_intersect_key($row, \array_flip($tsCols)));
            foreach ($nonEmptyTsVals as $key => $val) {
                $val = $val instanceof Abstraction
                    ? $val['value']
                    : $val;
                $datetime = new DateTime('@' . $val);
                $rows[$i][$key] = \str_replace('+0000', '', $datetime->format('Y-m-d H:i:s T'));
            }
        }
        return $rows;
    }
}
