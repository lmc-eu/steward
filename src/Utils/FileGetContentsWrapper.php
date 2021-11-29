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
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PHP\r\n",
            ],
        ];

        $context = stream_context_create($options);

        return file_get_contents($filename, false, $context);
    }
}
