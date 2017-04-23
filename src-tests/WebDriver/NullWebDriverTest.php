<?php

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\TestCase;

class NullWebDriverTest extends TestCase
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
     * @dataProvider provideMethodName
     */
    public function testShouldThrowExceptionWhenInteractingWithInstance($methodName, $params)
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You cannot interact with NullWebDriver.');

        call_user_func_array([$this->webdriver, $methodName], $params);
    }

    /**
     * Return method names of WebDriver interface
     *
     * @return array
     */
    public function provideMethodName()
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
