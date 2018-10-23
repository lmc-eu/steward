<?php declare(strict_types=1);

namespace Lmc\Steward\Component;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\UnsupportedOperationException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelectInterface;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Manipulate the Select2 UI jQuery based component.
 * Currently compatible with Select2 v3.5.
 *
 * @see https://select2.github.io/
 */
class Select2 extends AbstractComponent implements WebDriverSelectInterface
{
    protected const DROPDOWN_SELECTOR = '#select2-drop';
    protected const DROPDOWN_OPTIONS_SELECTOR = '.select2-results > li';
    protected const SIMPLESELECT_SELECTED_OPTION_SELECTOR = '.select2-chosen';
    protected const MULTISELECT_SELECTED_OPTIONS_SELECTOR = '.select2-choices > li.select2-search-choice';

    /** @var bool */
    protected $multiple;
    /** @var WebDriverElement */
    protected $element;
    /** @var string */
    protected $select2Selector;

    /**
     * @param WebDriverElement $element Original HTML element to which is the Select2 attached
     */
    public function __construct(AbstractTestCase $tc, WebDriverElement $element)
    {
        parent::__construct($tc);

        $this->element = $element;
        $this->select2Selector = '#s2id_' . $this->element->getAttribute('id');
        $select2Element = $this->waitForCss($this->select2Selector);
        $this->multiple = $this->detectIfSelect2IsMultiple($select2Element);
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getOptions(): array
    {
        $this->openDropdownOptions();

        $dropdown = $this->wd->findElement(WebDriverBy::cssSelector(self::DROPDOWN_SELECTOR));

        return $dropdown->findElements((WebDriverBy::cssSelector(self::DROPDOWN_OPTIONS_SELECTOR)));
    }

    /**
     * @return WebDriverElement[] All selected options belonging to this select tag.
     */
    public function getAllSelectedOptions(): array
    {
        if (!$this->isMultiple()) {
            return [$this->getFirstSelectedOption()];
        }

        return $this->findMultipleByCss($this->select2Selector . ' ' . self::MULTISELECT_SELECTED_OPTIONS_SELECTOR);
    }

    /**
     * @throws NoSuchElementException
     *
     * @return WebDriverElement The first selected option in multi-select (or the currently selected option
     * in a normal select)
     */
    public function getFirstSelectedOption(): WebDriverElement
    {
        if (!$this->isMultiple()) {
            return $this->findByCss($this->select2Selector . ' ' . self::SIMPLESELECT_SELECTED_OPTION_SELECTOR);
        }

        $selected = $this->findMultipleByCss(
            $this->select2Selector . ' ' . self::MULTISELECT_SELECTED_OPTIONS_SELECTOR
        );

        if (count($selected) === 0) {
            throw new NoSuchElementException('No options are selected');
        }

        return reset($selected);
    }

    /**
     * @codeCoverageIgnore
     * @param int $index
     * @throws UnsupportedOperationException
     */
    public function selectByIndex($index): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    /**
     * @codeCoverageIgnore
     * @param string $value
     * @throws UnsupportedOperationException
     */
    public function selectByValue($value): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    /**
     * @codeCoverageIgnore
     * @param string $text
     * @throws UnsupportedOperationException
     */
    public function selectByVisibleText($text): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    /**
     * Select first option which text partially matches the argument.
     *
     * @param string $text
     */
    public function selectByVisiblePartialText($text): void
    {
        $this->openDropdownOptions();

        $this->log('Sending keys to select2: %s', $text);

        $inputSelector = WebDriverBy::cssSelector(
            $this->isMultiple() ? $this->select2Selector . ' input' : '#select2-drop input'
        );

        // Insert searched term into Select2 generated input
        $this->tc->wd
            ->findElement($inputSelector)
            ->sendKeys($text);

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

    /**
     * @codeCoverageIgnore
     * @throws UnsupportedOperationException
     */
    public function deselectAll(): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    /**
     * @codeCoverageIgnore
     * @param int $index
     * @throws UnsupportedOperationException
     */
    public function deselectByIndex($index): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    /**
     * @codeCoverageIgnore
     * @param string $value
     * @throws UnsupportedOperationException
     */
    public function deselectByValue($value): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    /**
     * @codeCoverageIgnore
     * @param string $text
     * @throws UnsupportedOperationException
     */
    public function deselectByVisibleText($text): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    /**
     * @codeCoverageIgnore
     * @param string $text
     * @throws UnsupportedOperationException
     */
    public function deselectByVisiblePartialText($text): void
    {
        throw new UnsupportedOperationException('Method not is not implemented');
    }

    public function openDropdownOptions(): void
    {
        // Wait for select2 to appear
        $select2link = $this->tc->wd->wait()->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector($this->select2Selector . ' ' . ($this->isMultiple() ? 'input' : 'a'))
            )
        );

        // Click on element to open dropdown - to copy users behavior
        $select2link->click();
    }

    protected function detectIfSelect2IsMultiple(WebDriverElement $select2Element): bool
    {
        $select2Classes = $select2Element->getAttribute('class');

        return (mb_strpos($select2Classes, 'select2-container-multi') !== false);
    }
}
