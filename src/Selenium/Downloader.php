<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Assert\Assert;

/**
 * Download Selenium standalone server
 */
class Downloader
{
    /** @var string Selenium storage URL */
    public static $storageUrl = 'https://selenium-release.storage.googleapis.com';
    /** @var string Target directory where should be the file saved */
    private $targetDir;
    /** @var string Version to download */
    private $version;

    /**
     * @param string $targetDir Target directory where should be the file saved
     */
    public function __construct(string $targetDir)
    {
        $this->targetDir = rtrim($targetDir, '/');
    }

    /**
     * Get latest released version of Selenium server. If not found, null is returned.
     */
    public static function getLatestVersion(): ?string
    {
        $data = @file_get_contents(self::$storageUrl);
        if (!$data) {
            return null;
        }

        libxml_use_internal_errors(true); // disable errors from being thrown
        $xml = simplexml_load_string($data);
        if (!$xml) {
            return null;
        }

        $releases = $xml->xpath('//*[text()[contains(.,"selenium-server-standalone")]]');
        $lastRelease = end($releases); // something like "2.42/selenium-server-standalone-2.42.2.jar"
        if ($lastRelease === false) {
            return null;
        }

        $lastRelease = (string) $lastRelease;

        $lastVersion = preg_replace('/.*standalone-(.+)\.jar/', '$1', $lastRelease);
        if ($lastRelease === $lastVersion) { // regexp not matched
            return null;
        }

        return $lastVersion;
    }

    /**
     * @param string $version Version to download
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * Get version that should be downloaded; if not set, attempt to retrieve latest released version
     */
    public function getVersion(): ?string
    {
        if (!$this->version) {
            $this->version = self::getLatestVersion();
        }

        return $this->version;
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
        return $this->targetDir . '/' . $this->getFileName();
    }

    /**
     * Get complete URL of the file to download
     */
    public function getFileUrl(): string
    {
        $version = $this->getVersion();
        Assert::that($version, 'Invalid version (expected format is X.Y.Z)')
            ->notEmpty()
            ->regex('/^\d+\.\d+\.[\da-z\-]+$/i');

        $versionParts = explode('.', $version);

        $devVersion = '';
        if (preg_match('/(\d+)-([[:alnum:]]+)/', $versionParts[2], $matches)) {
            $devVersion = $matches[2];
        }

        $fileUrl = self::$storageUrl . '/' . $versionParts[0] . '.' . $versionParts[1]
            . (!empty($devVersion) ? '-' . $devVersion : '')
            . '/' . $this->getFileName();

        return $fileUrl;
    }

    /**
     * Execute the download
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
        if (mb_strpos($responseHeaders[0], '200 OK') === false) {
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

    /**
     * Get name of the jar file
     */
    protected function getFileName(): string
    {
        return 'selenium-server-standalone-' . $this->getVersion() . '.jar';
    }
}
