<?php

namespace bdk\Test\Debug\Fixture;

/**
 * Test Php 8.0 features
 */
class Php81
{
    final public const FINAL_CONST = 'foo';

    public function __construct(
        public readonly string $title,
    ) {}
}
