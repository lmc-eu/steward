<?php declare(strict_types=1);

namespace Lmc\Steward\Test;

use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SyntaxSugarTraitTest extends TestCase
{
    /** @var SyntaxSugarTrait|\stdClass */
    protected $trait;

    protected function setUp(): void
    {
        $this->trait = $this->getObjectForTrait('Lmc\Steward\Test\SyntaxSugarTrait');

        $this->trait->wd = $this->createMock(RemoteWebDriver::class);
    }

    /**
     * @dataProvider provideFindElementStrategy
     * @param string $methodPostfix Shortcut method postfix to call (prefix 'find' is omitted)
     * @param string $expectedWebDriverByStrategy Method of WebDriverBy that is expected to be called
     */
    public function testFindByMethodsShouldCallFindElement(
        string $methodPostfix,
        string $expectedWebDriverByStrategy
    ): void {
        $wd = $this->trait->wd;
        /** @var MockObject $wd */
        $wd->expects($this->once())
            ->method('findElement')
            ->with(WebDriverBy::$expectedWebDriverByStrategy('foobar'))
            ->willReturn($this->createMock(RemoteWebElement::class));

        $method = 'find' . $methodPostfix;

        $this->trait->$method('foobar');
    }

    /**
     * @dataProvider provideFindElementStrategy
     * @param string $methodPostfix Shortcut method postfix to call (prefix 'findMultiple' is omitted)
     * @param string $expectedWebDriverByStrategy Method of WebDriverBy that is expected to be called
     */
    public function testFindMultipleByMethodsShouldCallFindElements(
        string $methodPostfix,
        string $expectedWebDriverByStrategy
    ): void {
        $wd = $this->trait->wd;
        /** @var MockObject $wd */
        $wd->expects($this->once())
            ->method('findElements')
            ->with(WebDriverBy::$expectedWebDriverByStrategy('foobar'))
            ->willReturn([]);

        $method = 'findMultiple' . $methodPostfix;

        $this->trait->$method('foobar');
    }

    /**
     * @return array[]
     */
    public function provideFindElementStrategy(): array
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
     *
     * @dataProvider provideWaitForMethod
     * @param bool $isElementMethod Is this method working with elements?
     */
    public function testWaitForMethodsShouldWaitUntilWebDriverExpectedCondition(
        string $method,
        bool $isElementMethod = true
    ): void {
        /** @var WebDriverWait|MockObject $waitMock */
        $waitMock = $this->createMock(WebDriverWait::class);

        // Note the WebDriverExpectedCondition instances are not comparable (as they return callbacks), so we can
        // only check for instance type.
        $waitMock->expects($this->exactly($isElementMethod ? 2 : 1))
            ->method('until')
            ->with($this->isInstanceOf(WebDriverExpectedCondition::class))
            ->willReturn($this->createMock(RemoteWebElement::class));

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
     * @return array[]
     */
    public function provideWaitForMethod(): array
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
