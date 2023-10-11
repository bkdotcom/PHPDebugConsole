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

use bdk\Debug;

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
            'addParams' => array(),
            'filter' => FILTER_DEFAULT, // filter applied if getting value from ini val
            'msg' => '',    // (optional) message displayed if assertion fails
            'name' => '',   // ini name
            'operator' => '==',
            'valActual' => '__use_ini_val__',
            'valCompare' => true,
        ), $setting));
        $params = array(
            $this->debug->stringUtil->compare($setting['valActual'], $setting['valCompare'], $setting['operator']),
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
        if (\is_bool($setting['valCompare'])) {
            $setting['filter'] = FILTER_VALIDATE_BOOLEAN;
        } elseif (\is_int($setting['valCompare'])) {
            $setting['filter'] = FILTER_VALIDATE_INT;
        }
        if ($setting['valActual'] === '__use_ini_val__') {
            $setting['valActual'] = \filter_var(\ini_get($setting['name']), $setting['filter']);
        }
        if ($setting['msg']) {
            return $setting;
        }
        $valFriendly = $this->valFriendly($setting);
        $setting['msg'] = \sprintf(
            '%s %s',
            $setting['operator'] === '==' ? 'should be' : 'should not be',
            $valFriendly
        );
        if (\substr($valFriendly, 0, 1) === '<') {
            $setting['addParams'][] = Debug::meta('sanitize', false);
        }
        return $setting;
    }

    /**
     * Get the friendly expected / not-expected value for default message
     *
     * @param array $setting setting options
     *
     * @return string
     */
    private function valFriendly(array $setting)
    {
        if ($setting['filter'] === FILTER_VALIDATE_BOOLEAN) {
            return $setting['valCompare'] ? 'enabled' : 'disabled';
        }
        if ($setting['valCompare'] === '') {
            return 'empty';
        }
        return \is_string($setting['valCompare'])
            ? '<span class="t_string">' . \htmlspecialchars($setting['valCompare']) . '</span>'
            : $setting['valCompare'];
    }
}
