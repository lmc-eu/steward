<?php declare(strict_types=1);

namespace Lmc\Steward\Listener;

use Facebook\WebDriver\Exception\TimeoutException;
use Lmc\Steward\ConfigHelper;
use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\WarningTestCase;

/**
 * @covers \Lmc\Steward\Listener\SnapshotListener
 */
class SnapshotListenerTest extends TestCase
{
    use PHPMock;

    protected function setUp(): void
    {
        ConfigHelper::setEnvironmentVariables(ConfigHelper::getDummyConfig());
        ConfigHelper::unsetConfigInstance();
    }

    /**
     * @dataProvider provideBasicTestEvent
     * @dataProvider provideTestWithDataSet
     */
    public function testShouldTakeSnapshot(
        string $method,
        \Throwable $exception,
        string $testcaseName,
        string $testName,
        string $dataSetName,
        array $dataSet,
        string $expectedFileNameBase
    ): void {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(
            AbstractTestCase::class,
            [$testName, $dataSet, $dataSetName],
            $testcaseName
        );

        /** @var RemoteWebDriver|MockObject $webDriver */
        $webDriver = $this->createMock(RemoteWebDriver::class);
        $webDriver->expects($this->once())
            ->method('getCurrentURL')
            ->willReturn('http://foo.bar');

        $webDriver->expects($this->once())
            ->method('takeScreenshot')
            ->with(
                $this->matches('%s%esrc-tests%e' . $expectedFileNameBase . '-%c%c%c%c-%c%c-%c%c-%c%c-%c%c-%c%c.png')
            );

        $webDriver->expects($this->once())
            ->method('getPageSource')
            ->willReturn('<html><body><h1>Foo</h1></body></html>');

        $test->wd = $webDriver;

        $fileGetContentsMock = $this->getFunctionMock('Lmc\Steward\Listener', 'file_put_contents');
        $fileGetContentsMock->expects($this->once())
            ->with(
                $this->matches('%s%esrc-tests%e' . $expectedFileNameBase . '-%c%c%c%c-%c%c-%c%c-%c%c-%c%c-%c%c.html'),
                '<html><body><h1>Foo</h1></body></html>'
            );

        $listener = new SnapshotListener();
        $listener->{$method}($test, $exception, 3.3);

        $output = $test->getActualOutput();
        $this->assertStringMatchesFormat(
            '[%s]%S
[%s] [WARN] Test failed on page "http://foo.bar", taking page snapshots:
[%s] Screenshot: "%s%esteward%esrc-tests%e' . $expectedFileNameBase . '-%s.png"
[%s] HTML snapshot: "%s%esteward%esrc-tests%e' . $expectedFileNameBase . '-%s.html"
[%s]%S',
            $output
        );
    }

    /**
     * @return array[]
     */
    public function provideBasicTestEvent(): array
    {
        $dummyException = new \Exception('Error exception', 333);
        $dummyFailureException = new AssertionFailedError('Failure exception');

        return [
            ['addError', $dummyException, 'FooBarTest', 'testFooBar', '', [], 'FooBarTest-testFooBar'],
            ['addFailure', $dummyFailureException, 'FooBarTest', 'testFooBar', '', [], 'FooBarTest-testFooBar'],
        ];
    }

    /**
     * @return array[]
     */
    public function provideTestWithDataSet(): array
    {
        $dummyException = new \Exception('Error exception', 333);

        return [
            'dataset without custom (numbered)' => [
                'addError',
                $dummyException,
                'FooBarTest',
                'testFooBar',
                '0',
                ['foo', 'bar'],
                'FooBarTest-testFooBar-with-data-set-0',
            ],
            'dataset with custom name' => [
                'addError',
                $dummyException,
                'FooBarTest',
                'testFooBar',
                'some data',
                ['foo', 'bar'],
                'FooBarTest-testFooBar-with-data-set-some-data',
            ],
        ];
    }

    public function testShouldNotTakeSnapshotIfTestIsNotStewardAbstractTestCase(): void
    {
        $test = new WarningTestCase('foo');

        $listener = new SnapshotListener();
        $listener->addError($test, new \Exception('Error', 333), 3.3);
        $listener->addFailure($test, new AssertionFailedError('Failure'), 3.3);

        $this->assertEmpty($test->getActualOutput());
    }

    public function testShouldAppendErrorOutputIfWebDriverIsNotAvailable(): void
    {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(AbstractTestCase::class, ['testFooBar'], 'FooBarTest');

        $listener = new SnapshotListener();
        $listener->addError($test, new \Exception('Error', 333), 3.3);

        $this->assertStringContainsString(
            '[WARN] WebDriver instance not found, cannot take snapshot.',
            $test->getActualOutput()
        );
    }

    public function testShouldAppendErrorOutputIfWebDriverThrowsException(): void
    {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(AbstractTestCase::class, ['testFooBar'], 'FooBarTest');

        /** @var RemoteWebDriver|MockObject $webDriver */
        $webDriver = $this->createMock(RemoteWebDriver::class);
        $webDriver->expects($this->once())
            ->method('getCurrentURL')
            ->willThrowException(new TimeoutException('Timed out'));

        $test->wd = $webDriver;

        $listener = new SnapshotListener();
        $listener->addError($test, new \Exception('Error', 333), 3.3);

        $this->assertStringContainsString(
            '[WARN] Error taking page snapshot, perhaps browser is not accessible?',
            $test->getActualOutput()
        );
    }
}
