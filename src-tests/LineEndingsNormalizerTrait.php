<?php

namespace Lmc\Steward;

trait LineEndingsNormalizerTrait
{
    /**
     * Normalize line-endings to LF (\n)
     *
     * @param string $string
     * @return mixed
     */
    private function normalizeLineEndings($string)
    {
        $string = preg_replace('~(*BSR_ANYCRLF)\R~', "\n", $string);

        return $string;
    }
}
