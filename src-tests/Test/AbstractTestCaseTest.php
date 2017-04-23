<?php

namespace Lmc\Steward\Test;

use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverOptions;
use Facebook\WebDriver\WebDriverWindow;
use Lmc\Steward\Component\TestUtils;
use Lmc\Steward\ConfigHelper;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use PHPUnit\Framework\TestCase;

class AbstractTestCaseTest extends TestCase
{
    const EXPECTED_TIMESTAMP_PATTERN = '\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]';

    /** @var AbstractTestCase */
    protected $testCase;

    protected function setUp()
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = 1;
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->testCase = $this->getMockForAbstractClass(
            AbstractTestCase::class,
            [],
            'MockedTest',
            false,
            true,
            true,
            ['getName']
        );

        $this->testCase->expects($this->any())
            ->method('getName')
            ->willReturn('testMethodDummyName');
    }

    public function testShouldSetDefaultWindowSizeInitUtilsAndOutputTestNameOnSetUp()
    {
        $wdMock = $this->createMock(RemoteWebDriver::class);
        $wdOptionsMock = $this->createMock(WebDriverOptions::class);
        $wdWindowMock = $this->createMock(WebDriverWindow::class);

        $wdMock->expects($this->once())
            ->method('manage')
            ->willReturn($wdOptionsMock);

        $wdOptionsMock->expects($this->once())
            ->method('window')
            ->willReturn($wdWindowMock);

        $wdWindowMock->expects($this->once())
            ->method('setSize')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(WebDriverDimension::class),
                    $this->attributeEqualTo('width', 1280),
                    $this->attributeEqualTo('height', 1024)
                )
            );

        $this->testCase->wd = $wdMock;

        $this->testCase->setUp();

        $this->expectOutputRegex(
            '/^' . self::EXPECTED_TIMESTAMP_PATTERN . ': Starting execution of test MockedTest\:\:testMethodDummyName$/'
        );

        $this->assertInstanceOf(TestUtils::class, $this->testCase->utils);
    }

    public function testShouldLogTestNameToOutputOnTearDown()
    {
        $this->testCase->tearDown();

        $this->expectOutputRegex(
            '/^' . self::EXPECTED_TIMESTAMP_PATTERN . ': Finished execution of test MockedTest\:\:testMethodDummyName$/'
        );
    }

    /**
     * @dataProvider provideLogStrings
     * @param string $expectedOutput
     * @param string $logMethod
     * @param string $format
     * @param array ...$arguments
     */
    public function testShouldLogToOutput($expectedOutput, $logMethod, $format, ...$arguments)
    {
        $expectedOutput = preg_quote($expectedOutput);
        $this->expectOutputRegex('/^' . self::EXPECTED_TIMESTAMP_PATTERN . $expectedOutput . '$/');

        if (!empty($arguments)) {
            $this->testCase->$logMethod($format, ...$arguments);
        } else {
            $this->testCase->$logMethod($format);
        }
    }

    public function provideLogStrings()
    {
        return [
            'log simple string' => [': This is output', 'log', 'This is output'],
            'warn simple string' => [' [WARN]: This is warning', 'warn', 'This is warning'],
            'debug simple string' => [' [DEBUG]: This is debug', 'debug', 'This is debug'],
            'log with multiple formatted params' => [': Foo 1337 bar baz', 'log', 'Foo %d bar %s', 1337, 'baz'],
            'log with params passed as an array' => [': 1337-baz-333', 'log', '%d-%s-%d', [1337, 'baz', 333]],
        ];
    }

    public function testShouldNotLogDebugOutputIfDebugModeIsNotEnabled()
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = 0;
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->expectOutputString('');
        $this->testCase->debug('Output that should not be printed');
    }

    public function testCurrentOutputShouldAlsoContainAppendedOutput()
    {
        $this->testCase->appendTestLog('Appended %s', 'foo');

        $output = $this->testCase->getActualOutput();

        $this->assertRegExp('/^' . self::EXPECTED_TIMESTAMP_PATTERN . ': Appended foo$/', $output);
    }
}
