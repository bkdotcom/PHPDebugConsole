<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use CApplicationComponent;
use CModel;
use CWebApplication;
use Exception;
use Yii;

/**
 * Collect Pdo info
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class UserInfo
{
	protected $component;

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
     * Log current user info
     *
     * @return void
     */
    public function log()
    {
        if ($this->component->shouldCollect('user') === false) {
            return;
        }

        $user = Yii::app()->user;
        if (\method_exists($user, 'getIsGuest') && $user->getIsGuest()) {
            return;
        }

        $channelOpts = array(
            'channelIcon' => 'fa fa-user-o',
            'nested' => false,
        );
        $debug = $this->component->debug->rootInstance->getChannel('User', $channelOpts);

        $this->logIdentityData($user, $debug);
        $this->logAuthClass($debug);
    }

    /**
     * Log user attributes
     *
     * @param CApplicationComponent $user  User instance (web or console)
     * @param Debug                 $debug Debug instance
     *
     * @return void
     */
    private function logIdentityData(CApplicationComponent $user, Debug $debug)
    {
        $identityData = $user->model->attributes;
        if ($user->model instanceof CModel) {
            $identityData = array();
            foreach ($user->model->attributes as $key => $val) {
                $key = $user->model->getAttributeLabel($key);
                $identityData[$key] = $val;
            }
        }
        $debug->table(\get_class($user), $identityData);
    }

    /**
     * Log auth & access manager info
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logAuthClass(Debug $debug)
    {
        try {
            $yiiApp = Yii::app();

            if (!($yiiApp instanceof CWebApplication)) {
                return;
            }
            $authManager = $yiiApp->getAuthManager();
            $debug->log('authManager class', $debug->abstracter->crateWithVals(
                \get_class($authManager),
                array(
                    'type' => Type::TYPE_IDENTIFIER,
                    'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
                )
            ));

            $accessManager = $yiiApp->getComponent('accessManager');
            if ($accessManager) {
                $debug->log('accessManager class', $debug->abstracter->crateWithVals(
                    \get_class($accessManager),
                    array(
                        'type' => Type::TYPE_IDENTIFIER,
                        'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
                    )
                ));
            }
        } catch (Exception $e) {
            $debug->error('Exception logging user info');
        }
    }
}
