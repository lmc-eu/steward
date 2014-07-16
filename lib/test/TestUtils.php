<?php

namespace Lmc\Steward\Test;

/**
 * Commons test utils and syntax sugar for tests
 *
 * @copyright LMC s.r.o.
 */
class TestUtils
{
    /**
     * @var AbstactTestCase
     */
    protected $test;

    /**
     * Create utils instance
     * @param \Lmc\Steward\Test\AbstractTestCase $test
     */
    public function __construct(AbstractTestCase $test)
    {
        $this->test = $test;
    }

    /**
     * Set value of Select2 element
     * @param string $originalId ID of original select/input element
     * @param string $value Value to be selected
     * @param string $multiSelect OPTIONAL Is the select multiselect?
     * @todo Support multiple values for multiselects
     */
    public function setSelect2Value($originalId, $value, $multiSelect = false)
    {
        $select2selector = '#s2id_' . $originalId;

        // Wait for select2 to appear
        $select2link = $this->test->wd->wait()->until(
            \WebDriverExpectedCondition::presenceOfElementLocated(
                \WebDriverBy::cssSelector($select2selector . ' ' . ($multiSelect ? 'input' : 'a'))
            )
        );

        // Click on element to open dropdown - to copy users behavior
        $select2link->click();

        $this->test->log('Sending keys to select2: %s', $value);

        // Insert searched term into s2 generated input
        $this->test->wd
            ->findElement(\WebDriverBy::cssSelector($multiSelect ? $select2selector . ' input' : '#select2-drop input'))
            ->sendKeys($value);

        // Wait until result are rendered (or maybe loaded with ajax)
        $firstResult = $this->test->wd->wait()->until(
            \WebDriverExpectedCondition::presenceOfElementLocated(
                \WebDriverBy::cssSelector('.select2-drop .select2-result.select2-highlighted')
            )
        );

        $this->test->log('Dropdown detected, selecting the first result: %s', $firstResult->getText());

        // Select first item in results
        $firstResult->click();
    }

    /**
     * Sleep for given amout of seconds.
     * Unlike sleep(), also the float values are supported.
     * ALWAYS TRY TO USE WAIT() INSTEAD!
     * @param float $seconds
     */
    public function sleep($seconds)
    {
        $fullSecond = floor($seconds);
        $microseconds = fmod($seconds, 1) * 1000000000;

        time_nanosleep($fullSecond, $microseconds);
    }
}
