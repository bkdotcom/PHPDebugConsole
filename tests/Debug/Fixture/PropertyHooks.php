<?php

namespace bdk\Test\Debug\Fixture;

/**
 * PHP 8.4 property hooks
 */
class PropertyHooks
{
    public static array $static = [];

    public ?string $backedGetOnly {
        get => $this->backedGetOnly;
    }

    public ?string $backedSetOnly {
        set (?string $value) {
            $this->backedSetOnly = $value;
        }
    }

    public ?string $backedGetAndSet {
        set (?string $value) {
            $this->backedGetAndSet = $value;
        }

        get => $this->backedGetAndSet;
    }

    public $things = [];

    public string $virtualGetOnly {
        get => \implode(', ', $this->things);
    }

    /**
     * Write only property
     *
     * @var string
     */
    public string $virtualSetOnly {
        set (string $value) {
            $this->things[] = $value;
        }
    }

    public string $virtualGetAndSet {
        set (string $value) {
            $this->things[] = $value;
        }

        get => \implode(', ', $this->things);
    }
}
