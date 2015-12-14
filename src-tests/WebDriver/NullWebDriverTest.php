<?php

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\WebDriverBy;

class NullWebDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var NullWebDriver
     */
    protected $webdriver;

    public function setUp()
    {
        $this->webdriver = new NullWebDriver();
    }

    /**
     * @param $methodName
     * @param $params
     * @dataProvider methodNameProvider
     * @expectedException \Exception
     * @expectedExceptionMessage You cannot interact with NullWebDriver.
     */
    public function testShouldThrowExceptionWhenInteractingWithInstance($methodName, $params)
    {
        call_user_func_array([$this->webdriver, $methodName], $params);
    }

    /**
     * Return method names of WebDriver interface
     *
     * @return array
     */
    public function methodNameProvider()
    {
        return [
            ['close', []],
            ['execute', ['name', []]],
            ['findElement', [WebDriverBy::id('foo')]],
            ['findElements', [WebDriverBy::id('foo')]],
            ['get', ['http://lmc.eu']],
            ['getCurrentURL', []],
            ['getPageSource', []],
            ['getTitle', []],
            ['getWindowHandle', []],
            ['getWindowHandles', []],
            ['manage', []],
            ['navigate', []],
            ['quit', []],
            ['switchTo', []],
            ['takeScreenshot', []],
            ['wait', []],
        ];
    }
}
