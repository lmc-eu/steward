<?php

namespace Lmc\Steward\Test;

/**
 * Abstract test results publisher.
 */
abstract class AbstractPublisher
{
    /** @var string */
    protected $environment;

    /** @var string */
    protected $jobName;

    /** @var int */
    protected $buildNumber;

    /** @var bool */
    protected $debug = false;

    /**
     * @param string $environment
     * @param string $jobName
     * @param int $buildNumber
     */
    abstract public function __construct($environment, $jobName, $buildNumber);

    /**
     * Publish testcase result
     *
     * @param string $testCaseName
     * @param string $status
     * @param \DateTimeInterface $startDate Testcase start datetime
     * @param \DateTimeInterface $endDate Testcase end datetime
     */
    abstract public function publishResults(
        $testCaseName,
        $status,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    );

    /**
     * Publish results of one single test
     *
     * @param string $testCaseName
     * @param string $testName
     * @param string $status
     * @param string $message
     */
    abstract public function publishResult($testCaseName, $testName, $status, $message);

    /**
     * Is debug mode enabled?
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * Enable debug mode
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }
}
