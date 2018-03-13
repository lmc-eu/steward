<?php declare(strict_types=1);

namespace Lmc\Steward\Utils;

class Strings
{
    /**
     * Convert given string to safe filename (and keep string case).
     *
     * No transliteration, conversion etc. is done - unsafe characters are simply replaced with hyphen.
     */
    public static function toFilename(string $string): string
    {
        return trim(preg_replace('/([^a-zA-Z0-9]|-)+/', '-', $string), '-');
    }
}
