<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

/**
 * Resolve released Selenium server versions
 */
class VersionResolver
{
    /** @var string[] Versions with malformed names, duplicates etc. */
    private const IGNORED_VERSIONS = [
        '3.0-beta2/selenium-server-standalone-3.0.0-beta3.jar',
        '4.0/selenium-server-4.0.0-alpha-1.jar',
        '4.0/selenium-server-4.0.0-alpha-2.jar',
        '4.0/selenium-server-standalone-4.0.0-alpha-1.jar',
        '4.0/selenium-server-standalone-4.0.0-alpha-2.jar',
        '4.0-alpha/selenium-server-4.0.0-alpha-3.jar',
        '4.0-alpha4/selenium-server-4.0.0-alpha-4.jar',
        '4.0-alpha5/selenium-server-4.0.0-alpha-5.jar',
    ];

    /**
     * @return Version[]
     */
    public function getAvailableVersions(): array
    {
        $data = @file_get_contents(Downloader::SELENIUM_STORAGE_URL);
        if (!$data) {
            return [];
        }

        libxml_use_internal_errors(true); // disable errors from being thrown
        $xml = simplexml_load_string($data);
        if (!$xml) {
            return [];
        }

        $releases = $xml->xpath('//*[text()[contains(.,"selenium-server") and contains(.,".jar")]]');
        $availableVersions = [];
        foreach ($releases as $release) {
            $release = (string) $release;

            if (in_array($release, self::IGNORED_VERSIONS, true)) { // skip ignored version
                continue;
            }

            $parsedVersion = preg_replace('/.*(standalone|server)-(.+\..+\..+)\.jar/', '$2', $release);
            if ($release === $parsedVersion) { // regexp did not match
                continue;
            }

            $availableVersions[] = Version::createFromString($parsedVersion);
        }

        $this->sortVersions($availableVersions);

        return $availableVersions;
    }

    /**
     * Get latest released version of Selenium server. If not found, null is returned.
     */
    public function getLatestVersion(): ?Version
    {
        $availableVersions = $this->getAvailableVersions();

        if (empty($availableVersions)) {
            return null;
        }

        return end($availableVersions);
    }

    private function sortVersions(array &$versions): void
    {
        // Sort naturally, but versions like 3.0.0 must be after 3.0.0-beta, so we must take care of them explicitly
        usort($versions, static function (Version $a, Version $b): int {
            $aParts = explode('-', $a->toString());
            $bParts = explode('-', $b->toString());

            // First part is the same (3.0.0), but one string does have second part (-beta) while the other one does not
            if ($aParts[0] === $bParts[0] && (count($aParts) !== count($bParts))) {
                // The one with less parts should be ordered after the longer one
                return count($bParts) <=> count($aParts);
            }

            return strnatcmp(mb_strtolower($a->toString()), mb_strtolower($b->toString()));
        });
    }
}
