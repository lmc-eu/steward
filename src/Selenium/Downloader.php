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
        $availableVersions = self::getAvailableVersions();

        if (!$availableVersions) {
            return null;
        }

        return end($availableVersions);
    }

    public static function getAvailableVersions(): array
    {
        $data = @file_get_contents(self::$storageUrl);
        if (!$data) {
            return [];
        }

        libxml_use_internal_errors(true); // disable errors from being thrown
        $xml = simplexml_load_string($data);
        if (!$xml) {
            return [];
        }

        $releases = $xml->xpath('//*[text()[contains(.,"selenium-server-standalone")]]');
        $availableVersions = [];
        foreach ($releases as $release) {
            $parsedVersion = preg_replace('/.*standalone-(.+)\.jar/', '$1', $release);
            if ((string) $release === $parsedVersion) { // regexp did not match
                continue;
            }

            $availableVersions[] = $parsedVersion;
        }

        self::sortVersions($availableVersions);

        return $availableVersions;
    }

    private static function sortVersions(array &$versions): void
    {
        // Sort naturally, but versions like 3.0.0 must be after 3.0.0-beta, so we must take care of them explicitly
        usort($versions, function (string $a, string $b): int {
            $aParts = explode('-', $a);
            $bParts = explode('-', $b);

            // First part is the same (3.0.0), but one string does have second part (-beta) while the other one does not
            if ($aParts[0] === $bParts[0] && (count($aParts) !== count($bParts))) {
                // The one with less parts should be ordered after the longer one
                return count($bParts) <=> count($aParts);
            }

            return strnatcmp(mb_strtolower($a), mb_strtolower($b));
        });
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

        $devVersionToAppend = '';
        if (!empty($devVersion) && $versionParts[0] === '3') { // Selenium 3 releases included dev version in their path
            $devVersionToAppend = '-' . $devVersion;
        }

        $fileUrl = self::$storageUrl . '/' . $versionParts[0] . '.' . $versionParts[1]
            . $devVersionToAppend
            . '/' . $this->getFileName();

        return $fileUrl;
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
