<?php

namespace Lmc\Steward\Component;

use Lmc\Steward\Test\AbstractTestCaseBase;

/**
 * Generate unique values for identifiers based on the name of running test-case and current time
 */
class UniqueValue extends AbstractComponent
{
    /** @var string Name of the test case class */
    protected $testClassName;

    /**
     * Create UniqueValue instance
     * @param AbstractTestCaseBase $tc TestCase instance
     */
    public function __construct(AbstractTestCaseBase $tc)
    {
        parent::__construct($tc);
        $this->testClassName = get_class($tc);
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
    public function createTimestampValue($prefix = null, $suffix = '')
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
     * @param string $distinguishingPrefix default uses the fully qualified class name of the test
     * @return string
     */
    public function createTimestampValueHash(
        $maxLength = 50,
        $readablePrefix = '',
        $readableSuffix = '',
        $distinguishingPrefix = null
    ) {
        // hashLength = maxLength - prefix length - suffix length
        $hashLength = $maxLength - mb_strlen($readablePrefix) - mb_strlen($readableSuffix);

        $hash = sha1($this->createTimestampValue($distinguishingPrefix));

        return $readablePrefix . mb_substr($hash, 0, $hashLength) . $readableSuffix;
    }
}
