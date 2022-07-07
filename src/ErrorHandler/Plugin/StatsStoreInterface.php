<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.2
 */

namespace bdk\ErrorHandler\Plugin;

use bdk\ErrorHandler\Error;

/**
 * Interface for storing and retrieving error statistics
 */
interface StatsStoreInterface
{
    /**
     * Adds/Update/Store this error's stats
     *
     * @param Error $error Error instance
     *
     * @return bool
     */
    public function errorUpsert(Error $error);

    /**
     * Get stats for given error
     *
     * @param Error $error Error instance
     *
     * @return array returns empty array if no stats
     */
    public function findByError(Error $error);

    /**
     * Get stats for given error hash
     *
     * @param string $hash Error hash string
     *
     * @return array returns empty array if no stats
     */
    public function findByHash($hash);

    /**
     * Clear data
     *
     * @return void
     */
    public function flush();

    /**
     * Return list of errors that have
     * not occured since their cutoff
     * have occured since their last email
     *
     * @return array
     */
    public function getSummaryErrors();
}
