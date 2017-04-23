<?php

namespace Lmc\Steward\Test;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use PHPUnit\Framework\TestCase;

class SyntaxSugarTraitTest extends TestCase
{
    /** @var SyntaxSugarTrait|\stdClass */
    protected $trait;

    protected function setUp()
    {
        $this->trait = $this->getObjectForTrait('Lmc\Steward\Test\SyntaxSugarTrait');

        $this->trait->wd = $this->getMockBuilder(RemoteWebDriver::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @dataProvider provideFindElementStrategy
     * @param string $methodPostfix Shortcut method postfix to call (prefix 'find' is omitted)
     * @param string $expectedWebDriverByStrategy Method of WebDriverBy that is expected to be called
     */
    public function testFindByMethodsShouldCallFindElement($methodPostfix, $expectedWebDriverByStrategy)
    {
        $wd = $this->trait->wd;
        /** @var \PHPUnit_Framework_MockObject_MockObject $wd */
        $wd->expects($this->once())
            ->method('findElement')
            ->with(WebDriverBy::$expectedWebDriverByStrategy('foobar'));

        $method = 'find' . $methodPostfix;

        $this->trait->$method('foobar');
    }

    /**
     * @dataProvider provideFindElementStrategy
     * @param string $methodPostfix Shortcut method postfix to call (prefix 'findMultiple' is omitted)
     * @param string $expectedWebDriverByStrategy Method of WebDriverBy that is expected to be called
     */
    public function testFindMultipleByMethodsShouldCallFindElements($methodPostfix, $expectedWebDriverByStrategy)
    {
        $wd = $this->trait->wd;
        /** @var \PHPUnit_Framework_MockObject_MockObject $wd */
        $wd->expects($this->once())
            ->method('findElements')
            ->with(WebDriverBy::$expectedWebDriverByStrategy('foobar'));

        $method = 'findMultiple' . $methodPostfix;

        $this->trait->$method('foobar');
    }

    /**
     * @return array
     */
    public function provideFindElementStrategy()
    {
        return [
            // $methodPostfix, $expectedWebDriverByStrategy
            ['ByClass', 'className'],
            ['ByCss', 'cssSelector'],
            ['ById', 'id'],
            ['ByName', 'name'],
            ['ByLinkText', 'linkText'],
            ['ByPartialLinkText', 'partialLinkText'],
            ['ByTag', 'tagName'],
            ['ByXpath', 'xpath'],
        ];
    }

    /**
     * Test that waitFor...() methods waits for instance of WebDriverExpectedCondition. Note we only test the
     * WebDriverExpectedCondition instance type but not its content, because they return callback
     * which we cannot compare.
     * @dataProvider provideWaitForMethod
     * @param string $method
     * @param bool $isElementMethod Is this method working with elements?
     */
    public function testWaitForMethodsShouldWaitUntilWebDriverExpectedCondition($method, $isElementMethod = true)
    {
        /** @var WebDriverWait|\PHPUnit_Framework_MockObject_MockObject $waitMock */
        $waitMock = $this->getMockBuilder(WebDriverWait::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Note the WebDriverExpectedCondition instances are not comparable (as they return callbacks), so we can
        // only check for instance type.
        $waitMock->expects($this->exactly($isElementMethod ? 2 : 1))
            ->method('until')
            ->with($this->isInstanceOf(WebDriverExpectedCondition::class));

        $this->trait->wd->expects($this->exactly($isElementMethod ? 2 : 1))
            ->method('wait')
            ->willReturn($waitMock);

        $this->trait->$method('foo');

        // If the waitFor method works with elements, test also $mustBeVisible parameter
        if ($isElementMethod) {
            $this->trait->$method('foo', $mustBeVisible = true);
        }
    }

    /**
     * @return array
     */
    public function provideWaitForMethod()
    {
        return [
            // $method, $isElementMethod
            ['waitForClass'],
            ['waitForCss'],
            ['waitForId'],
            ['waitForName'],
            ['waitForLinkText'],
            ['waitForPartialLinkText'],
            ['waitForTag'],
            ['waitForXpath'],

            ['waitForTitle', false],
            ['waitForPartialTitle', false],
            ['waitForTitleRegexp', false],
        ];
    }
}
