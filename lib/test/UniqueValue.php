<?php
/**
 * Generating unique values for identifiers based on the name of running test-case and current time
 */

namespace Lmc\Steward\Test;

class UniqueValue
{
    /**
     * Create UniqueValue instance
     * @param \Lmc\Steward\Test\AbstractTestCaseBase $test
     */
    public function __construct(AbstractTestCaseBase $test)
    {
        $this->test = $test;
        $this->testClassName = get_class($this->test);
    }

    public function getTestClassKey()
    {
        return str_replace(['/', '\\'], '-', $this->testClassName);
    }

    /**
     * generates unique name from current time with prefix and suffix
     * if prefix is not specified uses fully qualified name of the test class
     * suffix is optional
     * @param null $prefix
     * @param string $suffix
     * @return string
     */
    public function createTimestampValue($prefix = null, $suffix = "")
    {
        if ($prefix === null) {
            $prefix = $this->getTestClassKey();
        }
        $now = new \DateTime();

        return $prefix . $now->format('YmdHis') . $suffix;
    }

    /**
     * generates unique name from current time and $distinguishingPrefix and appends $readablePrefix and $readableSuffix
     * @param int $maxLength max total length including $readablePrefix and $readableSuffix - will cut the generated part
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
        // maxLength - prefix length - suffix length
        $ml = $maxLength - strlen($readablePrefix) - strlen($readableSuffix);
        $sha1 = sha1($this->createTimestampValue($distinguishingPrefix));

        return $readablePrefix . substr($sha1, 0, $ml) . $readableSuffix;
    }
}
