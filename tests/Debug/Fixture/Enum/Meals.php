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
    /** What's for dinner? */
    case DINNER;

    public static function prepare($meal = self::BREAKFAST) {
    }
}
