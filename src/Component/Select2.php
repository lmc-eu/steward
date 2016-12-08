<?php

namespace Lmc\Steward\Component;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Manipulate the Select2 UI component
 * @see https://select2.github.io/
 */
class Select2 extends AbstractComponent
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
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector($select2selector . ' ' . ($multiSelect ? 'input' : 'a'))
            )
        );

        // Click on element to open dropdown - to copy users behavior
        $select2link->click();

        $this->log('Sending keys to select2: %s', $value);

        // Insert searched term into s2 generated input
        $this->tc->wd
            ->findElement(WebDriverBy::cssSelector($multiSelect ? $select2selector . ' input' : '#select2-drop input'))
            ->sendKeys($value);

        // Wait until result are rendered (or maybe loaded with ajax)
        $firstResult = $this->tc->wd->wait()->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.select2-drop .select2-result.select2-highlighted')
            )
        );

        $this->log('Dropdown detected, selecting the first result: %s', $firstResult->getText());

        // Select first item in results
        $firstResult->click();
    }
}
