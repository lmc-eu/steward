<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCase;
use PHPUnit\Framework\Test;

class XmlPublisher extends AbstractPublisher
{
    /** @var string Default name of results file. */
    public const FILE_NAME = 'results.xml';
    /** @var string */
    protected $fileDir;
    /** @var string */
    protected $fileName = self::FILE_NAME;
    /** @var resource|null */
    protected $fileHandle;

    /**
     * Set directory where results file should be stored. Usable when config object is not available (when
     * not called from PHPUnit testcase but from Command). If the file dir is not set, the value from Config object
     * is used.
     */
    public function setFileDir(string $dir): void
    {
        $this->fileDir = $dir;
    }

    /**
     * Change file name from the default. Mostly usable for testing.
     */
    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    /**
     * Get full path to results file.
     */
    public function getFilePath(): string
    {
        if (!$this->fileDir) {
            $this->fileDir = ConfigProvider::getInstance()->logsDir;
        }

        return $this->fileDir . '/' . $this->fileName;
    }

    /**
     * Clean the file with all previous results (if exists).
     */
    public function clean(): void
    {
        if (file_exists($this->getFilePath())) {
            unlink($this->getFilePath());
        }
    }

    public function publishResults(
        string $testCaseName,
        string $status,
        string $result = null,
        \DateTimeInterface $testCaseStartDate = null,
        \DateTimeInterface $testCaseEndDate = null
    ): void {
        $xml = $this->readAndLock();

        $testCaseNode = $this->getTestCaseNode($xml, $testCaseName);
        $testCaseNode['status'] = $status;
        if (!empty($result)) {
            $testCaseNode['result'] = $result;
        }
        if ($testCaseStartDate) {
            $testCaseNode['start'] = $testCaseStartDate->format(\DateTime::ISO8601);
        }
        if ($testCaseEndDate) {
            $testCaseNode['end'] = $testCaseEndDate->format(\DateTime::ISO8601);
        }

        $this->writeAndUnlock($xml);
    }

    public function publishResult(
        string $testCaseName,
        string $testName,
        Test $testInstance,
        string $status,
        string $result = null,
        string $message = null
    ): void {
        if (!in_array($status, self::TEST_STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Tests status must be one of "%s", but "%s" given', implode(', ', self::TEST_STATUSES), $status)
            );
        }
        if ($result !== null && !in_array($result, self::TEST_RESULTS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Tests result must be null or one of "%s", but "%s" given',
                    implode(', ', self::TEST_RESULTS),
                    $result
                )
            );
        }

        $xml = $this->readAndLock();

        $testCaseNode = $this->getTestCaseNode($xml, $testCaseName);
        $testNode = $this->getTestNode($testCaseNode, $testCaseName, $testName);

        $testNode['status'] = $status;

        if ($status === self::TEST_STATUS_STARTED) {
            $testNode['start'] = (new \DateTimeImmutable())->format(\DateTime::ISO8601);

            $executor = $this->getTestExecutor($testInstance);
            if ($executor) {
                $testNode['executor'] = $executor;
            }
        }
        if ($status === self::TEST_STATUS_DONE) {
            $testNode['end'] = (new \DateTimeImmutable())->format(\DateTime::ISO8601);
        }

        if ($result !== null) {
            $testNode['result'] = $result;
        }

        $this->writeAndUnlock($xml);
    }

    /**
     * Get element for test case of given name. If id does not exist yet, it is created.
     */
    protected function getTestCaseNode(\SimpleXMLElement $xml, string $testCaseName): \SimpleXMLElement
    {
        $testcaseNode = $xml->xpath(sprintf('//testcase[@name=%s]', $this->quoteXpathAttribute($testCaseName)));

        if (!$testcaseNode) {
            $testcaseNode = $xml->addChild('testcase');
            $testcaseNode->addAttribute('name', $testCaseName);
        } else {
            $testcaseNode = reset($testcaseNode);
        }

        return $testcaseNode;
    }

    /**
     * Get element for test of given name. If id does not exist yet, it is created.
     */
    protected function getTestNode(\SimpleXMLElement $xml, string $testCaseName, string $testName): \SimpleXMLElement
    {
        $testNode = $xml->xpath(
            sprintf(
                '//testcase[@name=%s]/test[@name=%s]',
                $this->quoteXpathAttribute($testCaseName),
                $this->quoteXpathAttribute($testName)
            )
        );

        if (!$testNode) {
            $testNode = $xml->addChild('test');
            $testNode->addAttribute('name', $testName);
        } else {
            $testNode = reset($testNode);
        }

        return $testNode;
    }

    protected function readAndLock(): \SimpleXMLElement
    {
        $file = $this->getFilePath();
        $fileDir = dirname($file);

        if ($this->fileHandle) {
            throw new \RuntimeException(
                sprintf('File "%s" was already opened by this XmlPublisher and closed', $file)
            );
        }

        if (!is_writable($fileDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" does not exist or is not writeable.', $fileDir));
        }

        // open (or create) the file and acquire exclusive lock (or wait until it is acquired)
        $this->fileHandle = fopen($file, 'c+b');
        if (!flock($this->fileHandle, LOCK_EX)) {
            throw new \RuntimeException(sprintf('Cannot obtain lock for file "%s"', $file));
        }

        if (fstat($this->fileHandle)['size'] === 0) { // new or empty file => create empty element and add stylesheet
            $xml = new \SimpleXMLElement(
                '<?xml version="1.0" encoding="utf-8" ?>'
                . '<?xml-stylesheet type="text/xsl" href="#stylesheet"?>'
                . '<!DOCTYPE testcases [<!ATTLIST xsl:stylesheet id ID #REQUIRED>]>'
                . '<testcases>'
                . $this->getStylesheet()
                . '</testcases>'
            );
        } else { // file already exists, load current xml
            $fileContents = fread($this->fileHandle, fstat($this->fileHandle)['size']);
            $xml = simplexml_load_string($fileContents);
        }

        return $xml;
    }

    protected function writeAndUnlock(\SimpleXMLElement $xml): void
    {
        if (!$this->fileHandle) {
            throw new \RuntimeException(
                sprintf(
                    'File "%s" was not opened by this XmlPublisher yet (or it was already closed)',
                    $this->getFilePath()
                )
            );
        }

        // remove all file contents
        ftruncate($this->fileHandle, 0);
        rewind($this->fileHandle);

        // write new contents (formatted)
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        fwrite($this->fileHandle, $dom->saveXML());

        // unlock and close the file, remove reference
        flock($this->fileHandle, LOCK_UN);
        fclose($this->fileHandle);
        $this->fileHandle = null;
    }

    private function getStylesheet(): string
    {
        $xslPath = __DIR__ . '/../Resources/results.xsl';

        return file_get_contents($xslPath);
    }

    /**
     * Encapsulate given attribute value into valid xpath expression.
     *
     * @param string $input Value of an xpath attribute selector
     */
    protected function quoteXpathAttribute(string $input): string
    {
        if (mb_strpos($input, '\'') === false) { // Selector does not contain single quotes
            return "'$input'"; // Encapsulate with double quotes
        }

        if (mb_strpos($input, '"') === false) { // Selector contain single quotes but not double quotes
            return "\"$input\""; // Encapsulate with single quotes
        }

        // When both single and double quotes are contained, escape each part individually and concat() all parts
        return "concat('" . strtr($input, ['\'' => '\', "\'", \'']) . "')";
    }

    protected function getTestExecutor(Test $test): string
    {
        if (!$test instanceof AbstractTestCase || !$test->wd instanceof RemoteWebDriver) {
            return '';
        }

        $serverUrl = ConfigProvider::getInstance()->serverUrl;

        $executor = (new SeleniumServerAdapter($serverUrl))
            ->getSessionExecutor($test->wd->getSessionID());

        return $executor;
    }
}
