<?php

namespace bdk\Test\Debug\Fixture;

/**
 * PhpDoc Summary
 *
 * @link https://mlocati.github.io/articles/php-type-hinting.html Handy reference for types by php version
 */
class Php71
{
    // constants
    //   type declarations for constants not until php 8.3
    //   cannot be nullable

    // properties
    //   type introduced in php 7.4 (incl nullabl)

    public function nullableArray(?array $arg1): ?array
    {
        return $arg1;
    }

    public function nullableBool(?bool $arg1): ?bool
    {
        return $arg1;
    }

    public function nullableCallable(?callable $arg1): ?callable
    {
        return $arg1;
    }

    public function nullableFloat(?float $arg1): ?float
    {
        return $arg1;
    }

    public function nullableInt(?int $arg1): ?int
    {
        return $arg1;
    }

    public function nullableIterable(?iterable $arg1): ?iterable
    {
        return $arg1;
    }

    /**
     * PHP type not until php 7.2 (nullable or otherwise)
     *
     * @param object|null $arg1 Some object
     *
     * @return object|null
     */
    public function nullableObject($arg1)
    {
        return $arg1;
    }

    public function nullableSelf(?self $arg1): ?self
    {
        return $arg1;
    }

    /**
     * As return type: Php 8.0 (nullable or otherwise)
     * As a parameter:  Not a thing
     *
     * @return static|null
     */
    public function nullableStatic()
    {
        return $this;
    }

    public function nullableString(?string $arg1): ?string
    {
        return $arg1;
    }

    public function nullableClassname(?TestObj $arg1): ?TestObj
    {
    }

    /**
     * Void isn't nullable
     *
     * @return void
     */
    public function void(): void
    {
    }
}
