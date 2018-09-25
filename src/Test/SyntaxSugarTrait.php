<?php declare(strict_types=1);

namespace Lmc\Steward\Test;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\WebDriver\RemoteWebDriver;

/**
 * Adds WebDriver syntax sugar methods.
 * Needs to be used in context where $wd property holding instance of RemoteWebDriver is present (like
 * AbstractTestCase or AbstractComponent).
 *
 * @property RemoteWebDriver $wd
 */
trait SyntaxSugarTrait
{
    /**
     * Locates element whose class name contains the search value; compound class names are not permitted.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByClass(string $className): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::className($className));
    }

    /**
     * Locates all elements whose class name contains the search value; compound class names are not permitted.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByClass(string $className): array
    {
        return $this->wd->findElements(WebDriverBy::className($className));
    }

    /**
     * Wait for element whose class name contains the search value; compound class names are not permitted.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForClass(string $className, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::className($className), $mustBeVisible);
    }

    /**
     * Locates element matching a CSS selector.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByCss(string $cssSelector): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::cssSelector($cssSelector));
    }

    /**
     * Locates all elements matching a CSS selector.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByCss(string $cssSelector): array
    {
        return $this->wd->findElements(WebDriverBy::cssSelector($cssSelector));
    }

    /**
     * Wait for element matching a CSS selector.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForCss(string $cssSelector, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::cssSelector($cssSelector), $mustBeVisible);
    }

    /**
     * Locates element whose ID attribute matches the search value.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findById(string $id): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::id($id));
    }

    /**
     * Locates all elements whose ID attribute matches the search value.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleById(string $id): array
    {
        return $this->wd->findElements(WebDriverBy::id($id));
    }

    /**
     * Wait for element whose ID attribute matches the search value.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForId(string $id, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::id($id), $mustBeVisible);
    }

    /**
     * Locates element whose NAME attribute matches the search value.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByName(string $name): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::name($name));
    }

    /**
     * Locates all elements whose NAME attribute matches the search value.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByName(string $name): array
    {
        return $this->wd->findElements(WebDriverBy::name($name));
    }

    /**
     * Wait for element whose NAME attribute matches the search value.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForName(string $name, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::name($name), $mustBeVisible);
    }

    /**
     * Locates anchor element whose visible text matches the search value.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByLinkText(string $linkText): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::linkText($linkText));
    }

    /**
     * Locates all anchor elements whose visible text matches the search value.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByLinkText(string $linkText): array
    {
        return $this->wd->findElements(WebDriverBy::linkText($linkText));
    }

    /**
     * Wait for anchor element whose visible text matches the search value.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForLinkText(string $linkText, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::linkText($linkText), $mustBeVisible);
    }

    /**
     * Locates anchor element whose visible text partially matches the search value.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByPartialLinkText(string $partialLinkText): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::partialLinkText($partialLinkText));
    }

    /**
     * Locates all anchor elements whose visible text partially matches the search value.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByPartialLinkText(string $partialLinkText): array
    {
        return $this->wd->findElements(WebDriverBy::partialLinkText($partialLinkText));
    }

    /**
     * Wait for anchor element whose visible text partially matches the search value.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForPartialLinkText(string $partialLinkText, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::partialLinkText($partialLinkText), $mustBeVisible);
    }

    /**
     * Locates element whose tag name matches the search value.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByTag(string $tagName): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::tagName($tagName));
    }

    /**
     * Locates all elements whose tag name matches the search value.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByTag(string $tagName): array
    {
        return $this->wd->findElements(WebDriverBy::tagName($tagName));
    }

    /**
     * Wait for element whose tag name matches the search value.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForTag(string $tagName, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::tagName($tagName), $mustBeVisible);
    }

    /**
     * Locates element matching an XPath expression.
     *
     * @throws NoSuchElementException
     * @return RemoteWebElement The first element located using the mechanism. Exception is thrown if no element found.
     */
    public function findByXpath(string $xpath): RemoteWebElement
    {
        return $this->wd->findElement(WebDriverBy::xpath($xpath));
    }

    /**
     * Locates all elements matching an XPath expression.
     *
     * @return RemoteWebElement[] A list of all elements, or an empty array if nothing matches
     */
    public function findMultipleByXpath(string $xpath): array
    {
        return $this->wd->findElements(WebDriverBy::xpath($xpath));
    }

    /**
     * Wait for element that matches an XPath expression.
     *
     * @param bool $mustBeVisible Pass true to check if element is also visible. False only checks presence in DOM.
     */
    public function waitForXpath(string $xpath, bool $mustBeVisible = false): RemoteWebElement
    {
        return $this->waitForElement(WebDriverBy::xpath($xpath), $mustBeVisible);
    }

    /**
     * Wait until page title exactly matches given string
     *
     * @param string $title The expected title, which must be an exact match.
     */
    public function waitForTitle(string $title): void
    {
        $this->wd->wait()->until(
            WebDriverExpectedCondition::titleIs($title)
        );
    }

    /**
     * Wait until page title partially matches given string
     *
     * @param string $title The expected title substring
     */
    public function waitForPartialTitle(string $title): void
    {
        $this->wd->wait()->until(
            WebDriverExpectedCondition::titleContains($title)
        );
    }

    /**
     * Wait until page title partially matches given regular expression
     *
     * @param string $titleRegepx The expected title regular expression
     */
    public function waitForTitleRegexp(string $titleRegepx): void
    {
        $this->wd->wait()->until(
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
    private function waitForElement(WebDriverBy $by, bool $mustBeVisible = false): RemoteWebElement
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
