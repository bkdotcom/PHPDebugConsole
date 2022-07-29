<?php

namespace bdk\Test\Debug\Fixture\Enum;

enum MealsBacked: string
{
    const REGULAR_CONSTANT = 'test';
    const ENUM_VALUE = self::DINNER;

    case BREAKFAST = 'breakfast';
    case LUNCH = 'lunch';
    case DINNER = 'dinner';
}
