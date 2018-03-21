<?php declare(strict_types=1);

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\JavaScriptExecutor;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverHasInputDevices;

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

    public function close(): void
    {
        $this->throwException();
    }

    public function get($url): void
    {
        $this->throwException();
    }

    public function getCurrentURL(): void
    {
        $this->throwException();
    }

    public function getPageSource(): void
    {
        $this->throwException();
    }

    public function getTitle(): void
    {
        $this->throwException();
    }

    public function getWindowHandle(): void
    {
        $this->throwException();
    }

    public function getWindowHandles(): void
    {
        $this->throwException();
    }

    public function quit(): void
    {
        $this->throwException();
    }

    public function takeScreenshot($save_as = null): void
    {
        $this->throwException();
    }

    public function wait($timeout_in_second = 30, $interval_in_millisecond = 250): void
    {
        $this->throwException();
    }

    public function manage(): void
    {
        $this->throwException();
    }

    public function navigate(): void
    {
        $this->throwException();
    }

    public function switchTo(): void
    {
        $this->throwException();
    }

    public function execute($name, $params): void
    {
        $this->throwException();
    }

    public function findElement(WebDriverBy $locator): void
    {
        $this->throwException();
    }

    public function findElements(WebDriverBy $locator): void
    {
        $this->throwException();
    }

    public function executeScript($script, array $arguments = []): void
    {
        $this->throwException();
    }

    public function executeAsyncScript($script, array $arguments = []): void
    {
        $this->throwException();
    }

    public function getKeyboard(): void
    {
        $this->throwException();
    }

    public function getMouse(): void
    {
        $this->throwException();
    }
}
