<?php

namespace Lmc\Steward\Component\Fixtures;

/**
 * Object having __toString
 */
class StringableObject
{
    /** @var string */
    protected $string;

    function __construct($string)
    {
        $this->string = $string;
    }

    function __toString()
    {
        return '__toString() called: ' . $this->string;
    }
}
