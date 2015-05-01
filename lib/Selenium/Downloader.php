<?php

namespace Lmc\Steward\Selenium;

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
    public function __construct($targetDir)
    {
        $this->targetDir = rtrim($targetDir, '/');
    }

    /**
     * Get latest released version of Selenium server. If not found, false is returned.
     * @return string|false
     */
    public static function getLatestVersion()
    {
        $data = file_get_contents(self::$storageUrl);
        if (!$data) {
            return false;
        }

        libxml_use_internal_errors(true); // disable errors from being thrown
        $xml = simplexml_load_string($data);
        if (!$xml) {
            return false;
        }

        $releases = $xml->xpath('//*[text()[contains(.,"selenium-server-standalone")]]');
        $lastRelease = end($releases); // something like "2.42/selenium-server-standalone-2.42.2.jar"

        $lastVersion = preg_replace('/.*standalone-([0-9\.]*)\.jar/', '$1', $lastRelease);
        if ($lastRelease == $lastVersion) { // regexp not matched
            return false;
        }

        return $lastVersion;
    }

    /**
     * @param string $version Version to download
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * Get version that should be downloaded; if not set, attempt to retrieve latest released version
     * @return false|string
     */
    public function getVersion()
    {
        if (!$this->version) {
            $this->version = self::getLatestVersion();
        }

        return $this->version;
    }

    /**
     * Check if file of given version was already downloaded to given directory
     * @return bool
     */
    public function isAlreadyDownloaded()
    {
        return file_exists($this->getFilePath());
    }

    /**
     * Get target path of the file
     * @return string
     */
    public function getFilePath()
    {
        return $this->targetDir . '/' . $this->getFileName($this->getVersion());
    }

    /**
     * Get complete URL of the file to download
     * @return string
     */
    public function getFileUrl()
    {
        $versionParts = explode('.', $this->getVersion());
        $fileUrl = self::$storageUrl . '/' . $versionParts[0] . '.' . $versionParts[1] . '/' . $this->getFileName();

        return $fileUrl;
    }

    /**
     * Execute the download
     * @return int Downloaded size in bytes or false on failure
     */
    public function download()
    {
        $targetPath = $this->getFilePath();

        if (!is_dir(dirname($targetPath))) {
            mkdir(dirname($targetPath), 0777, true);
        }

        $fileUrl = $this->getFileUrl();

        $fp = fopen($fileUrl, 'r');
        $downloadedSize = file_put_contents($targetPath, $fp);

        return $downloadedSize;
    }

    /**
     * Get name of the jar file
     * @return string
     */
    protected function getFileName()
    {
        return 'selenium-server-standalone-' . $this->getVersion() . '.jar';
    }
}
