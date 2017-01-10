<?php

namespace Lmc\Steward\Component;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Common test utils and syntax sugar for tests.
 * @deprecated
 */
class TestUtils extends AbstractComponent
{
    /**
     * Set value of Select2 element
     * @param string $originalId ID of original select/input element
     * @param string $value Value to be selected
     * @param bool $multiSelect UNUSED, kept for backwards compatibility
     * @todo Support multiple values for multiselects
     * @deprecated Use Select2 component
     */
    public function setSelect2Value($originalId, $value, $multiSelect = false)
    {
        $originalSelect = $this->waitForId($originalId);
        (new Select2($this->tc, $originalSelect))->selectByVisiblePartialText($value);
    }

    /**
     * Get path to given fixture, which could be then entered into file input field.
     *
     * @param string $fixture Fixture identifier (relative path to fixture from directory with tests)
     * @return string Path to fixture
     * @deprecated Use FileDetector instead
     */
    public function getFixturePath($fixture)
    {
        $fixturesDir = ConfigProvider::getInstance()->fixturesDir;
        $directorySeparator = '/';

        if (mb_strpos($fixturesDir, '\\') !== false) { // if \ was used in the path, we are most probably on windows
            $directorySeparator = '\\';
            $fixture = str_replace('/', $directorySeparator, $fixture);
        }

        $fixturePath = rtrim($fixturesDir, $directorySeparator) . $directorySeparator . $fixture;

        // if relative path was provided and the file is accessible, resolve into absolute path
        if (realpath($fixturePath)) {
            $fixturePath = realpath($fixturePath);
        }

        $this->debug('Assembled path to fixture: "%s"', $fixturePath);

        return $fixturePath;
    }

    /**
     * Sleep for given amount of seconds.
     * Unlike sleep(), also the float values are supported.
     * ALWAYS TRY TO USE WAIT() INSTEAD!
     * @param float $seconds
     * @deprecated Use directly AbstractTestCase::sleep() method
     */
    public static function sleep($seconds)
    {
        AbstractTestCase::sleep($seconds);
    }
}
