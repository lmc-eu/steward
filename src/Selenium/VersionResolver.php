<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Lmc\Steward\Utils\FileGetContentsWrapper;

/**
 * Resolve released Selenium server versions
 */
class VersionResolver
{
    private const RELEASES_API_URL = 'https://api.github.com/repos/SeleniumHQ/selenium/releases';
    /** @var FileGetContentsWrapper */
    private $fileGetContentsWrapper;

    public function __construct()
    {
        $this->fileGetContentsWrapper = new FileGetContentsWrapper();
    }

    /**
     * @internal
     */
    public function setFileGetContentsWrapper(FileGetContentsWrapper $fileGetContentsWrapper): void
    {
        $this->fileGetContentsWrapper = $fileGetContentsWrapper;
    }

    /**
     * Get latest released stable version of Selenium server. If not found, null is returned.
     */
    public function getLatestVersion(): ?Version
    {
        $responseData = $this->fileGetContentsWrapper->fileGetContents(self::RELEASES_API_URL . '/latest');

        try {
            $decodedData = json_decode($responseData, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        if (!isset($decodedData->tag_name)) {
            return null;
        }

        $version = preg_replace('/^selenium-(.+)$/', '$1', $decodedData->tag_name);

        return Version::createFromString($version);
    }
}
