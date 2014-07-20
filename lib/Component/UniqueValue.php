<?php
/**
 * Generating unique values for identifiers based on the name of running test-case and current time
 */

namespace Lmc\Steward\Component;

class UniqueValue
{
    /** @var string Test case instance */
    protected $test;
    /** @var string Name of the test case class */
    protected $testClassName;

    /**
     * Create UniqueValue instance
     * @param \Lmc\Steward\Test\AbstractTestCaseBase $test
     */
    public function __construct(\Lmc\Steward\Test\AbstractTestCaseBase $test)
    {
        $this->test = $test;
        $this->testClassName = get_class($this->test);
    }

    public function getTestClassKey()
    {
        return str_replace(['/', '\\'], '-', $this->testClassName);
    }

    /**
     * Generates unique name from current time with prefix and suffix
     * if prefix is not specified uses fully qualified name of the test class
     * suffix is optional
     * @param string|null $prefix
     * @param string $suffix
     * @return string
     */
    public function createTimestampValue($prefix = null, $suffix = "")
    {
        if ($prefix === null) {
            $prefix = $this->getTestClassKey();
        }

        return $prefix . (new \DateTime())->format('YmdHis') . $suffix;
    }

    /**
     * Generates unique name from current time and $distinguishingPrefix and appends $readablePrefix and $readableSuffix
     * @param int $maxLength max total length including $readablePrefix and $readableSuffix - will cut the
     *      generated part
     * @param string $readablePrefix pre-pended to the hashed value
     * @param string $readableSuffix appended to the hashed value
     * @param null $distinguishingPrefix default uses the fully qualified class name of the test
     * @return string
     */
    public function createTimestampValueHash(
        $maxLength = 50,
        $readablePrefix = "",
        $readableSuffix = "",
        $distinguishingPrefix = null
    ) {
        // hashLength = maxLength - prefix length - suffix length
        $hashLength = $maxLength - strlen($readablePrefix) - strlen($readableSuffix);

        $hash = sha1($this->createTimestampValue($distinguishingPrefix));

        return $readablePrefix . substr($hash, 0, $hashLength) . $readableSuffix;
    }
}
