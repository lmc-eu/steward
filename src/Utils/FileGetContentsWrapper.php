<?php declare(strict_types=1);

namespace Lmc\Steward\Utils;

/**
 * Wrap system file_get_contents to allow its simple mocking in tests.
 */
class FileGetContentsWrapper
{
    /**
     * @return false|string The function returns the read data or false on failure.
     */
    public function fileGetContents(string $filename)
    {
        return file_get_contents($filename);
    }
}
