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

    /** The <b>most</b> important meal */
    case BREAKFAST;

    #[ExampleCaseAttribute]
    case LUNCH;
    /** What's for dinner? */
    case DINNER;

    /**
     * Prepare a meal
     *
     * @param string $meal  enum
     * @param string $extra constant
     *
     * @return void
     */
    public static function prepare($meal = self::BREAKFAST, $extra = self::REGULAR_CONSTANT) {
    }
}
