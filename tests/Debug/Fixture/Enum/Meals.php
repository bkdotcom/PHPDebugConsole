<?php

namespace bdk\Test\Debug\Fixture\Enum;

/**
 * Meals PHPDoc
 */
#[ExampleAttribute]
enum Meals
{
    const REGULAR_CONSTANT = 'test';
    const ENUM_VALUE = self::DINNER;

    /** The most important meal */
    case BREAKFAST;

    #[ExampleCaseAttribute]
    case LUNCH;
    case DINNER;

    static function prepare($meal = self::BREAKFAST) {
    }
}
