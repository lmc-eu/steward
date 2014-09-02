<?php

namespace Lmc\Steward\Test;

class XmlPublisher extends AbstractPublisher
{
    /** @var string */
    protected $fileName;

    /** @var resource */
    protected $fileHandle;

    /**
     * @param string $environment
     * @param string $jobName
     * @param int $buildNumber
     */
    public function __construct($environment, $jobName, $buildNumber)
    {
        $this->fileName = realpath(__DIR__ . '/../../logs') . '/results.xml';
    }

    /**
     * @param string $fileName
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * Publish testcase result
     *
     * @param string $testCaseName
     * @param string $status
     * @param string $result
     * @param \DateTimeInterface $startDate Testcase start datetime
     * @param \DateTimeInterface $endDate Testcase end datetime
     */
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
        if ($result) {
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

    /**
     * Publish results of one single test
     *
     * @param string $testCaseName
     * @param string $testName
     * @param string $status
     * @param string $result
     * @param string $message
     */
    public function publishResult($testCaseName, $testName, $status, $result = null, $message = null)
    {
        $xml = $this->readAndLock();

        $testCaseNode = $this->getTestCaseNode($xml, $testCaseName);
        $testNode = $this->getTestNode($testCaseNode, $testName);

        $testNode['status'] = $status;
        if (!is_null($result)) {
            $testNode['result'] = self::$testResultsMap[$result]; // @TODO: use directly the value
        }

        if ($status == 'started') { // @TODO: use constant
            $testNode['start'] = (new \DateTimeImmutable())->format(\DateTime::ISO8601);
        }
        if ($status == 'done') { // @TODO: use constant
            $testNode['end'] = (new \DateTimeImmutable())->format(\DateTime::ISO8601);
        }

        $this->writeAndUnlock($xml);
    }

    /**
     * Get element for test case of given name. If id does not exist yet, it is created.
     * @param \SimpleXMLElement $xml
     * @param $testCaseName
     * @return \SimpleXMLElement
     */
    protected function getTestCaseNode(\SimpleXMLElement $xml, $testCaseName)
    {
        $testcaseNode = $xml->xpath('//testcase[@name="' . $testCaseName . '"]');

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
     * @param $testName
     * @return \SimpleXMLElement
     */
    protected function getTestNode(\SimpleXMLElement $xml, $testName)
    {
        $testNode = $xml->xpath('//test[@name="' . $testName . '"]');

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
        if ($this->fileHandle) {
            throw new \RuntimeException(
                sprintf('File "%s" was already opened by this XmlPublisher and closed', $this->fileName)
            );
        }

        // open (or create) the file and acquire exclusive lock (or wait until it is acquired)
        $this->fileHandle = fopen($this->fileName, 'c+');
        if (!flock($this->fileHandle, LOCK_EX)) {
            throw new \RuntimeException(sprintf('Cannot obtain lock for file "%s"', $this->fileName));
        }

        if (fstat($this->fileHandle)['size'] == 0) { // new or empty file, create empty xml element
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><testcases />');
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
                    $this->fileName
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
}
