<?php

namespace Lmc\Steward\FunctionalTests;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverElement;
use Lmc\Steward\Component\Select2;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Test Select2 component through real Steward-based TestCase.
 *
 * @group integration
 * @group functional
 * @covers Lmc\Steward\Component\Select2
 */
class Select2Test extends AbstractTestCase
{
    const SELECT_SIMPLE_SELECTOR = '#select';
    const INPUT_SIMPLE_SELECTOR = '#input';
    const SELECT_MULTIPLE_SELECTOR = '#select-multiple';
    const INPUT_MULTIPLE_SELECTOR = '#input-multiple';

    /**
     * @before
     */
    public function init()
    {
        $this->wd->get('file:///' . __DIR__ . '/Web/select2v3.html');
    }

    /**
     * @dataProvider provideSelectSelector
     * @param string $selector
     * @param bool $shouldBeMultiple
     */
    public function testShouldCreateNewInstanceForSelect2AndDetectIfItIsMultiple($selector, $shouldBeMultiple)
    {
        $element = $this->findByCss($selector);
        $select2 = new Select2($this, $element);

        $this->assertSame($shouldBeMultiple, $select2->isMultiple());
        $options = $select2->getOptions();
        $this->assertCount(5, $options);
        $this->assertContainsOnlyInstancesOf(WebDriverElement::class, $options);

        $actualOptions = [];
        foreach ($options as $option) {
            $actualOptions[] = $option->getText();
        }

        $this->assertSame(
            [
                'First',
                'This is second option',
                'This is not second option',
                'Fourth with spaces inside',
                'Fifth surrounded by spaces',
            ],
            $actualOptions
        );
    }

    /**
     * @return array[]
     */
    public function provideSelectSelector()
    {
        return [
            'simple <select>' => [self::SELECT_SIMPLE_SELECTOR, false],
            'simple <input>' => [self::INPUT_SIMPLE_SELECTOR, false],
            '<select> with multiple attribute' => [self::SELECT_MULTIPLE_SELECTOR, true],
            '<input> with multiple options' => [self::INPUT_MULTIPLE_SELECTOR, true],
        ];
    }

    public function testShouldGetDefaultSelectedOptionOfSimpleSelect()
    {
        $element = $this->findByCss(self::SELECT_SIMPLE_SELECTOR);
        $select2 = new Select2($this, $element);

        $selectedOptions = $select2->getAllSelectedOptions();
        $firstSelectedOption = $select2->getFirstSelectedOption();

        $this->assertContainsOnlyInstancesOf(WebDriverElement::class, $selectedOptions);
        $this->assertCount(1, $selectedOptions);
        $this->assertSame('First', $selectedOptions[0]->getText());

        $this->assertInstanceOf(WebDriverElement::class, $firstSelectedOption);
        $this->assertSame('First', $firstSelectedOption->getText());
    }

    public function testShouldReturnEmptyArrayIfNoOptionsOfMultipleSelectSelected()
    {
        $element = $this->findByCss(self::SELECT_MULTIPLE_SELECTOR);
        $select2 = new Select2($this, $element);

        $selectedOptions = $select2->getAllSelectedOptions();

        $this->assertSame([], $selectedOptions);
    }

    public function testShouldThrowExceptionIfThereIsNoFirstSelectedOptionOfMultipleSelect()
    {
        $element = $this->findByCss(self::SELECT_MULTIPLE_SELECTOR);
        $select2 = new Select2($this, $element);

        $this->expectException(NoSuchElementException::class);
        $this->expectExceptionMessage('No options are selected');
        $select2->getFirstSelectedOption();
    }

    public function testShouldSelectOptionOfSimpleSelectByVisiblePartialText()
    {
        $element = $this->findByCss(self::SELECT_SIMPLE_SELECTOR);
        $select2 = new Select2($this, $element);

        $this->assertSame('First', $select2->getFirstSelectedOption()->getText());
        $select2->selectByVisiblePartialText('not second');
        $this->assertSame('This is not second option', $select2->getFirstSelectedOption()->getText());

        $select2->selectByVisiblePartialText('Fifth');
        $this->assertSame('Fifth surrounded by spaces', $select2->getFirstSelectedOption()->getText());
    }

    public function testShouldSelectOptionOfMultipleSelectByVisiblePartialText()
    {
        $element = $this->findByCss(self::SELECT_MULTIPLE_SELECTOR);
        $select2 = new Select2($this, $element);

        $this->assertSame([], $select2->getAllSelectedOptions());

        $select2->selectByVisiblePartialText('Firs');
        $this->assertSame('First', $select2->getFirstSelectedOption()->getText());
        $selectedOptions = $select2->getAllSelectedOptions();
        $this->assertContainsOnlyInstancesOf(WebDriverElement::class, $selectedOptions);
        $this->assertCount(1, $selectedOptions);
        $this->assertSame('First', $selectedOptions[0]->getText());

        $select2->selectByVisiblePartialText('second');
        $select2->selectByVisiblePartialText('Fifth');
        // the first selected option is still the same
        $this->assertSame('First', $select2->getFirstSelectedOption()->getText());

        $finalSelectedOptions = $select2->getAllSelectedOptions();
        $this->assertContainsOnlyInstancesOf(WebDriverElement::class, $selectedOptions);
        $this->assertCount(3, $finalSelectedOptions);
        $this->assertSame('First', $finalSelectedOptions[0]->getText());
        $this->assertSame('This is second option', $finalSelectedOptions[1]->getText());
        $this->assertSame('Fifth surrounded by spaces', $finalSelectedOptions[2]->getText());
    }
}
