<?php declare(strict_types=1);

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\TestCase;

class NullWebDriverTest extends TestCase
{
    /** @var NullWebDriver */
    protected $webdriver;

    protected function setUp(): void
    {
        $this->webdriver = new NullWebDriver();
    }

    /**
     * @dataProvider provideMethodName
     */
    public function testShouldThrowExceptionWhenInteractingWithInstance(string $methodName, array $params): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You cannot interact with NullWebDriver.');

        call_user_func_array([$this->webdriver, $methodName], $params);
    }

    /**
     * Return method names of WebDriver interface
     *
     * @return array[]
     */
    public function provideMethodName(): array
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
            ['executeScript', ['']],
            ['executeAsyncScript', ['']],
            ['getKeyboard', []],
            ['getMouse', []],
        ];
    }
}
