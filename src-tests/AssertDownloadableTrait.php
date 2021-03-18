<?php declare(strict_types=1);

namespace Lmc\Steward;

trait AssertDownloadableTrait
{
    protected function assertIsDownloadable(string $url): void
    {
        $context = stream_context_create(['http' => ['method' => 'HEAD', 'ignore_errors' => true]]);
        $fd = fopen($url, 'rb', false, $context);
        $responseCode = $http_response_header[0];
        fclose($fd);

        $this->assertStringContainsString('200 OK', $responseCode, 'Error downloading from "' . $url . '"');
    }
}
