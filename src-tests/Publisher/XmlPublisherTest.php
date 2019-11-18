<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Lmc\Steward\ConfigHelper;
use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;

class XmlPublisherTest extends TestCase
{
    use PHPMock;

    /** @var XmlPublisher */
    protected $publisher;
    /** @var MockObject|Test */
    protected $testInstanceMock;

    protected function setUp(): void
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['LOGS_DIR'] = dirname(self::getFilePath());
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->publisher = new XmlPublisher();
        $this->testInstanceMock = $this->createMock(Test::class);
    }

    public static function tearDownAfterClass(): void
    {
        // return the file to original state (= empty file)
        $fn = self::getFilePath();
        if (file_exists($fn)) {
            unlink($fn);
        }
        touch($fn);
    }

    /**
     * Get path to xml fixtures file
     */
    public static function getFilePath(string $fileName = 'empty.xml'): string
    {
        return __DIR__ . '/Fixtures/' . $fileName;
    }

    public function testShouldAllowToSetCustomFileName(): void
    {
        $this->assertNotContains('custom.xml', $this->publisher->getFilePath());
        $this->publisher->setFileName('custom.xml');
        $this->assertContains('custom.xml', $this->publisher->getFilePath());
    }

    public function testShouldAllowToOverrideConfigObjectFileDirWithCustomDir(): void
    {
        $this->publisher->setFileDir('foo/bar');

        $this->assertEquals('foo/bar/results.xml', $this->publisher->getFilePath());
    }

    public function testShouldCleanPreviousResults(): void
    {
        $fn = self::getFilePath('previous.xml');
        touch($fn);

        $this->assertFileExists($fn, 'Fixture file was not created'); // check preconditions

        $this->publisher->setFileName(basename($fn));

        $this->publisher->clean();

        $this->assertFileNotExists($fn);
    }

    public function testShouldNotAllowToPublishUnknownTestStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tests status must be one of "started, done", but "unknownStatus" given');

        $this->publisher->publishResult('testCaseName', 'testName', $this->testInstanceMock, 'unknownStatus');
    }

    public function testShouldNotAllowToPublishUnknownTestResult(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Tests result must be null or one of "passed, failed, broken, skipped, incomplete", '
            . 'but "unknownResult" given'
        );

        $this->publisher->publishResult(
            'testCaseName',
            'testName',
            $this->testInstanceMock,
            'started',
            'unknownResult'
        );
    }

    public function testShouldAddTestResultToEmptyFile(): array
    {
        $fileName = $this->createEmptyFile();

        $this->publisher->publishResult('testCaseNameFoo', 'testNameBar', $this->testInstanceMock, 'started');

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);

        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase);
        $this->assertEquals('testCaseNameFoo', $xml->testcase['name']);

        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase->test);
        $this->assertCount(1, $xml->testcase->test);

        $this->assertEquals('testNameBar', $xml->testcase->test['name']);
        $this->assertEquals('started', $xml->testcase->test['status']);
        $this->assertEmpty($xml->testcase->test['result']);

        $this->assertNotEmpty((string) $xml->testcase->test['start']);
        $startDate = (string) $xml->testcase->test['start']; // convert to string so it could be serialized by PHPUnit
        $this->assertEmpty($xml->testcase->test['end']);

        return [$fileName, $xml->asXML(), $startDate];
    }

    /**
     * @depends testShouldAddTestResultToEmptyFile
     */
    public function testShouldUpdateTestStatusWhenTestIsDone(array $params): void
    {
        $fileName = $params[0];
        $xml = $params[1];
        $originalTestStartDate = $params[2];

        // Restore file contents (in process isolation the tearDownAfterClass would be called and file would be empty)
        file_put_contents($fileName, $xml);

        $this->publisher->setFileName(basename($fileName));
        $this->publisher->publishResult('testCaseNameFoo', 'testNameBar', $this->testInstanceMock, 'done', 'passed');

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);

        // still only one test result is present
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase->test);
        $this->assertCount(1, $xml->testcase->test);

        // the status is now updated and result is set
        $this->assertEquals('testNameBar', $xml->testcase->test['name']);
        $this->assertEquals('done', $xml->testcase->test['status']);
        $this->assertEquals('passed', $xml->testcase->test['result']);

        // start date was not updated
        $this->assertEquals($originalTestStartDate, $xml->testcase->test['start']);

        // and the end date is now set
        $this->assertNotEmpty((string) $xml->testcase->test['end']);
    }

    public function testShouldAddTestcaseResultToEmptyFile()
    {
        $fileName = $this->createEmptyFile();

        $this->publisher->publishResults('testCaseNameFoo', 'queued');

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);

        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase);
        $this->assertEquals('testCaseNameFoo', $xml->testcase['name']);
        $this->assertEquals('queued', $xml->testcase['status']);
        $this->assertEmpty((string) $xml->testcase->test['result']);
        $this->assertEmpty((string) $xml->testcase->test['start']);
        $this->assertEmpty((string) $xml->testcase->test['end']);

        return [$fileName];
    }

    /**
     * @depends testShouldAddTestcaseResultToEmptyFile
     */
    public function testShouldUpdateTestcaseStatusWhenDone(array $params): void
    {
        $fileName = $params[0];

        $this->publisher->setFileName(basename($fileName));
        $this->publisher->publishResults(
            'testCaseNameFoo',
            'done',
            'passed',
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);

        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase);
        $this->assertEquals('testCaseNameFoo', $xml->testcase['name']);
        $this->assertEquals('done', $xml->testcase['status']);
        $this->assertEquals('passed', $xml->testcase['result']);
        $this->assertNotEmpty((string) $xml->testcase['start']);
        $this->assertNotEmpty((string) $xml->testcase['end']);
    }

    public function testShouldFailIfGivenDirectoryDoesNotExists(): void
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['LOGS_DIR'] = '/notexisting';
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $publisher = new XmlPublisher();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory "/notexisting" does not exist or is not writeable.');

        $publisher->publishResult('testCaseNameFoo', 'testNameBar', $this->testInstanceMock, 'started');
    }

    public function testShouldNotOverwriteTestsWithSameName(): void
    {
        $fileName = $this->createEmptyFile();

        // create first record for testFoo
        $this->publisher->publishResult(
            'testCaseNameFoo',
            'testFoo',
            $this->testInstanceMock,
            'done',
            XmlPublisher::TEST_RESULT_PASSED
        );

        // create first record for testFoo, but in different testcase
        $this->publisher->publishResult(
            'testCaseNameBar',
            'testFoo',
            $this->testInstanceMock,
            'done',
            XmlPublisher::TEST_RESULT_PASSED
        );

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);

        $tests = $xml->xpath('//test[@name="testFoo"]');

        $this->assertCount(2, $tests);
    }

    /**
     * Check processing of tests with special characters in name which could appear when test have dataProvider and
     * its name is appended by PHPUnit.
     *
     * @dataProvider provideTestName
     */
    public function testShouldProperlyHandleTestsWithDataProvider(string $testCaseName, string $testName): void
    {
        $fileName = $this->createEmptyFile();

        // Add "started" record of the test
        $this->publisher->publishResult(
            $testCaseName,
            $testName,
            $this->testInstanceMock,
            XmlPublisher::TEST_STATUS_STARTED
        );

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase);
        $this->assertCount(1, $xml->testcase);
        $this->assertEquals($testCaseName, $xml->testcase['name']);
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase->test);
        $this->assertCount(1, $xml->testcase->test);
        $this->assertEquals($testName, $xml->testcase->test['name']);
        $this->assertEquals(XmlPublisher::TEST_STATUS_STARTED, $xml->testcase->test['status']);

        // Update the test status to "done"
        $this->publisher->publishResult(
            $testCaseName,
            $testName,
            $this->testInstanceMock,
            XmlPublisher::TEST_STATUS_DONE,
            XmlPublisher::TEST_RESULT_PASSED
        );

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase);
        $this->assertCount(1, $xml->testcase);
        $this->assertEquals($testCaseName, $xml->testcase['name']);
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase->test);
        $this->assertCount(1, $xml->testcase->test);
        $this->assertEquals($testName, $xml->testcase->test['name']);
        $this->assertEquals(XmlPublisher::TEST_STATUS_DONE, $xml->testcase->test['status']);
        $this->assertEquals(XmlPublisher::TEST_RESULT_PASSED, $xml->testcase->test['result']);
    }

    /**
     * @dataProvider provideEndpointTestsessionResponse
     */
    public function testShouldLogTestExecutorWhenTestStarted(
        string $testsessionEndpointResponse,
        ?string $expectedExecutor
    ): void {
        $webDriverMock = $this->createMock(RemoteWebDriver::class);
        $webDriverMock->expects($this->once())
            ->method('getSessionID')
            ->willReturn('session-id-foo-bar');

        $testMock = $this->createMock(AbstractTestCase::class);
        $testMock->wd = $webDriverMock;

        $fileGetContentsMock = $this->getFunctionMock('Lmc\Steward\Selenium', 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with('http://server.tld:4444/grid/api/testsession?session=session-id-foo-bar')
            ->willReturn($testsessionEndpointResponse);

        $fileName = $this->createEmptyFile();

        $this->publisher->publishResult(
            'testCaseNameFoo',
            'testFoo',
            $testMock,
            XmlPublisher::TEST_STATUS_STARTED
        );

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName);

        $this->assertEquals('testFoo', $xml->testcase->test['name']);
        $this->assertEquals('started', $xml->testcase->test['status']);
        $this->assertEquals($expectedExecutor, $xml->testcase->test['executor']);
    }

    /**
     * @return array[]
     */
    public function provideEndpointTestsessionResponse(): array
    {
        return [
            'executor found' => [
                file_get_contents(__DIR__ . '/../Selenium/Fixtures/testsession-found.json'),
                'http://10.1.255.241:5555',
            ],
            'empty response' => ['', null],
        ];
    }

    /**
     * @return array[]
     */
    public function provideTestName(): array
    {
        return [
            // Testcases
            'Testcase with single quotes' => ['test \' case', 'testName'],
            'Testcase with double quotes' => ['test " case', 'testName'],
            'Testcase with quotes combination' => ['test " ca\'se', 'testName'],
            'Testcase with other special chars' => ['test Fů Bař &amp; <Baž>', 'testName'],

            // Tests
            'Un-named dataset' => ['testCase', 'testBar with data set #1'],
            'Named dataset' => ['testCase', 'testBar with data set "foo"'],
            'Dataset with double quotes in name' => [
                'testCase',
                'testBar with data set "Really <weird> chara&amp;cters"',
            ],
            'Dataset with apostrophe in name (double quotes and apostrophes are combined in the whole name)' => [
                'testCase',
                'testBar with data set "Apostrophe \' in dataset name"',
            ],
            'Only apostrophes used in test name' => ['testCase', 'testBar with data set \'Foo\''],
        ];
    }

    /**
     * Create empty.xml file and sets is as Publisher file
     */
    protected function createEmptyFile(): string
    {
        // create empty file
        $fileName = self::getFilePath('empty.xml');
        if (file_exists($fileName)) {
            unlink($fileName);
        }
        touch($fileName);

        $this->publisher->setFileName(basename($fileName));

        return $fileName;
    }
}
