<?php

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCaseBase;

class XmlPublisher extends AbstractPublisher
{
    /** @var string Default name of results file. */
    const FILE_NAME = 'results.xml';
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
     * @param string $dir
     */
    public function setFileDir($dir)
    {
        $this->fileDir = $dir;
    }

    /**
     * Change file name from the default. Mostly usable for testing.
     * @param string $fileName
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * Get full path to results file.
     * @return string
     */
    public function getFilePath()
    {
        if (!$this->fileDir) {
            $this->fileDir = ConfigProvider::getInstance()->logsDir;
        }

        return $this->fileDir . '/' . $this->fileName;
    }

    /**
     * Clean the file with all previous results (if exists).
     */
    public function clean()
    {
        if (file_exists($this->getFilePath())) {
            unlink($this->getFilePath());
        }
    }

    public function publishResults(
        $testCaseName,
        $status,
        $result = null,
        \DateTimeInterface $startDate = null,
        \DateTimeInterface $endDate = null
    ) {
        $xml = $this->readAndLock();

        $testCaseNode = $this->getTestCaseNode($xml, $testCaseName);
        $testCaseNode['status'] = $status;
        if (!empty($result)) {
            $testCaseNode['result'] = $result;
        }
        if ($startDate) {
            $testCaseNode['start'] = $startDate->format(\DateTime::ISO8601);
        }
        if ($endDate) {
            $testCaseNode['end'] = $endDate->format(\DateTime::ISO8601);
        }

        $this->writeAndUnlock($xml);
    }

    public function publishResult(
        $testCaseName,
        $testName,
        \PHPUnit_Framework_Test $testInstance,
        $status,
        $result = null,
        $message = null
    ) {
        if (!in_array($status, self::$testStatuses)) {
            throw new \InvalidArgumentException(
                sprintf('Tests status must be one of "%s", but "%s" given', implode(', ', self::$testStatuses), $status)
            );
        }
        if (!is_null($result) && !in_array($result, self::$testResults)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Tests result must be null or one of "%s", but "%s" given',
                    implode(', ', self::$testResults),
                    $result
                )
            );
        }

        $xml = $this->readAndLock();

        $testCaseNode = $this->getTestCaseNode($xml, $testCaseName);
        $testNode = $this->getTestNode($testCaseNode, $testCaseName, $testName);

        $testNode['status'] = $status;

        if ($status == self::TEST_STATUS_STARTED) {
            $testNode['start'] = (new \DateTimeImmutable())->format(\DateTime::ISO8601);

            $executor = $this->getTestExecutor($testInstance);
            if ($executor) {
                $testNode['executor'] = $executor;
            }
        }
        if ($status == self::TEST_STATUS_DONE) {
            $testNode['end'] = (new \DateTimeImmutable())->format(\DateTime::ISO8601);
        }

        if (!is_null($result)) {
            $testNode['result'] = $result;
        }

        $this->writeAndUnlock($xml);
    }

    /**
     * Get element for test case of given name. If id does not exist yet, it is created.
     * @param \SimpleXMLElement $xml
     * @param string $testCaseName
     * @return \SimpleXMLElement
     */
    protected function getTestCaseNode(\SimpleXMLElement $xml, $testCaseName)
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
     * @param \SimpleXMLElement $xml
     * @param string $testCaseName
     * @param string $testName
     * @return \SimpleXMLElement
     */
    protected function getTestNode(\SimpleXMLElement $xml, $testCaseName, $testName)
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

    /**
     * @return \SimpleXMLElement
     */
    protected function readAndLock()
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
        $this->fileHandle = fopen($file, 'c+');
        if (!flock($this->fileHandle, LOCK_EX)) {
            throw new \RuntimeException(sprintf('Cannot obtain lock for file "%s"', $file));
        }

        if (fstat($this->fileHandle)['size'] == 0) { // new or empty file => create empty xml element and add stylesheet
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

    /**
     * @param \SimpleXMLElement $xml
     */
    protected function writeAndUnlock(\SimpleXMLElement $xml)
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

    /**
     * @return string
     */
    private function getStylesheet()
    {
        $xslPath = __DIR__ . '/../Resources/results.xsl';
        $xsl = file_get_contents($xslPath);

        return $xsl;
    }

    /**
     * Encapsulate given attribute value into valid xpath expression.
     * @param string $input Value of an xpath attribute selector
     * @return string
     */
    protected function quoteXpathAttribute($input)
    {
        if (mb_strpos($input, '\'') === false) { // Selector does not contain single quotes
            return "'$input'"; // Encapsulate with double quotes
        } elseif (mb_strpos($input, '"') === false) { // Selector contain single quotes but not double quotes
            return "\"$input\""; // Encapsulate with single quotes
        }

        // When both single and double quotes are contained, escape each part individually and concat() all parts
        return "concat('" . strtr($input, ['\'' => '\', "\'", \'']) . "')";
    }

    /**
     * @param \PHPUnit_Framework_Test $test
     * @return string
     */
    protected function getTestExecutor(\PHPUnit_Framework_Test $test)
    {
        if (!$test instanceof AbstractTestCaseBase || !$test->wd instanceof RemoteWebDriver) {
            return '';
        }

        $serverUrl = ConfigProvider::getInstance()->serverUrl;

        $executor = (new SeleniumServerAdapter($serverUrl))
            ->getSessionExecutor($test->wd->getSessionID());

        return $executor;
    }
}
