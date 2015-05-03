<?php

namespace Lmc\Steward\Publisher;

use Lmc\Steward\ConfigHelper;

class XmlPublisherTest extends \PHPUnit_Framework_TestCase
{
    /** @var XmlPublisher */
    protected $publisher;

    public function setUp()
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['LOGS_DIR'] = dirname(self::getFilePath());
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->publisher = new XmlPublisher(null, null, null);
    }

    public static function tearDownAfterClass()
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
     * @param string $fileName
     * @return string
     */
    public static function getFilePath($fileName = 'empty.xml')
    {
        return __DIR__ . '/Fixtures/' . $fileName;
    }

    public function testShouldAllowToSetCustomFileName()
    {
        $this->assertNotContains('custom.xml', $this->publisher->getFilePath());
        $this->publisher->setFileName('custom.xml');
        $this->assertContains('custom.xml', $this->publisher->getFilePath());
    }

    public function testShouldAllowToOverrideConfigObjectFileDirWithCustomDir()
    {
        $this->publisher->setFileDir('foo/bar');

        $this->assertEquals('foo/bar/results.xml', $this->publisher->getFilePath());
    }

    public function testShouldCleanPreviousResults()
    {
        $fn = self::getFilePath('previous.xml');
        touch($fn);

        $this->assertFileExists($fn, 'Fixture file was not created'); // check preconditions

        $this->publisher->setFileName(basename($fn));

        $this->publisher->clean();

        $this->assertFileNotExists($fn);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Tests status must be one of "started, done", but "unknownStatus" given
     */
    public function testShouldNotAllowToPublishUnknownTestStatus()
    {
        $this->publisher->publishResult('testCaseName', 'testName', 'unknownStatus');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Tests result must be null or one of "passed, failed, broken, skipped, incomplete"
     */
    public function testShouldNotAllowToPublishUnknownTestResult()
    {
        $this->publisher->publishResult('testCaseName', 'testName', 'started', 'unknownResult');
    }

    /**
     * @return array
     */
    public function testShouldAddTestResultToEmptyFile()
    {
        $fileName = $this->createEmptyFile();

        $this->publisher->publishResult('testCaseNameFoo', 'testNameBar', 'started');

        /** @var \SimpleXMLElement $xml */
        $fullXml = simplexml_load_file($fileName);
        $xml = $fullXml[0];

        $this->assertInstanceOf('\SimpleXMLElement', $xml->testcase);
        $this->assertEquals('testCaseNameFoo', $xml->testcase['name']);

        $this->assertInstanceOf('\SimpleXMLElement', $xml->testcase->test);
        $this->assertEquals(1, count($xml->testcase->test));

        $this->assertEquals('testNameBar', $xml->testcase->test['name']);
        $this->assertEquals('started', $xml->testcase->test['status']);
        $this->assertEmpty($xml->testcase->test['result']);

        $this->assertNotEmpty($xml->testcase->test['start']);
        $startDate = (string) $xml->testcase->test['start']; // convert to string so it could be serialized by PHPUnit
        $this->assertEmpty($xml->testcase->test['end']);

        return [$fileName, $fullXml->asXML(), $startDate];
    }

    /**
     * @depends testShouldAddTestResultToEmptyFile
     * @param array
     */
    public function testShouldUpdateTestStatusWhenTestIsDone($params)
    {
        $fileName = $params[0];
        $xml = $params[1];
        $originalTestStartDate = $params[2];

        // Restore file contents (in process isolation the tearDownAfterClass would be called and file would be empty)
        file_put_contents($fileName, $xml);

        $this->publisher->setFileName(basename($fileName));
        $this->publisher->publishResult('testCaseNameFoo', 'testNameBar', 'done', 'passed');

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName)[0];

        // still only one test result is present
        $this->assertInstanceOf('\SimpleXMLElement', $xml->testcase->test);
        $this->assertEquals(1, count($xml->testcase->test));

        // the status is now updated and result is set
        $this->assertEquals('testNameBar', $xml->testcase->test['name']);
        $this->assertEquals('done', $xml->testcase->test['status']);
        $this->assertEquals('passed', $xml->testcase->test['result']);

        // start date was not updated
        $this->assertEquals($originalTestStartDate, $xml->testcase->test['start']);

        // and the end date is now set
        $this->assertNotEmpty($xml->testcase->test['end']);
    }

    public function testShouldAddTestcaseResultToEmptyFile()
    {
        $fileName = $this->createEmptyFile();

        $this->publisher->publishResults('testCaseNameFoo', 'queued');

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName)[0];

        $this->assertInstanceOf('\SimpleXMLElement', $xml->testcase);
        $this->assertEquals('testCaseNameFoo', $xml->testcase['name']);
        $this->assertEquals('queued', $xml->testcase['status']);
        $this->assertEmpty($xml->testcase->test['result']);
        $this->assertEmpty($xml->testcase->test['start']);
        $this->assertEmpty($xml->testcase->test['end']);

        return [$fileName];
    }

    /**
     * @depends testShouldAddTestcaseResultToEmptyFile
     * @param array $params
     */
    public function testShouldUpdateTestcaseStatusWhenDone($params)
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
        $xml = simplexml_load_file($fileName)[0];

        $this->assertInstanceOf('\SimpleXMLElement', $xml->testcase);
        $this->assertEquals('testCaseNameFoo', $xml->testcase['name']);
        $this->assertEquals('done', $xml->testcase['status']);
        $this->assertEquals('passed', $xml->testcase['result']);
        $this->assertNotEmpty($xml->testcase['start']);
        $this->assertNotEmpty($xml->testcase['end']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Directory "/notexisting" does not exist or is not writeable.
     */
    public function testShouldFailIfGivenDirectoryDoesNotExists()
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['LOGS_DIR'] = '/notexisting';
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $publisher = new XmlPublisher(null, null, null);
        $publisher->publishResult('testCaseNameFoo', 'testNameBar', 'started');
    }

    public function testShouldNotOverwriteTestsWithSameName()
    {
        $fileName = $this->createEmptyFile();

        // create first record for testFoo
        $this->publisher->publishResult('testCaseNameFoo', 'testFoo', 'done', XmlPublisher::TEST_RESULT_PASSED);

        // create first record for testFoo, but in different testcase
        $this->publisher->publishResult('testCaseNameBar', 'testFoo', 'done', XmlPublisher::TEST_RESULT_PASSED);

        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file($fileName)[0];

        $tests = $xml->xpath('//test[@name="testFoo"]');

        $this->assertCount(2, $tests);
    }

    /**
     * Create empty.xml file and sets is as Publisher file
     * @return string File name
     */
    protected function createEmptyFile()
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
