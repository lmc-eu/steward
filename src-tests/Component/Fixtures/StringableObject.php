<?php declare(strict_types=1);

namespace Lmc\Steward\Component\Fixtures;

/**
 * Object having __toString
 */
class StringableObject
{
    /** @var string */
    protected $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function __toString(): string
    {
        return '__toString() called: ' . $this->string;
    }
}
