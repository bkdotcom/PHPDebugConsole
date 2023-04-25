<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Route;

use bdk\ErrorHandler\Error;

/**
 * common "shouldSend" method
 */
trait ErrorThrottleTrait
{
    /**
     * Should we send a notification for this error?
     *
     * @param Error  $error    Error instance
     * @param string $statsKey name under which we store stats
     *
     * @return bool
     */
    private function shouldSend(Error $error, $statsKey)
    {
        if ($error['throw']) {
            // subscriber that set throw *should have* stopped error propagation
            return false;
        }
        if (($error['type'] & $this->cfg['errorMask']) !== $error['type']) {
            return false;
        }
        if ($error['isFirstOccur'] === false) {
            return false;
        }
        if ($error['inConsole']) {
            return false;
        }
        $error['stats'] = \array_merge(array(
            $statsKey => array(
                'countSince' => 0,
                'timestamp'  => null,
            ),
        ), $error['stats'] ?: array());
        $tsCutoff = \time() - $this->cfg['throttleMin'] * 60;
        if ($error['stats'][$statsKey]['timestamp'] > $tsCutoff) {
            // This error was recently sent
            $error['stats'][$statsKey]['countSince']++;
            return false;
        }
        $error['stats'][$statsKey]['timestamp'] = \time();
        return true;
    }
}
