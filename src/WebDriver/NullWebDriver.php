<?php

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
    protected function throwException()
    {
        throw new \Exception('You cannot interact with NullWebDriver.');
    }

    public function close()
    {
        $this->throwException();
    }

    public function get($url)
    {
        $this->throwException();
    }

    public function getCurrentURL()
    {
        $this->throwException();
    }

    public function getPageSource()
    {
        $this->throwException();
    }

    public function getTitle()
    {
        $this->throwException();
    }

    public function getWindowHandle()
    {
        $this->throwException();
    }

    public function getWindowHandles()
    {
        $this->throwException();
    }

    public function quit()
    {
        $this->throwException();
    }

    public function takeScreenshot($save_as = null)
    {
        $this->throwException();
    }

    public function wait($timeout_in_second = 30, $interval_in_millisecond = 250)
    {
        $this->throwException();
    }

    public function manage()
    {
        $this->throwException();
    }

    public function navigate()
    {
        $this->throwException();
    }

    public function switchTo()
    {
        $this->throwException();
    }

    public function execute($name, $params)
    {
        $this->throwException();
    }

    public function findElement(WebDriverBy $locator)
    {
        $this->throwException();
    }

    public function findElements(WebDriverBy $locator)
    {
        $this->throwException();
    }

    public function executeScript($script, array $arguments = [])
    {
        $this->throwException();
    }

    public function executeAsyncScript($script, array $arguments = [])
    {
        $this->throwException();
    }

    public function getKeyboard()
    {
        $this->throwException();
    }

    public function getMouse()
    {
        $this->throwException();
    }
}
