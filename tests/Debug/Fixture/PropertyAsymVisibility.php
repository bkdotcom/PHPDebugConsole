<?php

namespace bdk\Test\Debug\Fixture;

/**
 * PHP 8.4 Property Asymmetric Visibility
 */
class PropertyAsymVisibility
{
    public static array $static = [];
    public protected(set) ?string $name;
    protected private(set) ?int $age;
}
