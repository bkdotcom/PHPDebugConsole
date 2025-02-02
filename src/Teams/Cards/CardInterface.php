<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Cards;

use JsonSerializable;

/**
 * Card interface
 */
interface CardInterface extends JsonSerializable
{
    /**
     * Returns message card array
     *
     * @return array
     */
    public function getMessage();
}
