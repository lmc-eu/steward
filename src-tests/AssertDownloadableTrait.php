<?php declare(strict_types=1);

namespace Lmc\Steward;

trait AssertDownloadableTrait
{
    protected function assertIsDownloadable(string $url): void
    {
        $context = stream_context_create(['http' => ['method' => 'HEAD', 'ignore_errors' => true]]);
        $fd = fopen($url, 'rb', false, $context);
        fclose($fd);

        $this->assertContains('HTTP/1.1 200 OK', $http_response_header, 'Error downloading from "' . $url . '"');
    }
}
