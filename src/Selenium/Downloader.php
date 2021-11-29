<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

/**
 * Download Selenium standalone server
 */
class Downloader
{
    /** @var string */
    private const DOWNLOAD_URL = 'https://github.com/SeleniumHQ/selenium/releases/download';
    /** @var string Target directory where should be the file saved */
    private $targetDir;
    /** @var Version Version to download */
    private $version;

    /**
     * @param string $targetDir Target directory where should be the file saved
     */
    public function __construct(string $targetDir, Version $version)
    {
        $this->targetDir = rtrim($targetDir, '/');
        $this->version = $version;
    }

    /**
     * Check if file of given version was already downloaded to given directory
     */
    public function isAlreadyDownloaded(): bool
    {
        return file_exists($this->getFilePath());
    }

    /**
     * Get target path of the file
     */
    public function getFilePath(): string
    {
        return $this->targetDir . '/' . $this->assembleFileName();
    }

    /**
     * Get complete URL of the file to download
     */
    public function getFileUrl(): string
    {
        return sprintf(
            '%s/%s/%s',
            self::DOWNLOAD_URL,
            $this->assembleTagName(),
            $this->assembleFileName()
        );
    }

    /**
     * Execute the download
     *
     * @throws \RuntimeException Thrown when file cannot be downloaded
     * @return int Downloaded size in bytes.
     */
    public function download(): int
    {
        $targetPath = $this->getFilePath();

        if (!is_dir(dirname($targetPath))) {
            mkdir(dirname($targetPath), 0777, true);
        }

        $fileUrl = $this->getFileUrl();

        $fp = @fopen($fileUrl, 'rb');
        $responseHeaders = get_headers($fileUrl);
        if (!in_array('HTTP/1.1 200 OK', $responseHeaders, true)) {
            throw new \RuntimeException(sprintf('Error downloading file "%s" (%s)', $fileUrl, $responseHeaders[0]));
        }

        $downloadedSize = file_put_contents($targetPath, $fp);
        // @codeCoverageIgnoreStart
        if (!$downloadedSize) {
            throw new \RuntimeException(sprintf('Error saving file to path "%s"', $targetPath));
        }
        // @codeCoverageIgnoreEnd

        return $downloadedSize;
    }

    private function assembleTagName(): string
    {
        return sprintf(
            'selenium-%s',
            $this->version->toString()
        );
    }

    /**
     * Get name of the jar file
     */
    protected function assembleFileName(): string
    {
        $baseName = ($this->version->getMajor() === '4' ? 'selenium-server' : 'selenium-server-standalone');

        return sprintf('%s-%s.jar', $baseName, $this->version->toString());
    }
}
