<?php declare(strict_types=1);

namespace Lmc\Steward;

trait LineEndingsNormalizerTrait
{
    /**
     * Normalize line-endings to LF (\n)
     */
    private function normalizeLineEndings(string $string)
    {
        $string = preg_replace('~(*BSR_ANYCRLF)\R~', "\n", $string);

        return $string;
    }
}
