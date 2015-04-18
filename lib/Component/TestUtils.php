<?php

namespace Lmc\Steward\Component;

use Lmc\Steward\Test\ConfigProvider;

/**
 * Common test utils and syntax sugar for tests.
 * Should be accessible in all tests.
 */
class TestUtils extends AbstractComponent
{
    /**
     * Set value of Select2 element
     * @param string $originalId ID of original select/input element
     * @param string $value Value to be selected
     * @param bool $multiSelect OPTIONAL Is the select multiselect?
     * @todo Support multiple values for multiselects
     */
    public function setSelect2Value($originalId, $value, $multiSelect = false)
    {
        $select2selector = '#s2id_' . $originalId;

        // Wait for select2 to appear
        $select2link = $this->tc->wd->wait()->until(
            \WebDriverExpectedCondition::presenceOfElementLocated(
                \WebDriverBy::cssSelector($select2selector . ' ' . ($multiSelect ? 'input' : 'a'))
            )
        );

        // Click on element to open dropdown - to copy users behavior
        $select2link->click();

        $this->log('Sending keys to select2: %s', $value);

        // Insert searched term into s2 generated input
        $this->tc->wd
            ->findElement(\WebDriverBy::cssSelector($multiSelect ? $select2selector . ' input' : '#select2-drop input'))
            ->sendKeys($value);

        // Wait until result are rendered (or maybe loaded with ajax)
        $firstResult = $this->tc->wd->wait()->until(
            \WebDriverExpectedCondition::presenceOfElementLocated(
                \WebDriverBy::cssSelector('.select2-drop .select2-result.select2-highlighted')
            )
        );

        $this->log('Dropdown detected, selecting the first result: %s', $firstResult->getText());

        // Select first item in results
        $firstResult->click();
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

        if (strpos($fixturesDir, '\\') !== false) { // if \ was used in the path, we are most probably on windows
            $directorySeparator = '\\';
            $fixture = str_replace('/', $directorySeparator, $fixture);
        }

        $fixturePath = rtrim($fixturesDir, $directorySeparator) . $directorySeparator .  $fixture;

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
        $fullSecond = floor($seconds);
        $microseconds = fmod($seconds, 1) * 1000000000;

        time_nanosleep($fullSecond, $microseconds);
    }
}
