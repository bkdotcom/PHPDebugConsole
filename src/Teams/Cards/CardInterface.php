<?php

namespace bdk\Teams\Cards;

use JsonSerializable;

/**
 *
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
