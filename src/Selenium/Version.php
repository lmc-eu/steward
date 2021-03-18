<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Assert\Assert;

/**
 * Type object for selenium server version
 */
class Version
{
    /** @var string */
    private $major = '';
    /** @var string */
    private $minor = '';
    /** @var string */
    private $bugfix = '';
    /** @var string */
    private $dev = '';

    private function __construct(string $version)
    {
        $this->parseVersion($version);
    }

    public static function createFromString(string $version): self
    {
        return new self($version);
    }

    public function toString(): string
    {
        $version = sprintf('%s.%s.%s', $this->getMajor(), $this->getMinor(), $this->getBugfix());

        if ($this->getDev() !== '') {
            $version .= '-' . $this->getDev();
        }

        return $version;
    }

    public function getMajor(): string
    {
        return $this->major;
    }

    public function getMinor(): string
    {
        return $this->minor;
    }

    public function getBugfix(): string
    {
        return $this->bugfix;
    }

    public function getDev(): string
    {
        return $this->dev;
    }

    private function parseVersion(string $version): void
    {
        Assert::that($version, 'Invalid version (expected format is X.Y.Z)')
            ->notEmpty()
            ->regex('/^\d+\.\d+\.[\da-z\-]+$/i');

        $versionParts = explode('.', $version);

        $this->major = $versionParts[0];
        $this->minor = $versionParts[1];

        if (preg_match('/(\d+)-([[:alnum:]-]+)/', $versionParts[2], $devVersionMatches)) {
            $this->bugfix = $devVersionMatches[1];
            $this->dev = $devVersionMatches[2];
        } else {
            $this->bugfix = $versionParts[2];
        }
    }
}
