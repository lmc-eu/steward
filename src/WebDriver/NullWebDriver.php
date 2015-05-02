<?php

namespace Lmc\Steward\WebDriver;

/**
 * Null webdriver is used as replacement of RemoteWebDriver in situations when we don't need to create browser instance.
 * It keeps API of \WebDriver, however, it is not possible to interact with this NullWebDriver.
 */
class NullWebDriver implements \WebDriver
{
    protected function throwException()
    {
        throw new \Exception('You cannot interact with NullWebDriver.');
    } // @codeCoverageIgnore

    public function close()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function get($url)
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function getCurrentURL()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function getPageSource()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function getTitle()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function getWindowHandle()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function getWindowHandles()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function quit()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function takeScreenshot($save_as = null)
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function wait($timeout_in_second = 30, $interval_in_millisecond = 250)
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function manage()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function navigate()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function switchTo()
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function execute($name, $params)
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function findElement(\WebDriverBy $locator)
    {
        $this->throwException();
    } // @codeCoverageIgnore

    public function findElements(\WebDriverBy $locator)
    {
        $this->throwException();
    } // @codeCoverageIgnore
}
