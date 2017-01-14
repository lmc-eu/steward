<?php

namespace Lmc\Steward\Test;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\WebDriver\RemoteWebDriver;

/**
 * Adds WebDriver syntax sugar methods.
 * Needs to be used in context where $wd property holding instance of \RemoteWebDriver is present (like
 * AbstractTestCaseBase or AbstractComponent).
 * @property RemoteWebDriver $wd
 */
trait SyntaxSugarTrait
{
    /**
     * Locates element whose class name contains the search value; compound class names are not permitted.
     *
     * @param string $className
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByClass($className)
    {
        return $this->wd->findElement(WebDriverBy::className($className));
    }

    /**
     * Locates all elements whose class name contains the search value; compound class names are not permitted.
     *
     * @param string $className
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByClass($className)
    {
        return $this->wd->findElements(WebDriverBy::className($className));
    }

    /**
     * Wait for element whose class name contains the search value; compound class names are not permitted.
     *
     * @param string $className
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForClass($className, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::className($className), $mustBeVisible);
    }

    /**
     * Locates element matching a CSS selector.
     *
     * @param string $cssSelector
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByCss($cssSelector)
    {
        return $this->wd->findElement(WebDriverBy::cssSelector($cssSelector));
    }

    /**
     * Locates all elements matching a CSS selector.
     *
     * @param string $cssSelector
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByCss($cssSelector)
    {
        return $this->wd->findElements(WebDriverBy::cssSelector($cssSelector));
    }

    /**
     * Wait for element matching a CSS selector.
     *
     * @param string $cssSelector
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForCss($cssSelector, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::cssSelector($cssSelector), $mustBeVisible);
    }

    /**
     * Locates element whose ID attribute matches the search value.
     *
     * @param string $id
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findById($id)
    {
        return $this->wd->findElement(WebDriverBy::id($id));
    }

    /**
     * Locates all elements whose ID attribute matches the search value.
     *
     * @param string $id
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleById($id)
    {
        return $this->wd->findElements(WebDriverBy::id($id));
    }

    /**
     * Wait for element whose ID attribute matches the search value.
     *
     * @param string $id
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForId($id, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::id($id), $mustBeVisible);
    }

    /**
     * Locates element whose NAME attribute matches the search value.
     *
     * @param string $name
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByName($name)
    {
        return $this->wd->findElement(WebDriverBy::name($name));
    }

    /**
     * Locates all elements whose NAME attribute matches the search value.
     *
     * @param string $name
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByName($name)
    {
        return $this->wd->findElements(WebDriverBy::name($name));
    }

    /**
     * Wait for element whose NAME attribute matches the search value.
     *
     * @param string $name
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForName($name, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::name($name), $mustBeVisible);
    }

    /**
     * Locates anchor element whose visible text matches the search value.
     *
     * @param string $linkText
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByLinkText($linkText)
    {
        return $this->wd->findElement(WebDriverBy::linkText($linkText));
    }

    /**
     * Locates all anchor elements whose visible text matches the search value.
     *
     * @param string $linkText
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByLinkText($linkText)
    {
        return $this->wd->findElements(WebDriverBy::linkText($linkText));
    }

    /**
     * Wait for anchor element whose visible text matches the search value.
     *
     * @param string $linkText
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForLinkText($linkText, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::linkText($linkText), $mustBeVisible);
    }

    /**
     * Locates anchor element whose visible text partially matches the search value.
     *
     * @param string $partialLinkText
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByPartialLinkText($partialLinkText)
    {
        return $this->wd->findElement(WebDriverBy::partialLinkText($partialLinkText));
    }

    /**
     * Locates all anchor elements whose visible text partially matches the search value.
     *
     * @param string $partialLinkText
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByPartialLinkText($partialLinkText)
    {
        return $this->wd->findElements(WebDriverBy::partialLinkText($partialLinkText));
    }

    /**
     * Wait for anchor element whose visible text partially matches the search value.
     *
     * @param string $partialLinkText
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForPartialLinkText($partialLinkText, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::partialLinkText($partialLinkText), $mustBeVisible);
    }

    /**
     * Locates element whose tag name matches the search value.
     *
     * @param string $tagName
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByTag($tagName)
    {
        return $this->wd->findElement(WebDriverBy::tagName($tagName));
    }

    /**
     * Locates all elements whose tag name matches the search value.
     *
     * @param string $tagName
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByTag($tagName)
    {
        return $this->wd->findElements(WebDriverBy::tagName($tagName));
    }

    /**
     * Wait for element whose tag name matches the search value.
     *
     * @param string $tagName
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForTag($tagName, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::tagName($tagName), $mustBeVisible);
    }

    /**
     * Locates element matching an XPath expression.
     *
     * @param string $xpath
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByXpath($xpath)
    {
        return $this->wd->findElement(WebDriverBy::xpath($xpath));
    }

    /**
     * Locates all elements matching an XPath expression.
     *
     * @param string $xpath
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByXpath($xpath)
    {
        return $this->wd->findElements(WebDriverBy::xpath($xpath));
    }

    /**
     * Wait for element that matches an XPath expression.
     *
     * @param string $xpath
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement
     */
    public function waitForXpath($xpath, $mustBeVisible = false)
    {
        return $this->waitForElement(WebDriverBy::xpath($xpath), $mustBeVisible);
    }

    /**
     * Wait until page title exactly matches given string
     *
     * @param string $title The expected title, which must be an exact match.
     * @return RemoteWebElement|array
     */
    public function waitForTitle($title)
    {
        return $this->wd->wait()->until(
            WebDriverExpectedCondition::titleIs($title)
        );
    }

    /**
     * Wait until page title partially matches given string
     *
     * @param string $title The expected title substring
     * @return RemoteWebElement|array
     */
    public function waitForPartialTitle($title)
    {
        return $this->wd->wait()->until(
            WebDriverExpectedCondition::titleContains($title)
        );
    }

    /**
     * Wait until page title partially matches given regular expression
     *
     * @param string $titleRegepx The expected title regular expression
     * @return RemoteWebElement|array
     */
    public function waitForTitleRegexp($titleRegepx)
    {
        return $this->wd->wait()->until(
            WebDriverExpectedCondition::titleMatches($titleRegepx)
        );
    }

    /**
     * Wait until an element is present on the DOM of a page (and is also visible if $mustBeVisible is set to true).
     *
     * @param WebDriverBy $by The locator used to find the element.
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     * @return RemoteWebElement The element which is located
     */
    private function waitForElement(WebDriverBy $by, $mustBeVisible = false)
    {
        if ($mustBeVisible) {
            $condition = 'visibilityOfElementLocated';
        } else {
            $condition = 'presenceOfElementLocated';
        }

        return $this->wd->wait()->until(
            WebDriverExpectedCondition::$condition($by)
        );
    }
}
