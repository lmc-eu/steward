<?php

namespace Lmc\Steward\Component;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\ConfigProvider;

/**
 * Common test utils and syntax sugar for tests.
 */
class TestUtils extends AbstractComponent
{
    /**
     * Set value of Select2 element
     * @param string $originalId ID of original select/input element
     * @param string $value Value to be selected
     * @param bool $multiSelect OPTIONAL Is the select multiselect?
     * @todo Support multiple values for multiselects
     * @deprecated Use Select2 component
     */
    public function setSelect2Value($originalId, $value, $multiSelect = false)
    {
        (new Select2($this->tc))->setSelect2Value($originalId, $value, $multiSelect);
    }

    /**
     * Get path to given fixture, which could be then entered into file input field.
     *
     * @param string $fixture Fixture identifier (relative path to fixture from directory with tests)
     * @return string Path to fixture
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
     */
    public static function sleep($seconds)
    {
        $fullSecond = (int) floor($seconds);
        $microseconds = fmod($seconds, 1) * 1000000000;

        time_nanosleep($fullSecond, $microseconds);
    }
}
