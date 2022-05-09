<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Plugin;

/**
 * Provite assertSetting method
 */
trait AssertSettingTrait
{
    /**
     * Assert ini setting is/is-not specified value
     *
     * @param array $setting setting name, "type", comparison value, operator
     *
     * @return void
     */
    protected function assertSetting($setting)
    {
        $setting = $this->assertSettingPrep(\array_merge(array(
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'msg' => '',    // (optional) message displayed if assertion fails
            'name' => '',   // ini name
            'operator' => '==',
            'valActual' => '__use_ini_val__',
            'valCompare' => true,
            'addParams' => array(),
        ), $setting));
        $assert = \is_array($setting['valCompare'])
            ? \in_array($setting['valActual'], $setting['valCompare'], true)
            : $this->debug->stringUtil->compare($setting['valActual'], $setting['valCompare'], $setting['operator']);
        $params = array(
            $assert,
            $setting['name']
                ? '%c' . $setting['name'] . '%c: ' . $setting['msg']
                : $setting['msg'],
        );
        $cCount = \substr_count($params[1], '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $params[] = 'font-family:monospace;';
            $params[] = '';
        }
        $params = \array_merge($params, $setting['addParams']);
        \call_user_func_array(array($this->debug, 'assert'), $params);
    }

    /**
     * Merge in default valActual & msg values
     *
     * @param array $setting setting name, "type", comparison value, operator
     *
     * @return array
     */
    private function assertSettingPrep($setting)
    {
        if ($setting['valActual'] === '__use_ini_val__') {
            $setting['valActual'] = \filter_var(\ini_get($setting['name']), $setting['filter']);
        }
        $valFriendly = $setting['filter'] === FILTER_VALIDATE_BOOLEAN
            ? ($setting['valCompare'] ? 'enabled' : 'disabled')
            : $setting['valCompare'];
        if (!$setting['msg']) {
            $msgDefault = $setting['operator'] === '=='
                ? 'should be ' . $valFriendly
                : 'should not be ' . $valFriendly;
            $setting['msg'] = $msgDefault;
        }
        return $setting;
    }
}
