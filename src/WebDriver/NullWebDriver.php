<?php declare(strict_types=1);

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\JavaScriptExecutor;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverHasInputDevices;
use Facebook\WebDriver\WebDriverKeyboard;
use Facebook\WebDriver\WebDriverMouse;
use Facebook\WebDriver\WebDriverNavigation;
use Facebook\WebDriver\WebDriverOptions;
use Facebook\WebDriver\WebDriverTargetLocator;
use Facebook\WebDriver\WebDriverWait;

/**
 * Null webdriver is used as replacement of RemoteWebDriver in situations when we don't need to create browser instance.
 * It keeps API of RemoteWebDriver, however, it is not possible to interact with this NullWebDriver in any way.
 *
 * @codeCoverageIgnore
 */
class NullWebDriver implements WebDriver, JavaScriptExecutor, WebDriverHasInputDevices
{
    protected function throwException(): void
    {
        throw new \LogicException('You cannot interact with NullWebDriver.');
    }

    public function close(): WebDriver
    {
        $this->throwException();
    }

    public function get($url): WebDriver
    {
        $this->throwException();
    }

    public function getCurrentURL(): string
    {
        $this->throwException();
    }

    public function getPageSource(): string
    {
        $this->throwException();
    }

    public function getTitle(): string
    {
        $this->throwException();
    }

    public function getWindowHandle(): string
    {
        $this->throwException();
    }

    public function getWindowHandles(): array
    {
        $this->throwException();
    }

    public function quit(): void
    {
        $this->throwException();
    }

    public function takeScreenshot($save_as = null): string
    {
        $this->throwException();
    }

    public function wait($timeout_in_second = 30, $interval_in_millisecond = 250): WebDriverWait
    {
        $this->throwException();
    }

    public function manage(): WebDriverOptions
    {
        $this->throwException();
    }

    public function navigate(): WebDriverNavigation
    {
        $this->throwException();
    }

    public function switchTo(): WebDriverTargetLocator
    {
        $this->throwException();
    }

    /**
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public function execute($name, $params)
    {
        $this->throwException();
    }

    public function findElement(WebDriverBy $locator): WebDriverElement
    {
        $this->throwException();
    }

    public function findElements(WebDriverBy $locator): array
    {
        $this->throwException();
    }

    /**
     * @param string $script
     * @return mixed
     */
    public function executeScript($script, array $arguments = [])
    {
        $this->throwException();
    }

    /**
     * @param string $script
     * @return mixed
     */
    public function executeAsyncScript($script, array $arguments = [])
    {
        $this->throwException();
    }

    public function getKeyboard(): WebDriverKeyboard
    {
        $this->throwException();
    }

    public function getMouse(): WebDriverMouse
    {
        $this->throwException();
    }
}
