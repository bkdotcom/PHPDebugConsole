<?php

namespace bdk\Test\Debug\Fixture;

use DateTimeImmutable;

/**
 * Test Php 8.2 readonly class
 */
final readonly class Php82readonly
{
    private string $test;

    /**
     * Constructor
     *
     * @param string $title
     * @param string $status
     * @param int    $publishedAt DateTimeImmuatable would make more sense... kept simple for test
     */
    public function __construct(
        public string $title,
        public string $status,
        public ?int $publishedAt = null,
    )
    {
    }
}
